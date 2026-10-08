<?php

declare(strict_types=1);

namespace QBitFlow\Tests\Unit;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use QBitFlow\Exceptions\ApiException;
use QBitFlow\Exceptions\AuthenticationException;
use QBitFlow\Exceptions\BadRequestException;
use QBitFlow\Exceptions\ConflictException;
use QBitFlow\Exceptions\ExceptionInterface;
use QBitFlow\Exceptions\FieldError;
use QBitFlow\Exceptions\GoneException;
use QBitFlow\Exceptions\IdempotencyException;
use QBitFlow\Exceptions\NetworkException;
use QBitFlow\Exceptions\NotFoundException;
use QBitFlow\Exceptions\PermissionDeniedException;
use QBitFlow\Exceptions\QBitFlowException;
use QBitFlow\Exceptions\RateLimitException;
use QBitFlow\Exceptions\ServerException;
use QBitFlow\Exceptions\ValidationException;
use QBitFlow\Http\RawResponse;
use QBitFlow\Http\Requester;
use QBitFlow\Http\Transport;
use QBitFlow\Models\Product;
use QBitFlow\Models\Subscription;
use QBitFlow\Params\PaymentListParams;
use QBitFlow\RequestOptions;
use QBitFlow\Support\Query;
use QBitFlow\Tests\Support\MockHttpClient;
use QBitFlow\Tests\Support\MockNetworkException;
use QBitFlow\Tests\Support\TestCase;
use stdClass;

/**
 * The HTTP layer: path and query encoding, body encoding, the error mapping, the retry matrix,
 * Retry-After and idempotency keys (behaviour §1, §3, §4).
 */
final class TransportTest extends TestCase
{
	private const OK = [200, '{"ok":true}'];

	private const ERR500 = [500, '{"error":"boom","code":"internal"}'];

	private const ERR503 = [503, '{"error":"no network","code":"network_unavailable"}'];

	private const ERR504 = [504, '{"error":"too slow","code":"timeout"}'];

	private const KEY_IN_USE = [409, '{"error":"in use","code":"idempotency_key_in_use"}'];

	// ---- Paths and queries ----------------------------------------------------------------

	#[Test]
	public function it_escapes_every_path_segment(): void
	{
		$cases = [
			[Requester::path('/product/reference/%s', 'a/b c'), '/product/reference/a%2Fb%20c'],
			[Requester::path('/transaction/payment/%s', 'pay@019c-1'), '/transaction/payment/pay@019c-1'],
			[Requester::path('/customer/email/%s', 'a+b@example.com'), '/customer/email/a+b@example.com'],
			[Requester::path('/product/reference/%s', '..'), '/product/reference/%2E%2E'],
			[Requester::path('/product/reference/%s', '.'), '/product/reference/%2E'],
			[Requester::path('/product/reference/%s', 'v1..2'), '/product/reference/v1..2'],
			[Requester::path('/x/%s/y/%s', '?q=1#f', '%41'), '/x/%3Fq=1%23f/y/%2541'],
			[Requester::path('/transaction/subscription/reference/%s/%s', 'subscription', 'ord:1'), '/transaction/subscription/reference/subscription/ord:1'],
		];
		foreach ($cases as [$got, $want]) {
			$this->assertSame($want, $got);
		}

		// The escaped path reaches the server as is.
		$this->transport(MockHttpClient::static(200, '{}'))->send('GET', Requester::path('/product/reference/%s', 'a/b'));
		$this->assertSame('/v2/product/reference/a%2Fb', $this->http->last()->getUri()->getPath());
	}

	#[Test]
	public function it_encodes_queries_like_go(): void
	{
		$after = new DateTimeImmutable('2026-10-04T12:00:00+02:00');
		$before = new DateTimeImmutable('2026-10-05T00:00:00.500000Z');
		$params = new PaymentListParams(limit: 25, cursor: '019c-cursor', customerUuid: self::MEMBER_UUID, createdAfter: $after,
			createdBefore: $before, includeMembers: true, refunded: false);
		$query = $params->toQuery();
		$this->assertSame('2026-10-04T12:00:00+02:00', $query['createdAfter']);
		$this->assertSame('2026-10-05T00:00:00.5Z', $query['createdBefore']);
		$this->assertSame(
			'createdAfter=2026-10-04T12%3A00%3A00%2B02%3A00&createdBefore=2026-10-05T00%3A00%3A00.5Z&cursor=019c-cursor&customerUuid='
				. self::MEMBER_UUID . '&includeMembers=true&limit=25&refunded=false',
			Query::encode($query),
		);
		$this->assertSame([], (new PaymentListParams())->toQuery(), 'unset filters are omitted');

		$this->assertSame('2026-10-01T00:00:00Z', Query::formatTime(new DateTimeImmutable('2026-10-01T00:00:00+00:00')), 'a zero offset is Z');
		$this->assertSame('2026-10-01T02:00:00+02:00', Query::formatTime(new DateTimeImmutable('2026-10-01T02:00:00', new DateTimeZone('+02:00'))));
		$this->assertSame('2026-10-01T00:00:00.123456Z', Query::formatTime(new DateTimeImmutable('2026-10-01T00:00:00.123456Z')));

		// Sent on the wire with the + escaped.
		$this->transport(MockHttpClient::static(200, '{"items":[],"nextCursor":null}'))->send('GET', '/transaction/payments', $query);
		$raw = $this->http->last()->getUri()->getQuery();
		$this->assertStringNotContainsString('+', $raw);
		parse_str($raw, $parsed);
		$this->assertSame('2026-10-04T12:00:00+02:00', $parsed['createdAfter']);
	}

	// ---- Body encoding --------------------------------------------------------------------

	/** @return iterable<string,array{0: array<string,mixed>|stdClass, 1: string}> */
	public static function badBodies(): iterable
	{
		yield 'NaN' => [['name' => 'Pro', 'price' => NAN], ''];
		yield 'Inf' => [['price' => INF], ''];
		yield 'invalid UTF-8 name' => [['name' => "Ad\xffa", 'email' => 'a@b.co'], 'name'];
		yield 'invalid UTF-8 address' => [['address' => "bad \xfe"], 'address'];
		yield 'nested' => [['productUuid' => self::MEMBER_UUID, 'frequency' => ['value' => 1, 'unit' => "mon\xffths"]], 'frequency.unit'];
		yield 'key' => [["bad\xffkey" => 1], 'body'];
		yield 'list entry' => [['url' => 'https://x.io', 'events' => ['payment.completed', "x\xff"]], 'events[1]'];
		yield 'object' => [(object) ['subscription' => (object) ['frequency' => "\xff"]], 'subscription.frequency'];
	}

	/** @param array<string,mixed>|stdClass $body */
	#[Test]
	#[DataProvider('badBodies')]
	public function it_refuses_a_body_json_cannot_carry(array|stdClass $body, string $field): void
	{
		$transport = $this->transport(MockHttpClient::static(200, '{}'));
		try {
			$transport->send('POST', '/x', body: $body);
			$this->fail('want a ValidationException');
		} catch (ValidationException $e) {
			if ($field !== '') {
				$this->assertSame([$field], array_map(static fn (FieldError $f): string => $f->field, $e->fieldErrors));
			}
		}
		$this->assertSame(0, $this->http->count(), 'nothing is sent');
	}

	#[Test]
	public function it_sends_valid_utf8_untouched_and_empty_bodies_as_objects(): void
	{
		$transport = $this->transport(MockHttpClient::static(200, '{}'));
		$transport->send('POST', '/x', body: ['name' => "O’Brien ☃ \u{FFFD}", 'url' => 'https://a/b']);
		$this->assertSame('{"name":"O’Brien ☃ ' . "\u{FFFD}" . '","url":"https://a/b"}', (string) $this->http->last()->getBody());
		$this->assertSame('application/json', $this->http->last()->getHeaderLine('Content-Type'));

		$transport->send('PUT', '/x', body: []);
		$this->assertSame('{}', (string) $this->http->last()->getBody());

		$transport->send('GET', '/x');
		$this->assertSame('', (string) $this->http->last()->getBody());
		$this->assertFalse($this->http->last()->hasHeader('Content-Type'));
	}

	// ---- Decoding -------------------------------------------------------------------------

	#[Test]
	public function it_maps_unusable_2xx_bodies_to_server_errors(): void
	{
		foreach (['' => 'empty', "  \n" => 'blank', '<html>ok</html>' => 'html', '{"s":"x"' => 'truncated', 'null' => 'null object', '[1]' => 'list for object', '{"price":"5"}' => 'string for float'] as $body => $name) {
			try {
				Transport::decode(new RawResponse(201, ['x-request-id' => 'rid'], (string) $body), Requester::one(Product::fromArray(...)));
				$this->fail($name . ': want a ServerException');
			} catch (ServerException $e) {
				$this->assertSame(201, $e->status, $name);
				$this->assertSame('rid', $e->requestId, $name);
				$this->assertFalse($e->isRetryable(), $name . ': a response-shape failure is not retryable');
			}
		}
		$this->assertSame([], Transport::decode(new RawResponse(200, [], 'null'), Requester::list(Product::fromArray(...))));
	}

	#[Test]
	public function it_returns_statuses_text_and_voids(): void
	{
		$http = new MockHttpClient(static fn (RequestInterface $r): ResponseInterface => match ($r->getUri()->getPath()) {
			'/v2/cancel' => MockHttpClient::response(202, '{"uuid":"sub@1","status":"active"}'),
			'/v2/csv' => MockHttpClient::response(200, "paymentUuid,type\npay@1,payment\n", ['Content-Type' => 'text/csv']),
			'/v2/delete' => MockHttpClient::response(200, '{"message":"deleted"}'),
			'/v2/nocontent' => MockHttpClient::response(204),
			'/v2/empty' => MockHttpClient::response(200),
			'/v2/csv-error' => MockHttpClient::response(400, '{"error":"too long","code":"bad_request"}'),
		});
		$requester = new Requester($this->transport($http));

		[$sub, $status] = $requester->callWithStatus('POST', '/cancel', Requester::one(Subscription::fromArray(...)));
		$this->assertSame(202, $status);
		$this->assertSame('sub@1', $sub->uuid);

		$this->assertSame("paymentUuid,type\npay@1,payment\n", $requester->text('/csv', []));
		$this->assertSame('text/csv, application/json', $http->last()->getHeaderLine('Accept'));
		try {
			$requester->text('/csv-error', []);
			$this->fail('want a BadRequestException');
		} catch (BadRequestException $e) {
			$this->assertSame('bad_request', $e->apiCode);
		}

		foreach (['/delete', '/nocontent', '/empty'] as $path) {
			$requester->void('DELETE', $path);
		}
		foreach (['/empty', '/nocontent'] as $path) {
			try {
				$requester->call('GET', $path, Requester::one(Product::fromArray(...)));
				$this->fail($path . ': want a ServerException');
			} catch (ServerException) {
				$this->addToAssertionCount(1);
			}
		}
	}

	// ---- Errors ---------------------------------------------------------------------------

	/** @return iterable<string,array{0: int, 1: string, 2: class-string}> */
	public static function errorMapping(): iterable
	{
		$cases = [
			[400, 'validation_failed', ValidationException::class], [400, 'bad_request', BadRequestException::class],
			[400, 'foreign_key_violation', BadRequestException::class], [400, 'invalid_signature', BadRequestException::class],
			[400, '', BadRequestException::class], [401, 'unauthorized', AuthenticationException::class],
			[403, 'forbidden', PermissionDeniedException::class], [403, 'policy_disabled', PermissionDeniedException::class],
			[403, 'plan_required', PermissionDeniedException::class], [404, 'not_found', NotFoundException::class],
			[409, 'unique_violation', ConflictException::class], [409, 'tx_already_sent', ConflictException::class],
			[409, 'merchant_not_ready', ConflictException::class], [409, 'refund_already_exists', ConflictException::class],
			[409, 'held_funds_pending', ConflictException::class], [409, 'idempotency_key_in_use', ConflictException::class],
			[410, 'merchant_closed', GoneException::class], [422, 'idempotency_key_reused', IdempotencyException::class],
			[422, 'something_else', ApiException::class], [413, 'request_too_large', ApiException::class], [405, '', ApiException::class],
			[429, 'rate_limit_exceeded', RateLimitException::class], [500, 'internal', ServerException::class],
			[503, 'network_unavailable', ServerException::class], [504, 'timeout', ServerException::class],
			[301, '', ServerException::class], [304, '', ServerException::class],
		];
		foreach ($cases as [$status, $code, $class]) {
			yield $status . ' ' . $code => [$status, $code, $class];
		}
	}

	/** @param class-string $class */
	#[Test]
	#[DataProvider('errorMapping')]
	public function it_maps_each_status_and_code_to_its_exception(int $status, string $code, string $class): void
	{
		$body = json_encode(['error' => 'the message', 'code' => $code, 'requestId' => 'req-body']);
		$error = $this->transport()->errorFromResponse(new RawResponse($status, [], (string) $body));

		$this->assertSame($class, $error::class);
		$this->assertInstanceOf(ApiException::class, $error);
		$this->assertInstanceOf(QBitFlowException::class, $error);
		$this->assertInstanceOf(ExceptionInterface::class, $error);
		$this->assertSame($status, $error->status);
		$this->assertSame($status, $error->getCode(), 'getCode() is the HTTP status');
		$this->assertSame($code, $error->apiCode);
		$this->assertSame($code, $error->getApiCode());
		$this->assertSame('the message', $error->errorMessage);
		$this->assertSame('req-body', $error->requestId);
		$this->assertSame([], $error->details);
	}

	#[Test]
	public function it_reads_field_errors_from_details_only(): void
	{
		$body = '{"error":"name must be at least 2 characters","code":"validation_failed",'
			. '"details":{"errors":[{"field":"name","message":"name must be at least 2 characters"},'
			. '{"field":"frequency.unit","message":"frequency.unit is required"},"garbage",{"other":1}],"max":5},'
			. '"errors":[{"field":"TOPLEVEL","message":"must be ignored"}],"requestId":"abc-123","debug":"stack trace"}';
		$error = $this->transport()->errorFromResponse(new RawResponse(400, ['x-request-id' => 'from-header'], $body));

		$this->assertInstanceOf(ValidationException::class, $error);
		$this->assertEquals([new FieldError('name', 'name must be at least 2 characters'), new FieldError('frequency.unit', 'frequency.unit is required')], $error->fieldErrors);
		$this->assertSame('abc-123', $error->requestId, 'the body wins over the header');
		$this->assertSame(5, $error->details['max']);
		$this->assertSame('name must be at least 2 characters (status 400, code validation_failed, request abc-123); '
			. 'name: name must be at least 2 characters; frequency.unit: frequency.unit is required', $error->getMessage());
		$this->assertSame($body, $error->rawBody, 'the raw body keeps the debug text');
	}

	/** @return iterable<string,array{0: string, 1: string, 2: int, 3: string, 4: string, 5: string}> */
	public static function errorFallbacks(): iterable
	{
		yield 'no body' => ['', 'hdr-1', 404, 'not found', 'hdr-1', ''];
		yield 'html body' => ['<html>gateway</html>', '', 502, 'bad gateway', '', ''];
		yield 'json array' => ['[1,2]', 'hdr-2', 500, 'internal server error', 'hdr-2', ''];
		yield 'wrong types' => ['{"error": 5, "code": ["x"], "details": "str", "requestId": 7}', 'hdr-3', 403, 'forbidden', 'hdr-3', ''];
		yield 'legacy code' => ['{"error":"boom","code":"error"}', '', 409, 'boom', '', 'error'];
		yield 'header only id' => ['{"error":"x"}', 'hdr-4', 401, 'x', 'hdr-4', ''];
		yield 'unknown status' => ['{}', '', 499, 'http status 499', '', ''];
	}

	#[Test]
	#[DataProvider('errorFallbacks')]
	public function it_falls_back_on_defaults(string $body, string $header, int $status, string $message, string $requestId, string $code): void
	{
		$headers = $header === '' ? [] : ['x-request-id' => $header];
		$error = $this->transport()->errorFromResponse(new RawResponse($status, $headers, $body));
		$this->assertSame($message, $error->errorMessage);
		$this->assertSame($requestId, $error->requestId);
		$this->assertSame($code, $error->apiCode);
		$this->assertSame([], $error->details);
		$this->assertSame([], $error->fieldErrors);
	}

	#[Test]
	public function it_formats_messages_like_every_sdk(): void
	{
		$this->assertSame('not found (status 404, code not_found, request r1)', (new NotFoundException('not found', 404, 'not_found', requestId: 'r1'))->getMessage());
		$this->assertSame('boom (status 500)', (new ServerException('boom', 500))->getMessage());
		$this->assertSame('validation failed; apiKey: apiKey is required', (new ValidationException('validation failed', fieldErrors: [new FieldError('apiKey', 'apiKey is required')]))->getMessage());
		$this->assertSame('request failed: dial tcp: refused', (new NetworkException('request failed', previous: new \RuntimeException('dial tcp: refused')))->getMessage());
		$this->assertSame('qbitflow error', (new ApiException())->getMessage());
	}

	#[Test]
	public function errors_reach_the_caller_typed_with_the_header_request_id(): void
	{
		$client = $this->client(MockHttpClient::static(403, '{"error":"members.products is off","code":"policy_disabled","details":{"policy":"members.products"}}', ['X-Request-Id' => 'hdr-id']));
		try {
			$client->me();
			$this->fail('want a PermissionDeniedException');
		} catch (PermissionDeniedException $e) {
			$this->assertSame('members.products', $e->details['policy']);
			$this->assertSame('hdr-id', $e->requestId);
			$this->assertSame('policy_disabled', $e->apiCode);
		}
	}

	// ---- Retries --------------------------------------------------------------------------

	/** @return iterable<string,array{0: string, 1: bool, 2: list<array{0:int,1:string}>, 3: int, 4: list<float>, 5: class-string|null, 6: int|null}> */
	public static function retryMatrix(): iterable
	{
		$get = ['GET', false];
		$create = ['POST', true];
		yield 'GET 500 exhausts retries' => [...$get, [self::ERR500], 4, [1.0, 2.0, 4.0], ServerException::class, null];
		yield 'GET 503 then 200' => [...$get, [self::ERR503, self::OK], 2, [1.0], null, null];
		yield 'GET 504 then 200' => [...$get, [self::ERR504, self::OK], 2, [1.0], null, null];
		yield 'GET 502 non-JSON then 200' => [...$get, [[502, '<html>bad gateway</html>'], self::OK], 2, [1.0], null, null];
		yield 'GET 400 not retried' => [...$get, [[400, '{"error":"bad","code":"bad_request"}']], 1, [], BadRequestException::class, null];
		yield 'GET 401 not retried' => [...$get, [[401, '{"error":"no","code":"unauthorized"}']], 1, [], AuthenticationException::class, null];
		yield 'GET 403 not retried' => [...$get, [[403, '{"error":"no","code":"forbidden"}']], 1, [], PermissionDeniedException::class, null];
		yield 'GET 404 not retried' => [...$get, [[404, '{"error":"no","code":"not_found"}']], 1, [], NotFoundException::class, null];
		yield 'GET 409 in_use not retried (not a create)' => [...$get, [self::KEY_IN_USE], 1, [], ConflictException::class, null];
		yield 'POST action 500 not retried' => ['POST', false, [self::ERR500], 1, [], ServerException::class, null];
		yield 'POST action 503 not retried' => ['POST', false, [self::ERR503], 1, [], ServerException::class, null];
		yield 'PUT 500 not retried' => ['PUT', false, [self::ERR500], 1, [], ServerException::class, null];
		yield 'DELETE 503 not retried' => ['DELETE', false, [self::ERR503], 1, [], ServerException::class, null];
		yield 'POST action 429 not retried' => ['POST', false, [[429, '{"error":"slow","code":"rate_limit_exceeded"}']], 1, [], RateLimitException::class, null];
		yield 'create 500, 500, 201' => [...$create, [self::ERR500, self::ERR500, [201, '{}']], 3, [1.0, 2.0], null, null];
		yield 'create 409 in_use then 201' => [...$create, [self::KEY_IN_USE, [201, '{}']], 2, [1.0], null, null];
		yield 'create 409 unique_violation not retried' => [...$create, [[409, '{"error":"dup","code":"unique_violation","details":{"field":"reference"}}']], 1, [], ConflictException::class, null];
		yield 'create 422 key reused not retried' => [...$create, [[422, '{"error":"reused","code":"idempotency_key_reused"}']], 1, [], IdempotencyException::class, null];
		yield 'create 400 validation not retried' => [...$create, [[400, '{"error":"bad","code":"validation_failed"}']], 1, [], ValidationException::class, null];
		yield '3xx not retried' => [...$get, [[302, '']], 1, [], ServerException::class, null];
		yield 'maxRetries 0 disables' => [...$get, [self::ERR500], 1, [], ServerException::class, 0];
		yield 'maxRetries 1' => [...$get, [self::ERR500], 2, [1.0], ServerException::class, 1];
		yield 'maxRetries 5' => [...$get, [self::ERR503], 6, [1.0, 2.0, 4.0, 8.0, 16.0], ServerException::class, 5];
	}

	/**
	 * @param list<array{0:int,1:string}> $replies
	 * @param list<float>                 $sleeps
	 * @param class-string|null           $error
	 */
	#[Test]
	#[DataProvider('retryMatrix')]
	public function it_follows_the_retry_matrix(string $method, bool $idempotent, array $replies, int $attempts, array $sleeps, ?string $error, ?int $maxRetries): void
	{
		$transport = $this->transport(MockHttpClient::sequence($replies), $maxRetries ?? 3);
		$caught = null;
		try {
			$transport->send($method, '/x', body: $method === 'GET' ? null : ['name' => 'Pro'], idempotent: $idempotent);
		} catch (QBitFlowException $e) {
			$caught = $e;
		}

		$this->assertSame($attempts, $this->http->count(), 'attempts');
		$this->assertSame($sleeps, $this->sleeps, 'sleeps');
		if ($error === null) {
			$this->assertNull($caught, (string) $caught?->getMessage());
		} else {
			$this->assertInstanceOf($error, $caught);
		}
	}

	#[Test]
	public function the_idempotency_key_is_stable_across_retries_and_fresh_per_call(): void
	{
		$transport = $this->transport(MockHttpClient::sequence([self::ERR503, self::KEY_IN_USE, [201, '{}']]));
		$transport->send('POST', '/product', body: ['name' => 'Pro'], idempotent: true);

		$this->assertSame(3, $this->http->count());
		$key = $this->http->requests[0]->getHeaderLine('Idempotency-Key');
		$this->assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $key);
		foreach ($this->http->requests as $request) {
			$this->assertSame($key, $request->getHeaderLine('Idempotency-Key'));
			$this->assertSame('{"name":"Pro"}', (string) $request->getBody());
		}

		$transport->send('POST', '/product', body: ['name' => 'Pro'], idempotent: true);
		$this->assertNotSame($key, $this->http->requests[3]->getHeaderLine('Idempotency-Key'));
	}

	#[Test]
	public function a_caller_key_replaces_the_generated_one_and_is_checked(): void
	{
		$transport = $this->transport(MockHttpClient::sequence([self::ERR500, [201, '{}']]));
		$transport->send('POST', '/product', body: ['name' => 'Pro'], idempotent: true, options: new RequestOptions(idempotencyKey: 'order-1042:create~v1'));
		foreach ($this->http->requests as $request) {
			$this->assertSame('order-1042:create~v1', $request->getHeaderLine('Idempotency-Key'));
		}

		foreach (['has space', "tab\tkey", 'é', str_repeat('k', 256)] as $key) {
			try {
				$transport->send('POST', '/product', body: [], idempotent: true, options: new RequestOptions(idempotencyKey: $key));
				$this->fail('want a ValidationException for ' . $key);
			} catch (ValidationException $e) {
				$this->assertSame('Idempotency-Key', $e->fieldErrors[0]->field);
			}
		}
		$transport->send('POST', '/product', body: [], idempotent: true, options: new RequestOptions(idempotencyKey: str_repeat('k', 255)));

		// Ignored (and never sent, nor checked) on other methods.
		$transport->send('GET', '/x', options: new RequestOptions(idempotencyKey: 'has space'));
		$this->assertFalse($this->http->last()->hasHeader('Idempotency-Key'));
	}

	/** @return iterable<string,array{0: string, 1: string, 2: int, 3: list<float>, 4: int}> */
	public static function retryAfterCases(): iterable
	{
		$now = new DateTimeImmutable('2026-10-01T12:00:00Z');
		$date = static fn (int $seconds): string => $now->modify("+{$seconds} seconds")->format('D, d M Y H:i:s \G\M\T');
		yield 'delta seconds' => ['5', '{"error":"slow","code":"rate_limit_exceeded"}', 2, [5.0], 0];
		yield 'below the backoff' => ['0', '{"error":"slow"}', 2, [1.0], 0];
		yield 'HTTP-date' => [$date(3), '{"error":"slow"}', 2, [3.0], 0];
		yield 'details fallback' => ['', '{"error":"slow","details":{"retryAfterSeconds":7,"limit":60,"periodSeconds":60}}', 2, [7.0], 0];
		yield 'over 60 s: no retry' => ['120', '{"error":"slow","details":{"limit":50,"periodSeconds":3600}}', 1, [], 120];
		yield 'HTTP-date over 60 s: no retry' => [$date(90), '{"error":"slow"}', 1, [], 90];
		yield 'exactly 60 s: retried' => ['60', '{"error":"slow"}', 2, [60.0], 0];
	}

	/** @param list<float> $sleeps */
	#[Test]
	#[DataProvider('retryAfterCases')]
	public function it_honours_retry_after(string $header, string $body, int $attempts, array $sleeps, int $after): void
	{
		$this->now = new DateTimeImmutable('2026-10-01T12:00:00Z');
		$transport = $this->transport(new MockHttpClient(static fn (RequestInterface $r, int $n): ResponseInterface => $n === 0
			? MockHttpClient::response(429, $body, $header === '' ? [] : ['Retry-After' => $header])
			: MockHttpClient::response(200, '{}')));

		$caught = null;
		try {
			$transport->send('GET', '/x');
		} catch (RateLimitException $e) {
			$caught = $e;
		}
		$this->assertSame($attempts, $this->http->count());
		$this->assertSame($sleeps, $this->sleeps);
		if ($attempts === 1) {
			$this->assertNotNull($caught);
			$this->assertSame($after, $caught->retryAfter);
		} else {
			$this->assertNull($caught);
		}
	}

	#[Test]
	public function the_rate_limit_backoff_grows(): void
	{
		$transport = $this->transport(MockHttpClient::static(429, '{"error":"slow","code":"rate_limit_exceeded","details":{"limit":60,"periodSeconds":60,"retryAfterSeconds":3}}', ['Retry-After' => '3']));
		try {
			$transport->send('GET', '/x');
			$this->fail('want a RateLimitException');
		} catch (RateLimitException $e) {
			$this->assertSame([3.0, 3.0, 4.0], $this->sleeps);
			$this->assertSame(60, $e->limit);
			$this->assertSame(60, $e->periodSeconds);
			$this->assertSame(3, $e->retryAfter);
			$this->assertSame(3, $e->getRetryAfter());
		}
	}

	#[Test]
	public function network_errors_are_retried_on_reads_only(): void
	{
		$transport = $this->transport(new MockHttpClient(static fn (RequestInterface $r, int $n) => $n < 2
			? new MockNetworkException('Connection refused')
			: MockHttpClient::response(200, '{"ok":true}')));
		$transport->send('GET', '/x');
		$this->assertSame(3, $this->http->count());
		$this->assertCount(2, $this->sleeps);

		$down = new MockHttpClient(static fn () => new MockNetworkException('cURL error 7: Failed to connect'));
		$transport = $this->transport($down);
		try {
			$transport->send('GET', '/x');
			$this->fail('want a NetworkException');
		} catch (NetworkException $e) {
			$this->assertSame(0, $e->status);
			$this->assertInstanceOf(MockNetworkException::class, $e->getPrevious());
			$this->assertTrue($e->isRetryable());
			$this->assertStringStartsWith('request failed: ', $e->getMessage());
		}
		$this->assertSame(4, $down->count());

		$before = $down->count();
		try {
			$transport->send('POST', '/expire');
			$this->fail('want a NetworkException');
		} catch (NetworkException) {
			$this->assertSame(1, $down->count() - $before, 'a non-retried write is attempted once');
		}

		try {
			$this->transport(new MockHttpClient(static fn () => new MockNetworkException('cURL error 28: Operation timed out')), 0, 2.5)->send('GET', '/x');
			$this->fail('want a NetworkException');
		} catch (NetworkException $e) {
			$this->assertStringStartsWith('request timed out after 2.5s', $e->getMessage());
		}
	}

	#[Test]
	public function redirects_are_server_errors_and_not_retried(): void
	{
		$transport = $this->transport(MockHttpClient::static(302, '', ['Location' => '/elsewhere']));
		foreach ([false, true] as $idempotent) {
			try {
				$transport->send($idempotent ? 'POST' : 'GET', '/me', body: $idempotent ? [] : null, idempotent: $idempotent);
				$this->fail('want a ServerException');
			} catch (ServerException $e) {
				$this->assertSame(302, $e->status);
				$this->assertFalse($e->isRetryable());
			}
		}
		$this->assertSame(2, $this->http->count());
		$this->assertSame([], $this->sleeps);
	}

	#[Test]
	public function is_retryable_classifies_errors(): void
	{
		$make = fn (int $status, string $code): ApiException => $this->transport()->errorFromResponse(new RawResponse($status, [], json_encode(['error' => 'x', 'code' => $code]) ?: ''));
		$cases = [
			[$make(500, 'internal'), true], [$make(503, 'network_unavailable'), true], [$make(504, 'timeout'), true],
			[$make(429, 'rate_limit_exceeded'), true], [$make(409, 'idempotency_key_in_use'), true],
			[$make(409, 'unique_violation'), false], [$make(422, 'idempotency_key_reused'), false],
			[$make(400, 'validation_failed'), false], [$make(404, 'not_found'), false], [$make(302, ''), false],
			[new NetworkException('down'), true], [new ValidationException('bad'), false],
		];
		foreach ($cases as [$error, $want]) {
			$this->assertSame($want, $error->isRetryable(), $error->getMessage());
		}
	}

	#[Test]
	public function it_generates_uuid_v4_keys(): void
	{
		$seen = [];
		for ($i = 0; $i < 100; $i++) {
			$id = Transport::uuidV4();
			$this->assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $id);
			$this->assertArrayNotHasKey($id, $seen);
			$seen[$id] = true;
		}
	}
}
