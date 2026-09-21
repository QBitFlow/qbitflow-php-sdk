<?php

declare(strict_types=1);

namespace QBitFlow\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use QBitFlow\Dto\Customer;
use QBitFlow\Dto\Session\CreatePaymentSessionDto;
use QBitFlow\Exceptions\NetworkException;
use QBitFlow\Exceptions\ServerException;
use QBitFlow\Exceptions\ValidationException;
use QBitFlow\Requests\WebhookRequests;
use QBitFlow\Support\CursorData;
use QBitFlow\Tests\Support\TestCase;

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
			\QBitFlow\Requests\SubscriptionRequests::class,
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
	public function on_behalf_of_rejects_a_non_positive_user_id(): void
	{
		$this->expectException(ValidationException::class);
		$this->expectExceptionMessage('User ID must be positive');

		$this->client()->products->onBehalfOf(0);
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
		$this->http
			->push(['items' => [['uuid' => 'p1', 'from' => 'a', 'to' => 'b']], 'nextCursor' => 'c2'])
			->push(['items' => [['uuid' => 'p2', 'from' => 'a', 'to' => 'b']], 'nextCursor' => null]);

		$client = $this->client();
		$seen = [];
		$cursor = null;

		do {
			$page = $client->oneTimePayments->getAll(limit: 1, cursor: $cursor);

			foreach ($page as $payment) {
				$seen[] = $payment->uuid;
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
		$this->expectExceptionMessage('Webhook payload is not valid JSON');

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
	public function it_exposes_the_webhook_header_names_and_test_id(): void
	{
		$webhooks = $this->client()->webhooks;

		$this->assertSame('X-Webhook-Signature-256', $webhooks->signatureHeader());
		$this->assertSame('X-Webhook-Timestamp', $webhooks->timestampHeader());
		$this->assertSame('X-Webhook-ID', $webhooks->webhookIdHeader());
		$this->assertSame('test-webhook-id', $webhooks->testWebhookId());

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
