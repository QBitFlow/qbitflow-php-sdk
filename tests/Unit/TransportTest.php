<?php

declare(strict_types=1);

namespace QBitFlow\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use QBitFlow\Exceptions\ConflictException;
use QBitFlow\Exceptions\ForbiddenException;
use QBitFlow\Exceptions\NetworkException;
use QBitFlow\Exceptions\NotFoundException;
use QBitFlow\Exceptions\QBitFlowException;
use QBitFlow\Exceptions\RateLimitException;
use QBitFlow\Exceptions\ServerException;
use QBitFlow\Exceptions\UnauthorizedException;
use QBitFlow\Exceptions\ValidationException;
use QBitFlow\Http\Transport;
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
	public function a_204_with_an_empty_body_is_accepted(): void
	{
		$this->http->pushRaw('', 204);

		$this->assertSame([], $this->transport()->delete('/product/1'));
	}

	#[Test]
	public function an_empty_2xx_body_where_json_is_expected_is_a_server_error(): void
	{
		$this->http->pushRaw('', 200, 'application/json');

		try {
			$this->transport()->get('/product/1');
			$this->fail('Expected a ServerException.');
		} catch (ServerException $e) {
			$this->assertSame(200, $e->getStatusCode());
			$this->assertStringContainsString('empty body', $e->getMessage());
		}
	}

	#[Test]
	public function a_scalar_json_body_is_a_server_error(): void
	{
		$this->http->push('"ok"');

		$this->expectException(ServerException::class);
		$this->expectExceptionMessage('expected a JSON object or list, got string');

		$this->transport()->get('/product/1');
	}

	#[Test]
	public function a_json_null_body_decodes_to_null(): void
	{
		// Go encodes a nil slice as null; list endpoints turn it into [].
		$this->http->push('null');

		$this->assertNull($this->transport()->get('/product/'));
	}

	#[Test]
	public function a_response_shape_failure_carries_the_http_status(): void
	{
		$this->http->push(['id' => 'not-a-number'], 201);

		try {
			$this->client()->products->create(new \QBitFlow\Dto\CreateProductDto('Widget', 'A widget', 1.0));
			$this->fail('Expected a ServerException.');
		} catch (ServerException $e) {
			$this->assertSame(201, $e->getStatusCode());
			$this->assertStringContainsString('"id" must be an integer', $e->getMessage());
			$this->assertSame(['id' => 'not-a-number'], $e->getResponse());
		}
	}

	#[Test]
	public function an_integer_beyond_php_int_max_is_reported_not_rounded(): void
	{
		$this->http->push('{"id":18446744073709551615,"createdAt":"2026-01-01T00:00:00Z"}');

		try {
			$this->client()->products->get(1);
			$this->fail('Expected a ServerException.');
		} catch (ServerException $e) {
			$this->assertSame(200, $e->getStatusCode());
			$this->assertStringContainsString('beyond PHP\'s range', $e->getMessage());
		}
	}

	#[Test]
	public function a_body_json_cannot_encode_is_a_validation_error_not_a_json_exception(): void
	{
		foreach ([['price' => NAN], ['price' => INF], ['name' => "Jos\xE9"]] as $data) {
			try {
				$this->transport()->post('/product/', $data);
				$this->fail('Expected a ValidationException.');
			} catch (ValidationException $e) {
				$this->assertStringContainsString('cannot be encoded as JSON', $e->getMessage());
				$this->assertInstanceOf(\JsonException::class, $e->getPrevious());
			}
		}

		$this->assertSame(0, $this->http->requestCount());
	}

	#[Test]
	public function it_returns_the_raw_body_untouched(): void
	{
		$csv = "paymentId,amount\npay_1,10.00\n";
		$this->http->pushRaw($csv);

		$this->assertSame($csv, $this->transport()->raw('GET', '/accounting/export'));
	}

	#[Test]
	public function it_rejects_a_negative_retry_count(): void
	{
		$this->expectException(ValidationException::class);

		new Transport('key', maxRetries: -1, httpClient: $this->http);
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
		yield '409 becomes a conflict' => [409, ConflictException::class];
		yield '422 is also a validation error' => [422, ValidationException::class];
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

	/**
	 * @return iterable<string,array{int}>
	 */
	public static function otherClientErrorProvider(): iterable
	{
		yield '402' => [402];
		yield '405' => [405];
		yield '410' => [410];
		yield '418' => [418];
	}

	#[Test]
	#[DataProvider('otherClientErrorProvider')]
	public function an_unmapped_4xx_is_the_base_exception_not_a_validation_error(int $status): void
	{
		// Calling a 410 "validation failed" would send the caller to fix a request that
		// was fine; the base type with the status code is the honest answer.
		$this->http->push(['error' => 'nope'], $status);

		try {
			$this->transport()->get('/product/1');
			$this->fail('Expected a QBitFlowException.');
		} catch (QBitFlowException $e) {
			$this->assertSame(QBitFlowException::class, $e::class);
			$this->assertSame($status, $e->getStatusCode());
			$this->assertSame('nope', $e->getMessage());
		}
	}

	#[Test]
	public function a_redirect_is_reported_immediately_as_a_server_error(): void
	{
		// The API never redirects, so a 3xx means the base URL points somewhere else. It is
		// neither followed nor retried.
		$this->http->push([], 302, ['Location' => 'https://elsewhere.test/']);

		try {
			$this->transport()->get('/product/');
			$this->fail('Expected a ServerException.');
		} catch (ServerException $e) {
			$this->assertSame(302, $e->getStatusCode());
			$this->assertSame(1, $this->http->requestCount());
			$this->assertSame([], $this->sleeps);
		}
	}

	#[Test]
	public function the_guzzle_client_the_sdk_builds_never_follows_a_real_redirect(): void
	{
		// A real local server answering 302 with a Location header. Following it would
		// replay the X-API-Key header to whatever host the redirect names.
		$script = <<<'PHP'
			$server = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
			if ($server === false) { exit(1); }
			$address = stream_socket_get_name($server, false);
			fwrite(STDOUT, $address . "\n");
			fflush(STDOUT);
			$client = stream_socket_accept($server, 5);
			if ($client !== false) {
				fread($client, 8192);
				fwrite($client, "HTTP/1.1 302 Found\r\nLocation: http://{$address}/elsewhere\r\n"
					. "Content-Length: 0\r\nConnection: close\r\n\r\n");
				fclose($client);
			}
			// A followed redirect would arrive as a second connection; count it.
			$second = @stream_socket_accept($server, 1);
			fwrite(STDOUT, $second === false ? "no-follow\n" : "followed\n");
			PHP;

		$process = proc_open([PHP_BINARY, '-r', $script], [1 => ['pipe', 'w']], $pipes);

		if (! is_resource($process)) {
			$this->markTestSkipped('Cannot start a local redirect server.');
		}

		$address = trim((string) fgets($pipes[1]));

		try {
			(new Transport('key', 'http://' . $address, 2.0, 0))->get('/product/');
			$this->fail('Expected a ServerException.');
		} catch (ServerException $e) {
			$this->assertSame(302, $e->getStatusCode());
		} finally {
			$verdict = trim((string) stream_get_contents($pipes[1]));
			fclose($pipes[1]);
			proc_close($process);
		}

		$this->assertSame('no-follow', $verdict);
	}

	#[Test]
	public function it_reads_retry_after_as_an_http_date_too(): void
	{
		$when = gmdate('D, d M Y H:i:s', time() + 120) . ' GMT';
		$this->http->push(['error' => 'Slow down'], 429, ['Retry-After' => $when]);
		$this->http->push(['error' => 'Slow down'], 429, ['Retry-After' => 'Wed, 21 Oct 2015 07:28:00 GMT']);
		$this->http->push(['error' => 'Slow down'], 429, ['Retry-After' => 'soon']);

		$seen = [];

		for ($i = 0; $i < 3; $i++) {
			try {
				$this->transport()->get('/product/');
			} catch (RateLimitException $e) {
				$seen[] = $e->getRetryAfter();
			}
		}

		$this->assertGreaterThanOrEqual(118, $seen[0]);
		$this->assertLessThanOrEqual(120, $seen[0]);
		$this->assertSame(0, $seen[1], 'A date in the past means "now", never a negative wait.');
		$this->assertNull($seen[2]);
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
			$this->assertSame([], $this->sleeps, 'A 429 is never retried automatically.');
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
		yield 'unrecognised shape falls back to the status line' => [['detail' => 'nope'], 'HTTP 400 Bad Request'];
		yield 'field name is included when the API supplies one' => [
			['errors' => [['field' => 'Price', 'message' => 'Price is too short']]],
			'Price: Price is too short',
		];
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

	/**
	 * The body below is the API's real validation envelope, captured from the server.
	 * Reporting only the first failure leaves the caller to discover the rest one
	 * round-trip at a time.
	 */
	#[Test]
	public function it_reports_every_field_failure_not_just_the_first(): void
	{
		$this->http->push([
			'errors' => [
				['field' => 'ProductName', 'message' => 'ProductName is too short'],
				['field' => 'Price', 'message' => 'Price is too short'],
			],
		], 400);

		try {
			$this->transport()->get('/product/');
			$this->fail('Expected a ValidationException.');
		} catch (ValidationException $e) {
			$this->assertStringContainsString('ProductName is too short', $e->getMessage());
			$this->assertStringContainsString('Price is too short', $e->getMessage());

			$fields = $e->getFields();
			$this->assertCount(2, $fields);
			$this->assertSame('ProductName', $fields[0]->field);
			$this->assertSame('ProductName is too short', $fields[0]->message);
			$this->assertSame('Price', $fields[1]->field);
			$this->assertSame('Price is too short', $fields[1]->message);
		}
	}

	#[Test]
	public function field_failures_are_parsed_for_every_status_not_only_400(): void
	{
		$this->http->push(['errors' => [['field' => 'Id', 'message' => 'Id is unknown']]], 404);

		try {
			$this->transport()->get('/product/1');
			$this->fail('Expected a NotFoundException.');
		} catch (NotFoundException $e) {
			$this->assertCount(1, $e->getFields());
			$this->assertSame('Id', $e->getFields()[0]->field);
		}
	}

	#[Test]
	public function it_exposes_no_fields_when_the_error_is_not_a_validation_list(): void
	{
		$this->http->push(['error' => 'resource not found'], 404);

		try {
			$this->transport()->get('/product/1');
			$this->fail('Expected a NotFoundException.');
		} catch (NotFoundException $e) {
			$this->assertSame([], $e->getFields());
		}
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
	public function it_falls_back_to_the_status_line_for_an_empty_error_body(): void
	{
		$this->http->pushRaw('', 400, 'text/plain');

		$this->expectException(ValidationException::class);
		$this->expectExceptionMessage('HTTP 400 Bad Request');

		$this->transport()->get('/product/');
	}

	// -------------------------------------------------------------------------
	// Retries
	// -------------------------------------------------------------------------

	#[Test]
	public function it_retries_a_get_on_server_errors_and_returns_the_eventual_success(): void
	{
		$this->http
			->push(['error' => 'boom'], 500)
			->push(['error' => 'boom'], 503)
			->push(['id' => 1]);

		$this->assertSame(['id' => 1], $this->transport()->get('/product/1'));
		$this->assertSame(3, $this->http->requestCount());
		$this->assertSame([1.0, 2.0], $this->sleeps, 'Backoff doubles with each attempt.');
	}

	#[Test]
	public function backoff_is_exponential(): void
	{
		$this->http
			->push([], 500)
			->push([], 500)
			->push([], 500)
			->push(['id' => 1]);

		$this->transport(maxRetries: 3)->get('/product/1');

		$this->assertSame([1.0, 2.0, 4.0], $this->sleeps);
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

	/**
	 * @return iterable<string,array{string}>
	 */
	public static function nonIdempotentMethodProvider(): iterable
	{
		yield 'POST' => ['POST'];
		yield 'PUT' => ['PUT'];
		yield 'DELETE' => ['DELETE'];
	}

	#[Test]
	#[DataProvider('nonIdempotentMethodProvider')]
	public function it_never_retries_a_non_idempotent_request_on_a_server_error(string $method): void
	{
		// A create that timed out after the server processed it would be replayed into a
		// duplicate checkout session; the caller must decide whether to retry.
		$this->http->push(['error' => 'boom'], 503);

		try {
			$this->sendWith($method);
			$this->fail('Expected a ServerException.');
		} catch (ServerException) {
			$this->assertSame(1, $this->http->requestCount(), $method . ' must be sent exactly once.');
			$this->assertSame([], $this->sleeps);
		}
	}

	#[Test]
	#[DataProvider('nonIdempotentMethodProvider')]
	public function it_never_retries_a_non_idempotent_request_on_a_network_failure(string $method): void
	{
		$this->http->pushFailure();

		try {
			$this->sendWith($method);
			$this->fail('Expected a NetworkException.');
		} catch (NetworkException) {
			$this->assertSame(1, $this->http->requestCount());
			$this->assertSame([], $this->sleeps);
		}
	}

	#[Test]
	public function a_get_marked_non_retriable_is_sent_once(): void
	{
		// force-cancel and execute-billing are GET routes that perform an action.
		$this->http->push([], 500);

		try {
			$this->transport()->get('/transaction/subscription/processing/force-cancel/sub1', retry: false);
			$this->fail('Expected a ServerException.');
		} catch (ServerException) {
			$this->assertSame(1, $this->http->requestCount());
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
	public function a_request_exception_on_a_get_is_retried_like_any_transport_failure(): void
	{
		// Guzzle reports a connection reset or a truncated response (cURL 18, 55, 56) as a
		// PSR-18 RequestExceptionInterface, not only a malformed request — those are
		// transient and must be retried on a GET.
		$this->http->pushRequestFailure('cURL error 18: end of response with 94 bytes missing')
			->push(['id' => 7]);

		$this->assertSame(['id' => 7], $this->transport()->get('/product/7'));
		$this->assertSame(2, $this->http->requestCount());
		$this->assertSame([1.0], $this->sleeps);
	}

	#[Test]
	public function a_request_exception_is_reported_as_a_network_error_once_retries_run_out(): void
	{
		$this->http->pushRequestFailure('reset')->pushRequestFailure('reset');

		try {
			$this->transport(maxRetries: 1)->get('/product/');
			$this->fail('Expected a NetworkException.');
		} catch (NetworkException $e) {
			$this->assertStringContainsString('reset', $e->getMessage());
			$this->assertNull($e->getStatusCode());
			$this->assertSame(2, $this->http->requestCount());
		}
	}

	#[Test]
	public function a_request_exception_on_a_post_is_not_retried(): void
	{
		$this->http->pushRequestFailure('reset');

		$this->expectException(NetworkException::class);

		try {
			$this->transport()->post('/customer/', ['name' => 'John']);
		} finally {
			$this->assertSame(1, $this->http->requestCount());
		}
	}

	#[Test]
	public function a_csv_export_error_body_is_parsed_as_json(): void
	{
		$this->http->push(['errors' => [['field' => 'From', 'message' => 'From is required']]], 400);

		try {
			$this->transport()->raw('GET', '/accounting/export', ['format' => 'csv']);
			$this->fail('Expected a ValidationException.');
		} catch (ValidationException $e) {
			$this->assertSame(400, $e->getStatusCode());
			$this->assertSame('From: From is required', $e->getMessage());
			$this->assertSame('From', $e->getFields()[0]->field);
		}
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

	private function sendWith(string $method): void
	{
		$transport = $this->transport(maxRetries: 3);

		match ($method) {
			'POST' => $transport->post('/customer/', ['name' => 'John']),
			'PUT' => $transport->put('/customer/c1', ['name' => 'John']),
			'DELETE' => $transport->delete('/customer/uuid/c1'),
		};
	}
}
