<?php

declare(strict_types=1);

namespace QBitFlow\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use QBitFlow\Enums\Credential;
use QBitFlow\Enums\Role;
use QBitFlow\Exceptions\ValidationException;
use QBitFlow\Http\Requester;
use QBitFlow\Models\Customer;
use QBitFlow\Page;
use QBitFlow\Params\CustomerListParams;
use QBitFlow\QBitFlow;
use QBitFlow\RequestOptions;
use QBitFlow\Services\WebhookEndpointsService;
use QBitFlow\Tests\Support\MockHttpClient;
use QBitFlow\Tests\Support\TestCase;

/**
 * The client: construction checks, headers, On-Behalf-Of, me(), pagination and iterators.
 */
final class ClientBehaviourTest extends TestCase
{
	#[Test]
	public function it_checks_the_api_key(): void
	{
		foreach (['', '   ', 'pk_live_123', 'SK_live', 'key'] as $key) {
			$this->assertSame(['apiKey'], $this->failingFields(static fn () => new QBitFlow($key)), $key);
		}
		foreach (['sk_', 'sk_123_live_abc', 'sk_019eca82-5680-7b00-8000-0000000000b1_test_x', '  sk_padded  '] as $key) {
			$this->assertInstanceOf(QBitFlow::class, new QBitFlow($key));
		}
	}

	#[Test]
	public function it_has_defaults_and_wires_every_service(): void
	{
		$client = new QBitFlow('sk_x');
		$this->assertSame('https://api.qbitflow.app/v2', $client->getBaseUrl());
		$this->assertSame(QBitFlow::DEFAULT_BASE_URL, $client->getBaseUrl());
		$this->assertSame(30.0, QBitFlow::DEFAULT_TIMEOUT);
		$this->assertSame(3, QBitFlow::DEFAULT_MAX_RETRIES);
		$this->assertSame('3.0.0', QBitFlow::VERSION);
		$this->assertNull($client->getOnBehalfOf());
		$this->assertInstanceOf(WebhookEndpointsService::class, $client->webhooks->endpoints);
		foreach (['products', 'customers', 'checkoutSessions', 'payments', 'failures', 'subscriptions', 'refunds', 'members',
			'invitations', 'wallets', 'accounting', 'webhooks', 'currencies'] as $service) {
			$this->assertSame($client->{$service}, $client->{$service}(), $service . ': the property and the facade method agree');
		}
		$this->assertStringNotContainsString('secret', print_r(new QBitFlow('sk_live_secret_1234'), true), 'the key never shows in debug output');
	}

	#[Test]
	public function it_checks_the_options(): void
	{
		$bad = [
			'baseUrl ftp' => static fn () => new QBitFlow('sk_x', baseUrl: 'ftp://api.example.com'),
			'baseUrl relative' => static fn () => new QBitFlow('sk_x', baseUrl: '/v2'),
			'baseUrl empty' => static fn () => new QBitFlow('sk_x', baseUrl: ''),
			'timeout zero' => static fn () => new QBitFlow('sk_x', timeout: 0),
			'timeout negative' => static fn () => new QBitFlow('sk_x', timeout: -1.0),
			'maxRetries' => static fn () => new QBitFlow('sk_x', maxRetries: -1),
			'onBehalfOf garbage' => static fn () => new QBitFlow('sk_x', onBehalfOf: '42'),
			'onBehalfOf nil' => static fn () => new QBitFlow('sk_x', onBehalfOf: '00000000-0000-0000-0000-000000000000'),
			'onBehalfOf spaces' => static fn () => new QBitFlow('sk_x', onBehalfOf: ' ' . self::MEMBER_UUID),
		];
		foreach ($bad as $name => $build) {
			$this->assertNotSame([], $this->failingFields($build), $name);
		}

		$client = new QBitFlow('sk_x', baseUrl: 'https://sandbox.example.com/v2///', timeout: 2.0, maxRetries: 0, onBehalfOf: self::MEMBER_UUID);
		$this->assertSame('https://sandbox.example.com/v2', $client->getBaseUrl(), 'trailing slashes stripped');
		$this->assertSame(self::MEMBER_UUID, $client->getOnBehalfOf());
	}

	#[Test]
	public function it_sends_the_headers(): void
	{
		$client = $this->client(MockHttpClient::static(200, '{"credential":"apiKey"}'));
		$client->me();
		(new Requester($this->transport()))->call('POST', '/product', static fn ($d) => $d, body: ['name' => 'x'], idempotent: true,
			options: new RequestOptions(requestId: 'req-1.a:b_c'));

		[$get, $post] = $this->http->requests;
		foreach ($this->http->requests as $request) {
			$this->assertSame(self::API_KEY, $request->getHeaderLine('X-API-Key'));
			$this->assertSame('qbitflow-php/3.0.0', $request->getHeaderLine('User-Agent'));
			$this->assertSame('application/json', $request->getHeaderLine('Accept'));
			$this->assertFalse($request->hasHeader('On-Behalf-Of'));
		}
		$this->assertSame('GET', $get->getMethod());
		$this->assertSame('/me', self::pathOf($get));
		foreach (['Content-Type', 'Idempotency-Key', 'X-Request-Id'] as $header) {
			$this->assertFalse($get->hasHeader($header), 'GET sent ' . $header);
		}
		$this->assertSame('application/json', $post->getHeaderLine('Content-Type'));
		$this->assertSame('{"name":"x"}', (string) $post->getBody());
		$this->assertSame('req-1.a:b_c', $post->getHeaderLine('X-Request-Id'));
		$this->assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $post->getHeaderLine('Idempotency-Key'));
	}

	#[Test]
	public function it_checks_the_request_id(): void
	{
		$client = $this->client(MockHttpClient::static(200, '{}'));
		foreach (['has space', 'slash/no', 'é', str_repeat('x', 129)] as $id) {
			$this->assertSame(['X-Request-Id'], $this->failingFields(static fn () => $client->me(new RequestOptions(requestId: $id))), $id);
		}
		$this->assertSame(0, $this->http->count());
	}

	#[Test]
	public function it_acts_on_behalf_of_a_member(): void
	{
		$other = '01a05cd7-2a00-7d00-8000-0000000000d1';
		$client = $this->client(MockHttpClient::static(200, '{}'));
		$member = $client->onBehalfOf(self::MEMBER_UUID);
		$orgOnly = $member->onBehalfOf('');

		$calls = [
			[$client, null, null],
			[$member, null, self::MEMBER_UUID],
			[$member, new RequestOptions(onBehalfOf: $other), $other], // the request's wins
			[$member, new RequestOptions(onBehalfOf: ''), null],       // '' forces the organization level
			[$client, new RequestOptions(onBehalfOf: $other), $other],
			[$orgOnly, null, null],
		];
		foreach ($calls as [$c, $options]) {
			$c->me($options);
		}
		foreach ($calls as $i => [, , $want]) {
			$request = $this->http->requests[$i];
			$this->assertSame($want, $request->hasHeader('On-Behalf-Of') ? $request->getHeaderLine('On-Behalf-Of') : null, 'call ' . $i);
		}
		$this->assertSame(self::MEMBER_UUID, $member->getOnBehalfOf());
		$this->assertNull($client->getOnBehalfOf(), 'the original client is unchanged');

		// Client-level option, and uppercase UUIDs sent as given.
		$client->onBehalfOf('019ECA82-5680-7B00-8000-0000000000B1')->me();
		$this->assertSame('019ECA82-5680-7B00-8000-0000000000B1', $this->http->last()->getHeaderLine('On-Behalf-Of'));
	}

	#[Test]
	public function an_invalid_on_behalf_of_is_refused_eagerly(): void
	{
		$client = $this->client(MockHttpClient::static(200, '{}'));
		$this->assertSame(['onBehalfOf'], $this->failingFields(static fn () => $client->onBehalfOf('not-a-uuid')));
		$this->assertSame(['onBehalfOf'], $this->failingFields(static fn () => $client->onBehalfOf('00000000-0000-0000-0000-000000000000')));
		$this->assertSame(['onBehalfOf'], $this->failingFields(static fn () => $client->me(new RequestOptions(onBehalfOf: '123'))));
		$this->assertSame(0, $this->http->count());
		$client->me();
		$this->assertSame(1, $this->http->count());
	}

	#[Test]
	public function me_describes_the_key(): void
	{
		$client = $this->client(MockHttpClient::static(200, '{
			"credential": "apiKey", "apiKeyUuid": "019cadfd-8900-7b00-8000-0000000000f1", "role": "user",
			"onBehalfOf": "019eca82-5680-7b00-8000-0000000000b1",
			"space": {"uuid": "019eca82-5680-7c00-8000-0000000000b2", "organizationUuid": "019cadfd-8900-7a00-8000-0000000000a1",
				"organizationName": "Example Shop", "userUuid": "019eca82-5680-7b00-8000-0000000000b1",
				"member": {"userUuid": "019eca82-5680-7b00-8000-0000000000b1", "name": "Ada", "lastName": "Lovelace", "email": "ada@example.com"},
				"test": true},
			"user": {"ignored": true}}'));
		$me = $client->me();
		$this->assertSame(Credential::API_KEY, $me->credential);
		$this->assertSame(Role::USER, $me->role);
		$this->assertSame(self::MEMBER_UUID, $me->onBehalfOf);
		$this->assertNull($me->userUuid);
		$this->assertNotNull($me->space);
		$this->assertTrue($me->space->test);
		$this->assertSame('Example Shop', $me->space->organizationName);
		$this->assertSame('Ada', $me->space->member?->name);
	}

	// ---- Pagination -----------------------------------------------------------------------

	/** Serves /customer/all in pages of 2 over 5 customers (c0…c4), keyed by cursor. */
	private function pagedClient(string $failOn = ''): QBitFlow
	{
		return $this->client(new MockHttpClient(static function (RequestInterface $r) use ($failOn): ResponseInterface {
			parse_str($r->getUri()->getQuery(), $q);
			$cursor = $q['cursor'] ?? '';
			if ($failOn !== '' && $cursor === $failOn) {
				return MockHttpClient::response(400, '{"error":"bad cursor","code":"validation_failed","details":{"errors":[{"field":"cursor","message":"cursor is unknown"}]}}');
			}

			return MockHttpClient::response(200, [
				'' => '{"items":[{"uuid":"c0"},{"uuid":"c1"}],"nextCursor":"c1"}',
				'c1' => '{"items":[{"uuid":"c2"},{"uuid":"c3"}],"nextCursor":"c3"}',
				'c3' => '{"items":[{"uuid":"c4"}],"nextCursor":null}',
			][$cursor]);
		}));
	}

	/** @return list<string> */
	private static function uuids(iterable $items, int $stopAfter = 0): array
	{
		$out = [];
		foreach ($items as $item) {
			$out[] = $item->uuid;
			if ($stopAfter > 0 && count($out) === $stopAfter) {
				break;
			}
		}

		return $out;
	}

	#[Test]
	public function iterate_walks_every_page_keeping_the_filters(): void
	{
		$client = $this->pagedClient();
		$this->assertSame(['c0', 'c1', 'c2', 'c3', 'c4'], self::uuids($client->customers->iterate(new CustomerListParams(limit: 2, email: 'a@b.co'))));
		$this->assertSame(3, $this->http->count());
		foreach (['email=a%40b.co&limit=2', 'cursor=c1&email=a%40b.co&limit=2', 'cursor=c3&email=a%40b.co&limit=2'] as $i => $query) {
			$this->assertSame($query, $this->http->requests[$i]->getUri()->getQuery());
		}
	}

	#[Test]
	public function iterate_resumes_from_a_cursor_and_accepts_no_params(): void
	{
		$client = $this->pagedClient();
		$this->assertSame(['c4'], self::uuids($client->customers->iterate(new CustomerListParams(cursor: 'c3'))));
		$this->assertSame(1, $this->http->count(), 'the first page is never re-requested');
		$this->assertCount(5, self::uuids($client->customers->iterate()));
	}

	#[Test]
	public function iterate_is_lazy(): void
	{
		$client = $this->pagedClient();
		$generator = $client->customers->iterate();
		$this->assertSame(0, $this->http->count(), 'nothing is fetched before the iteration');
		$this->assertSame(['c0', 'c1', 'c2'], self::uuids($generator, 3));
		$this->assertSame(2, $this->http->count());
	}

	#[Test]
	public function iterate_throws_the_error_after_the_items_before_it(): void
	{
		$client = $this->pagedClient('c3');
		$got = [];
		try {
			foreach ($client->customers->iterate() as $customer) {
				$got[] = $customer->uuid;
			}
			$this->fail('want a ValidationException');
		} catch (ValidationException $e) {
			$this->assertSame('cursor', $e->fieldErrors[0]->field);
		}
		$this->assertSame(['c0', 'c1', 'c2', 'c3'], $got);
	}

	#[Test]
	public function the_walk_stops_on_an_empty_or_stuck_page(): void
	{
		$calls = 0;
		$got = [];
		foreach (Requester::walk(null, static function () use (&$calls): Page {
			$calls++;

			return new Page($calls === 2 ? [] : [$calls], 'n' . $calls);
		}) as $value) {
			$got[] = $value;
		}
		$this->assertSame([1], $got);
		$this->assertSame(2, $calls);

		$calls = 0;
		foreach (Requester::walk('same', static function () use (&$calls): Page {
			$calls++;

			return new Page([1], 'same');
		}) as $value) {
		}
		$this->assertSame(1, $calls, 'a cursor that does not move stops the walk');
	}

	#[Test]
	public function a_page_says_whether_more_follow(): void
	{
		$this->assertTrue((new Page([], 'x'))->hasMore());
		$this->assertFalse((new Page())->hasMore());
		$page = Page::fromArray(['items' => null, 'nextCursor' => null], Customer::fromArray(...));
		$this->assertSame([], $page->items);
		$this->assertFalse($page->hasMore());
	}
}
