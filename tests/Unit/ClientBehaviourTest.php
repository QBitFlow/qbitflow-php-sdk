<?php

declare(strict_types=1);

namespace QBitFlow\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use QBitFlow\Dto\Customer;
use QBitFlow\Dto\Session\CreatePaymentSessionDto;
use QBitFlow\Exceptions\NetworkException;
use QBitFlow\Exceptions\ServerException;
use QBitFlow\Exceptions\ValidationException;
use QBitFlow\Requests\ClaimRequests;
use QBitFlow\Requests\SubscriptionRequests;
use QBitFlow\Requests\WebhookRequests;
use QBitFlow\Support\CursorData;
use QBitFlow\Tests\Support\TestCase;
use QBitFlow\Webhooks\WebhookVerifier;

final class ClientBehaviourTest extends TestCase
{
	// -------------------------------------------------------------------------
	// onBehalfOf
	// -------------------------------------------------------------------------

	#[Test]
	public function on_behalf_of_adds_the_header_without_touching_the_original_service(): void
	{
		$client = $this->client();

		$this->http->push([]);
		$client->products->onBehalfOf(123)->getAll();
		$this->assertSame('123', $this->http->lastRequest()->getHeaderLine('On-Behalf-Of'));

		$this->http->push([]);
		$client->products->getAll();
		$this->assertSame('', $this->http->lastRequest()->getHeaderLine('On-Behalf-Of'));
	}

	#[Test]
	public function on_behalf_of_returns_the_same_service_type(): void
	{
		$client = $this->client();

		$this->assertInstanceOf(
			SubscriptionRequests::class,
			$client->subscriptions->onBehalfOf(1),
		);
		$this->assertNotSame($client->products, $client->products->onBehalfOf(1));
	}

	#[Test]
	public function on_behalf_of_reaches_the_nested_session_service(): void
	{
		// PaymentRequests delegates session creation to its own SessionRequests; the
		// scoped header has to survive that hop.
		$this->http->push(['uuid' => 's1', 'link' => 'https://pay']);

		$this->client()->oneTimePayments
			->onBehalfOf(456)
			->createSession(new CreatePaymentSessionDto(productId: 1));

		$this->assertSame('456', $this->http->lastRequest()->getHeaderLine('On-Behalf-Of'));
		$this->assertSame('/v1/transaction/session-checkout/new/payment', $this->http->lastPath());
	}

	#[Test]
	public function on_behalf_of_zero_returns_to_organization_level(): void
	{
		// 0 means "the organization itself": the header is omitted, which also undoes an
		// earlier onBehalfOf() on a scoped copy.
		$client = $this->client();

		$this->http->push([]);
		$client->products->onBehalfOf(0)->getAll();
		$this->assertFalse($this->http->lastRequest()->hasHeader('On-Behalf-Of'));

		$this->http->push([]);
		$client->products->onBehalfOf(7)->onBehalfOf(0)->getAll();
		$this->assertFalse($this->http->lastRequest()->hasHeader('On-Behalf-Of'));
	}

	#[Test]
	public function on_behalf_of_rejects_a_negative_user_id(): void
	{
		$this->expectException(ValidationException::class);
		$this->expectExceptionMessage('User ID must be zero or positive');

		$this->client()->products->onBehalfOf(-1);
	}

	#[Test]
	public function a_client_level_on_behalf_of_scopes_every_service(): void
	{
		$client = $this->client();
		$asUser = $client->onBehalfOf(123);

		$this->assertNotSame($client, $asUser);
		$this->assertSame($client->getBaseUrl(), $asUser->getBaseUrl());
		$this->assertSame($client->getApiKey(), $asUser->getApiKey());

		$calls = [
			fn () => $asUser->products->getAll(),
			fn () => $asUser->customers->getAll(),
			fn () => $asUser->users->get(),
			fn () => $asUser->apiKeys->getAll(),
			fn () => $asUser->oneTimePayments->createSession(new CreatePaymentSessionDto(productId: 1)),
			fn () => $asUser->subscriptions->getPaymentHistory('sub@1'),
			fn () => $asUser->transactionStatus->get('pay@1', 'payment'),
			fn () => $asUser->refunds->getAll(),
			fn () => $asUser->accounting->exportJson('2026-01-01', '2026-01-31'),
			fn () => $asUser->claims->getFunds(),
			fn () => $asUser->currencies->getAllMain(),
			fn () => $asUser->webhooks->verify('{"a":1}', 'sig', '1'),
		];

		foreach ($calls as $call) {
			$this->http->push([]);
			$call();
			$this->assertSame('123', $this->http->lastRequest()->getHeaderLine('On-Behalf-Of'));
		}

		// The original client is untouched.
		$this->http->push([]);
		$client->products->getAll();
		$this->assertFalse($this->http->lastRequest()->hasHeader('On-Behalf-Of'));

		// 0 returns to organization level; a service-level override still applies on top.
		$this->http->push([])->push([]);
		$asUser->onBehalfOf(0)->products->getAll();
		$this->assertFalse($this->http->lastRequest()->hasHeader('On-Behalf-Of'));
		$asUser->products->onBehalfOf(9)->getAll();
		$this->assertSame('9', $this->http->lastRequest()->getHeaderLine('On-Behalf-Of'));
	}

	#[Test]
	public function a_client_level_on_behalf_of_rejects_a_negative_user_id(): void
	{
		$this->expectException(ValidationException::class);
		$this->expectExceptionMessage('User ID must be zero or positive');

		$this->client()->onBehalfOf(-5);
	}

	// -------------------------------------------------------------------------
	// Retries seen from the services
	// -------------------------------------------------------------------------

	#[Test]
	public function action_gets_are_never_retried(): void
	{
		// These are GET routes, but they perform an action; replaying one could cancel,
		// bill or create a claim-fund entry twice.
		$transport = $this->transport(maxRetries: 3);
		$subscriptions = new SubscriptionRequests($transport);
		$claims = new ClaimRequests($transport);

		$actions = [
			'forceCancel' => fn () => $subscriptions->forceCancel('sub1'),
			'executeTestBilling' => fn () => $subscriptions->executeTestBilling('sub1'),
			'triggerTestClaimFunds' => fn () => $claims->triggerTestClaimFunds(42),
		];

		foreach ($actions as $action => $call) {
			$this->http->push(['error' => 'boom'], 500);

			try {
				$call();
				$this->fail("Expected {$action} to surface the 500.");
			} catch (ServerException) {
				$this->assertSame([], $this->sleeps, "{$action} must not back off and retry.");
			}

			$this->http->pushFailure();

			try {
				$call();
				$this->fail("Expected {$action} to surface the network failure.");
			} catch (NetworkException) {
				$this->assertSame([], $this->sleeps, "{$action} must not retry a network failure.");
			}
		}

		$this->assertSame(6, $this->http->requestCount(), 'One request per call, no retries.');
	}

	#[Test]
	public function ordinary_gets_are_retried(): void
	{
		$claims = new ClaimRequests($this->transport(maxRetries: 3));

		$this->http->push(['error' => 'boom'], 503)->push([]);

		$this->assertSame([], $claims->getFunds());
		$this->assertSame([1.0], $this->sleeps);
	}

	#[Test]
	public function a_zero_retry_count_from_array_config_disables_retries(): void
	{
		// `??`-style defaulting: an explicit 0 must not be replaced by the default of 3.
		$client = \QBitFlow\QBitFlow::fromArray([
			'apiKey' => 'key-123',
			'baseUrl' => 'https://api.qbitflow.app/v1',
			'maxRetries' => 0,
			'httpClient' => $this->http,
		]);

		$this->http->push([], 500);

		try {
			$client->products->getAll();
			$this->fail('Expected a ServerException.');
		} catch (ServerException) {
			$this->assertSame(1, $this->http->requestCount());
		}
	}

	// -------------------------------------------------------------------------
	// Pagination
	// -------------------------------------------------------------------------

	#[Test]
	public function a_page_exposes_its_items_cursor_and_count(): void
	{
		$this->http->push([
			'items' => [
				['uuid' => 'c1', 'name' => 'A', 'lastName' => 'One', 'email' => 'a@b.c', 'createdAt' => '2026-01-01T00:00:00Z'],
				['uuid' => 'c2', 'name' => 'B', 'lastName' => 'Two', 'email' => 'b@b.c', 'createdAt' => '2026-01-02T00:00:00Z'],
			],
			'nextCursor' => 'cursor-2',
		]);

		$page = $this->client()->customers->getAll(limit: 2);

		$this->assertInstanceOf(CursorData::class, $page);
		$this->assertCount(2, $page);
		$this->assertTrue($page->hasMore());
		$this->assertSame('cursor-2', $page->nextCursor);
		$this->assertContainsOnlyInstancesOf(Customer::class, $page->items);

		$uuids = [];
		foreach ($page as $customer) {
			$uuids[] = $customer->uuid;
		}
		$this->assertSame(['c1', 'c2'], $uuids, 'A page is iterable.');
	}

	#[Test]
	public function the_last_page_reports_no_more_results(): void
	{
		$this->http->push(['items' => [], 'nextCursor' => null]);

		$page = $this->client()->customers->getAll();

		$this->assertFalse($page->hasMore());
		$this->assertNull($page->nextCursor);
		$this->assertCount(0, $page);
	}

	#[Test]
	public function pages_can_be_walked_to_the_end(): void
	{
		$payment = static fn (string $uuid): array => [
			'uuid' => $uuid,
			'createdAt' => '2026-01-01T00:00:00Z',
			'from' => 'a',
			'to' => 'b',
		];

		$this->http
			->push(['items' => [$payment('p1')], 'nextCursor' => 'c2'])
			->push(['items' => [$payment('p2')], 'nextCursor' => null]);

		$client = $this->client();
		$seen = [];
		$cursor = null;

		do {
			$page = $client->oneTimePayments->getAll(limit: 1, cursor: $cursor);

			foreach ($page as $settled) {
				$seen[] = $settled->uuid;
			}

			$cursor = $page->nextCursor;
		} while ($page->hasMore());

		$this->assertSame(['p1', 'p2'], $seen);
		$this->assertSame('/v1/transaction/payments?limit=1&cursor=c2', $this->http->lastPath());
	}

	// -------------------------------------------------------------------------
	// Webhook verification
	// -------------------------------------------------------------------------

	#[Test]
	public function verification_fails_quietly_when_the_api_rejects_the_signature(): void
	{
		$this->http->push(['error' => 'Invalid signature'], 400);

		$this->assertFalse($this->client()->webhooks->verify('{"a":1}', 'bad-sig', '123'));
	}

	#[Test]
	public function verification_rethrows_an_outage_rather_than_calling_it_a_forgery(): void
	{
		// A network failure must not be reported as "invalid signature" — that would make
		// an outage indistinguishable from an attack.
		$this->http->pushFailure();

		$this->expectException(NetworkException::class);

		$this->client()->webhooks->verify('{"a":1}', 'sig', '123');
	}

	#[Test]
	public function verification_rethrows_a_server_error(): void
	{
		$this->http->push([], 500);

		$this->expectException(ServerException::class);

		$this->client()->webhooks->verify('{"a":1}', 'sig', '123');
	}

	#[Test]
	public function verification_rejects_a_payload_that_is_not_json(): void
	{
		$this->expectException(ValidationException::class);
		$this->expectExceptionMessage('webhook payload is not valid JSON');

		$this->client()->webhooks->verify('{not json', 'sig', '123');
	}

	#[Test]
	public function verification_accepts_an_already_decoded_payload(): void
	{
		$this->http->push(['message' => 'ok']);

		$this->assertTrue($this->client()->webhooks->verify(['uuid' => 's1'], 'sig', '123'));
		$this->assertSame(['uuid' => 's1'], $this->http->lastBody()['payload']);
	}

	#[Test]
	public function it_exposes_one_set_of_webhook_header_names_and_the_test_id(): void
	{
		$webhooks = $this->client()->webhooks;

		$this->assertSame('X-Webhook-Signature-256', $webhooks->signatureHeader());
		$this->assertSame('X-Webhook-Timestamp', $webhooks->timestampHeader());
		$this->assertSame('X-Webhook-Id', $webhooks->webhookIdHeader());
		$this->assertSame('test-webhook-id', $webhooks->testWebhookId());

		// One definition, referenced from both entry points.
		$this->assertSame(WebhookVerifier::HEADER_WEBHOOK_ID, WebhookRequests::HEADER_WEBHOOK_ID);
		$this->assertSame(WebhookVerifier::HEADER_SIGNATURE, WebhookRequests::HEADER_SIGNATURE);
		$this->assertSame(WebhookVerifier::HEADER_TIMESTAMP, WebhookRequests::HEADER_TIMESTAMP);
		$this->assertSame(WebhookVerifier::TEST_WEBHOOK_ID, WebhookRequests::TEST_WEBHOOK_ID);

		$this->assertTrue($webhooks->isTestWebhook(WebhookRequests::TEST_WEBHOOK_ID));
		$this->assertFalse($webhooks->isTestWebhook('evt_real'));
		$this->assertFalse($webhooks->isTestWebhook(null));
	}

	// -------------------------------------------------------------------------
	// Client construction
	// -------------------------------------------------------------------------

	#[Test]
	public function it_exposes_every_service_as_both_a_property_and_a_method(): void
	{
		$client = $this->client();

		foreach ([
			'customers', 'products', 'users', 'apiKeys', 'webhooks', 'oneTimePayments',
			'subscriptions', 'transactionStatus', 'refunds', 'accounting', 'claims', 'currencies',
		] as $service) {
			$this->assertSame(
				$client->{$service},
				$client->{$service}(),
				sprintf('%s() should return the same instance as ->%s', $service, $service),
			);
		}
	}

	#[Test]
	public function a_blank_base_url_falls_back_to_the_default(): void
	{
		$fromArray = \QBitFlow\QBitFlow::fromArray(['apiKey' => 'k', 'baseUrl' => '', 'httpClient' => $this->http]);
		$direct = new \QBitFlow\QBitFlow('k', baseUrl: '  ', httpClient: $this->http);

		$this->assertSame(\QBitFlow\Config::baseUrl(), $fromArray->getBaseUrl());
		$this->assertSame(\QBitFlow\Config::baseUrl(), $direct->getBaseUrl());
	}

	#[Test]
	public function from_array_passes_the_psr17_factories_through(): void
	{
		$factory = new \Nyholm\Psr7\Factory\Psr17Factory();
		$client = \QBitFlow\QBitFlow::fromArray([
			'apiKey' => 'k',
			'baseUrl' => 'https://x.test/v1',
			'httpClient' => $this->http,
			'requestFactory' => $factory,
			'streamFactory' => $factory,
		]);

		$this->http->push([]);
		$client->products->getAll();

		$this->assertInstanceOf(\Nyholm\Psr7\Request::class, $this->http->lastRequest());
	}

	#[Test]
	public function it_builds_a_client_from_an_array_of_options(): void
	{
		$client = \QBitFlow\QBitFlow::fromArray([
			'apiKey' => 'key-123',
			'baseUrl' => 'https://staging.qbitflow.app/v1/',
			'timeout' => 5,
			'maxRetries' => 1,
			'httpClient' => $this->http,
		]);

		$this->assertSame('key-123', $client->getApiKey());
		$this->assertSame('https://staging.qbitflow.app/v1', $client->getBaseUrl(), 'Trailing slash is trimmed.');
	}

	#[Test]
	public function it_never_leaks_the_api_key_through_debug_output(): void
	{
		$dump = print_r($this->client('sk_live_supersecret_9999'), true);

		$this->assertStringNotContainsString('supersecret', $dump);
		$this->assertStringContainsString('***9999', $dump);
	}
}
