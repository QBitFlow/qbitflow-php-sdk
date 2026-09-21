<?php

declare(strict_types=1);

namespace QBitFlow\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use QBitFlow\Exceptions\ForbiddenException;
use QBitFlow\Exceptions\NetworkException;
use QBitFlow\Exceptions\NotFoundException;
use QBitFlow\Exceptions\QBitFlowException;
use QBitFlow\Exceptions\RateLimitException;
use QBitFlow\Exceptions\ServerException;
use QBitFlow\Exceptions\UnauthorizedException;
use QBitFlow\Exceptions\ValidationException;
use QBitFlow\QBitFlow;
use QBitFlow\Tests\Support\TestCase;

final class TransportTest extends TestCase
{
	#[Test]
	public function it_sends_authentication_and_content_headers(): void
	{
		$this->http->push(['ok' => true]);

		$this->transport()->get('/product/');

		$request = $this->http->lastRequest();

		$this->assertSame('test-api-key', $request->getHeaderLine('X-API-Key'));
		$this->assertSame('application/json', $request->getHeaderLine('Accept'));
		$this->assertSame('qbitflow-php/' . QBitFlow::VERSION, $request->getHeaderLine('User-Agent'));
	}

	#[Test]
	public function it_sets_a_json_content_type_only_when_there_is_a_body(): void
	{
		$this->http->push([])->push([]);

		$transport = $this->transport();

		$transport->get('/product/');
		$this->assertSame('', $this->http->lastRequest()->getHeaderLine('Content-Type'));

		$transport->post('/product/', ['name' => 'Widget']);
		$this->assertSame('application/json', $this->http->lastRequest()->getHeaderLine('Content-Type'));
		$this->assertSame(['name' => 'Widget'], $this->http->lastBody());
	}

	#[Test]
	public function it_prefixes_endpoints_that_omit_the_leading_slash(): void
	{
		$this->http->push([]);

		$this->transport()->get('product/');

		$this->assertSame('/v1/product/', $this->http->lastRequest()->getUri()->getPath());
	}

	#[Test]
	public function it_renders_booleans_as_true_and_false_in_the_query_string(): void
	{
		$this->http->push([]);

		$this->transport()->get('/utils/all-available-currencies', ['test' => true]);

		$this->assertSame('test=true', $this->http->lastRequest()->getUri()->getQuery());

		$this->http->push([]);
		$this->transport()->get('/utils/all-available-currencies', ['test' => false]);

		$this->assertSame('test=false', $this->http->lastRequest()->getUri()->getQuery());
	}

	#[Test]
	public function it_drops_null_query_parameters(): void
	{
		$this->http->push([]);

		$this->transport()->get('/customer/all', ['limit' => 10, 'cursor' => null]);

		$this->assertSame('limit=10', $this->http->lastRequest()->getUri()->getQuery());
	}

	#[Test]
	public function it_returns_an_empty_array_for_an_empty_response_body(): void
	{
		$this->http->pushRaw('', 204);

		$this->assertSame([], $this->transport()->delete('/product/1'));
	}

	#[Test]
	public function it_returns_the_raw_body_untouched(): void
	{
		$csv = "paymentId,amount\npay_1,10.00\n";
		$this->http->pushRaw($csv);

		$this->assertSame($csv, $this->transport()->raw('GET', '/accounting/export'));
	}

	// -------------------------------------------------------------------------
	// Error mapping
	// -------------------------------------------------------------------------

	/**
	 * @return iterable<string,array{int,class-string<QBitFlowException>}>
	 */
	public static function clientErrorProvider(): iterable
	{
		yield '400 becomes a validation error' => [400, ValidationException::class];
		yield '401 becomes unauthorized' => [401, UnauthorizedException::class];
		yield '403 becomes forbidden' => [403, ForbiddenException::class];
		yield '404 becomes not found' => [404, NotFoundException::class];
		yield '422 falls back to validation' => [422, ValidationException::class];
		yield '429 becomes a rate limit error' => [429, RateLimitException::class];
	}

	#[Test]
	#[DataProvider('clientErrorProvider')]
	public function it_maps_client_errors_to_their_exception_type(int $status, string $expected): void
	{
		$this->http->push(['error' => 'Something went wrong'], $status);

		try {
			$this->transport()->get('/product/1');
			$this->fail('Expected ' . $expected . ' to be thrown.');
		} catch (QBitFlowException $e) {
			$this->assertInstanceOf($expected, $e);
			$this->assertSame('Something went wrong', $e->getMessage());
			$this->assertSame($status, $e->getStatusCode());
			$this->assertSame(['error' => 'Something went wrong'], $e->getResponse());
		}
	}

	#[Test]
	public function it_exposes_the_retry_after_header_on_rate_limit_errors(): void
	{
		$this->http->push(['error' => 'Slow down'], 429, ['Retry-After' => '30']);

		try {
			$this->transport()->get('/product/');
			$this->fail('Expected a RateLimitException.');
		} catch (RateLimitException $e) {
			$this->assertSame(30, $e->getRetryAfter());
		}
	}

	#[Test]
	public function it_formats_an_exception_with_its_status_code(): void
	{
		$e = new NotFoundException('Nope', 404);

		$this->assertSame('[404] Nope', (string) $e);
		$this->assertSame('Plain', (string) new NotFoundException('Plain'));
	}

	/**
	 * @return iterable<string,array{array<string,mixed>|string,string}>
	 */
	public static function errorShapeProvider(): iterable
	{
		yield 'error field' => [['error' => 'Bad key'], 'Bad key'];
		yield 'message field' => [['message' => 'Bad request'], 'Bad request'];
		yield 'errors array of objects' => [['errors' => [['message' => 'name is required']]], 'name is required'];
		yield 'errors array of strings' => [['errors' => ['name is required']], 'name is required'];
		yield 'error wins over message' => [['error' => 'First', 'message' => 'Second'], 'First'];
		yield 'unrecognised shape' => [['detail' => 'nope'], 'An error occurred'];
	}

	#[Test]
	#[DataProvider('errorShapeProvider')]
	public function it_extracts_the_message_from_every_known_error_shape(array|string $body, string $expected): void
	{
		$this->http->push($body, 400);

		$this->expectException(ValidationException::class);
		$this->expectExceptionMessage($expected);

		$this->transport()->get('/product/');
	}

	#[Test]
	public function it_uses_a_non_json_error_body_as_the_message(): void
	{
		$this->http->pushRaw('Gateway timeout', 400, 'text/plain');

		$this->expectException(ValidationException::class);
		$this->expectExceptionMessage('Gateway timeout');

		$this->transport()->get('/product/');
	}

	#[Test]
	public function it_falls_back_to_a_generic_message_for_an_empty_error_body(): void
	{
		$this->http->pushRaw('', 400, 'text/plain');

		$this->expectException(ValidationException::class);
		$this->expectExceptionMessage('An error occurred');

		$this->transport()->get('/product/');
	}

	// -------------------------------------------------------------------------
	// Retries
	// -------------------------------------------------------------------------

	#[Test]
	public function it_retries_server_errors_and_returns_the_eventual_success(): void
	{
		$this->http
			->push(['error' => 'boom'], 500)
			->push(['error' => 'boom'], 503)
			->push(['id' => 1]);

		$this->assertSame(['id' => 1], $this->transport()->get('/product/1'));
		$this->assertSame(3, $this->http->requestCount());
		$this->assertSame([1.0, 2.0], $this->sleeps, 'Backoff should grow with each attempt.');
	}

	#[Test]
	public function it_gives_up_on_server_errors_once_retries_are_exhausted(): void
	{
		$this->http
			->push(['error' => 'boom'], 500)
			->push(['error' => 'boom'], 500)
			->push(['error' => 'boom'], 500);

		$this->expectException(ServerException::class);
		$this->expectExceptionMessage('boom');

		try {
			$this->transport(maxRetries: 2)->get('/product/1');
		} finally {
			$this->assertSame(3, $this->http->requestCount(), 'One initial attempt plus two retries.');
		}
	}

	#[Test]
	public function it_never_retries_a_client_error(): void
	{
		$this->http->push(['error' => 'Not found'], 404);

		try {
			$this->transport()->get('/product/999');
			$this->fail('Expected a NotFoundException.');
		} catch (NotFoundException) {
			$this->assertSame(1, $this->http->requestCount());
			$this->assertSame([], $this->sleeps);
		}
	}

	#[Test]
	public function it_retries_network_failures_and_then_reports_them(): void
	{
		$this->http->pushFailure()->pushFailure()->pushFailure();

		$this->expectException(NetworkException::class);
		$this->expectExceptionMessage('Connection refused');

		try {
			$this->transport(maxRetries: 2)->get('/product/');
		} finally {
			$this->assertSame(3, $this->http->requestCount());
		}
	}

	#[Test]
	public function it_recovers_from_a_transient_network_failure(): void
	{
		$this->http->pushFailure()->push(['id' => 7]);

		$this->assertSame(['id' => 7], $this->transport()->get('/product/7'));
	}

	#[Test]
	public function it_does_not_retry_when_retries_are_disabled(): void
	{
		$this->http->push([], 500);

		$this->expectException(ServerException::class);

		try {
			$this->transport(maxRetries: 0)->get('/product/');
		} finally {
			$this->assertSame(1, $this->http->requestCount());
		}
	}

	#[Test]
	public function it_reports_an_unparseable_success_body_as_a_server_error(): void
	{
		$this->http->pushRaw('{not json', 200);

		$this->expectException(ServerException::class);
		$this->expectExceptionMessage('Failed to parse JSON response');

		$this->transport()->get('/product/');
	}
}
