<?php

declare(strict_types=1);

namespace QBitFlow\Tests\Integration;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use QBitFlow\Enums\CheckoutSessionStatusValue;
use QBitFlow\Enums\EventType;
use QBitFlow\Enums\Role;
use QBitFlow\Events\Event;
use QBitFlow\Exceptions\ConflictException;
use QBitFlow\Exceptions\NotFoundException;
use QBitFlow\Models\Me;
use QBitFlow\Params;
use QBitFlow\QBitFlow;
use QBitFlow\Webhooks\Webhook;

/**
 * Live checks against a QBitFlow API (the Go SDK's integration suite). Never part of an
 * offline run:
 *
 * - `QBITFLOW_API_KEY` not set → skipped;
 * - `QBITFLOW_API_KEY` set without `QBITFLOW_BASE_URL` → FAILS (never aim at production by accident);
 * - the read-only checks only read; the write checks also need `QBITFLOW_LIVE_WRITES=1` and a
 *   test-mode key (or `QBITFLOW_ALLOW_LIVE_MODE_WRITES=1`). Each write is undone (deleted or expired).
 *
 * ```bash
 * QBITFLOW_API_KEY=sk_… QBITFLOW_BASE_URL=https://… vendor/bin/phpunit --testsuite Integration
 * ```
 */
#[Group('integration')]
final class LiveApiTest extends TestCase
{
	private QBitFlow $client;

	private Me $me;

	/** @var list<\Closure(): void> */
	private array $cleanups = [];

	protected function setUp(): void
	{
		parent::setUp();
		$key = getenv('QBITFLOW_API_KEY');
		$base = getenv('QBITFLOW_BASE_URL');
		if (! is_string($key) || trim($key) === '') {
			$this->markTestSkipped('QBITFLOW_API_KEY not set: live checks skipped');
		}
		if (! is_string($base) || trim($base) === '') {
			$this->fail('QBITFLOW_API_KEY is set but QBITFLOW_BASE_URL is not: set the API\'s base URL explicitly');
		}
		$this->client = new QBitFlow(apiKey: $key, baseUrl: $base, timeout: 30.0);
		$this->me = $this->client->me();
	}

	protected function tearDown(): void
	{
		foreach (array_reverse($this->cleanups) as $cleanup) {
			try {
				$cleanup();
			} catch (NotFoundException) {
				// already gone
			}
		}
		parent::tearDown();
	}

	private function isOrganizationKey(): bool
	{
		return $this->me->role === Role::ADMIN && $this->me->onBehalfOf === null;
	}

	private function requireWrites(): void
	{
		if (getenv('QBITFLOW_LIVE_WRITES') !== '1') {
			$this->markTestSkipped('QBITFLOW_LIVE_WRITES=1 not set: write checks skipped');
		}
		if (! ($this->me->space?->test ?? false) && getenv('QBITFLOW_ALLOW_LIVE_MODE_WRITES') !== '1') {
			$this->markTestSkipped('write checks run on a test-mode key only (QBITFLOW_ALLOW_LIVE_MODE_WRITES=1 overrides)');
		}
	}

	private static function suffix(): string
	{
		return (string) hrtime(true);
	}

	#[Test]
	public function me_describes_the_key(): void
	{
		$this->assertNotNull($this->me->space);
		$this->assertNotSame('', $this->me->space->uuid);
	}

	#[Test]
	public function read_only_routes_answer(): void
	{
		$c = $this->client;
		$c->products->list(new Params\ProductListParams(includeHidden: true));

		$page = $c->customers->list(new Params\CustomerListParams(limit: 2));
		$this->assertLessThanOrEqual(2, count($page->items));
		$n = 0;
		foreach ($c->customers->iterate(new Params\CustomerListParams(limit: 2)) as $customer) {
			$this->assertNotSame('', $customer->uuid);
			if (++$n === 3) {
				break;
			}
		}

		$c->payments->list(new Params\PaymentListParams(limit: 5));
		$c->payments->listCombined(new Params\CombinedPaymentListParams(limit: 5, createdAfter: new DateTimeImmutable('-30 days')));
		$c->failures->list(new Params\FailureListParams(limit: 5));
		$c->subscriptions->list(new Params\SubscriptionListParams(limit: 5));
		$c->refunds->list();
		$c->refunds->listInactive(new Params\RefundListParams(limit: 5));
		if ($this->isOrganizationKey()) {
			$c->members->list(new Params\MemberListParams(limit: 5));
			$c->members->listHeldFunds();
			$c->invitations->list(new Params\InvitationListParams(limit: 5));
		}
		$c->wallets->list(new Params\WalletListParams(withBalances: true));
		$c->wallets->listSupportedCurrencies();

		$currencies = $c->currencies->listAvailable(new Params\CurrencyListParams(test: $this->me->space?->test ?? false));
		$c->currencies->listMain();
		if ($currencies !== []) {
			$this->assertSame($currencies[0]->id, $c->currencies->get($currencies[0]->id)->id);
		}

		$to = new DateTimeImmutable('now');
		$from = $to->modify('-30 days');
		$c->accounting->exportJson($from->format('Y-m-d'), $to->format('Y-m-d'));
		$this->assertNotSame('', $c->accounting->exportCsv($from->format('Y-m-d'), $to->format('Y-m-d')), 'a header line is expected');

		$c->webhooks->endpoints->list();
		$events = $c->webhooks->events->list(new Params\EventListParams(limit: 5));
		if ($events->items !== []) {
			$c->webhooks->events->get($events->items[0]->id);
		}

		try {
			$c->checkoutSessions->getStatus('pay@019eca82-5680-7b00-8000-00000000dead');
			$this->fail('want a NotFoundException');
		} catch (NotFoundException) {
			$this->addToAssertionCount(1);
		}
	}

	#[Test]
	public function the_integration_helpers_work_against_the_api(): void
	{
		$c = $this->client;

		// H6: a range over 95 days is split (two requests here) and concatenated.
		$to = new DateTimeImmutable('now', new DateTimeZone('UTC'));
		$from = $to->modify('-120 days');
		$c->accounting->exportJsonRange($from->format('Y-m-d'), $to->format('Y-m-d'));
		$csv = $c->accounting->exportCsvRange($from->format('Y-m-d'), $to->format('Y-m-d'));
		$this->assertNotSame('', $csv, 'a header line is expected');

		// H1 smoke: an event of the log, exactly as the API serializes it, signed locally, goes
		// through the router to its typed handler.
		$raw = new class () implements ClientInterface {
			public string $lastBody = '';

			private ClientInterface $inner;

			public function __construct()
			{
				$this->inner = new \GuzzleHttp\Client(['http_errors' => false, 'allow_redirects' => false, 'timeout' => 30]);
			}

			public function sendRequest(RequestInterface $request): ResponseInterface
			{
				$response = $this->inner->sendRequest($request);
				$this->lastBody = (string) $response->getBody();

				return $response;
			}
		};
		$recording = new QBitFlow(apiKey: (string) getenv('QBITFLOW_API_KEY'), baseUrl: (string) getenv('QBITFLOW_BASE_URL'), httpClient: $raw);
		$page = $recording->webhooks->events->list(new Params\EventListParams(limit: 1));
		if ($page->items === []) {
			$this->markTestSkipped('the event log is empty: no event to route');
		}
		$wire = json_decode($raw->lastBody, false, 512, JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING);
		$body = json_encode($wire->items[0], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
		$secret = 'whsec_live_smoke_' . self::suffix();
		$seen = [];
		$router = $c->webhooks->router($secret)
			->onUnknown(static function (Event $e) use (&$seen): void {
				$seen[] = 'unknown';
			})
			->onAny(static function (Event $e) use (&$seen): void {
				$seen[] = $e->id;
			});
		foreach (EventType::values() as $type) {
			$router->on($type, static function (mixed $data, Event $e) use (&$seen): void {
				$seen[] = is_object($data) ? $data::class : get_debug_type($data);
			});
		}
		$result = $router->handle($body, Webhook::sign($body, $secret));
		$this->assertSame(200, $result->status, (string) $result->error?->getMessage());
		$this->assertSame($page->items[0]->id, $result->event?->id);
		$this->assertCount(2, $seen);
		$this->assertSame($page->items[0]->id, $seen[1]);
		$this->assertSame(400, $router->handle($body, Webhook::sign($body, 'whsec_other'))->status);
	}

	#[Test]
	public function writes_a_product(): void
	{
		$this->requireWrites();
		$ref = 'sdk-php-test-' . self::suffix();
		$product = $this->client->products->create(new Params\CreateProductParams(name: 'SDK PHP test', price: 1, reference: $ref));
		$this->cleanups[] = fn () => $this->client->products->delete($product->uuid);

		$this->assertSame($ref, $this->client->products->get($product->uuid)->reference);
		$this->assertSame($product->uuid, $this->client->products->getByReference($ref)->uuid);
		$updated = $this->client->products->update($product->uuid, new Params\UpdateProductParams(description: 'Updated by the PHP SDK', price: 2.0));
		$this->assertSame(2.0, $updated->price);
		$this->client->products->delete($product->uuid);
		$this->expectException(NotFoundException::class);
		$this->client->products->get($product->uuid);
	}

	#[Test]
	public function writes_a_customer(): void
	{
		$this->requireWrites();
		$s = self::suffix();
		$customer = $this->client->customers->create(new Params\CreateCustomerParams(name: 'Ada', email: "sdk-php-test-$s@example.com",
			lastName: 'Test', phoneNumber: '+33 6 12 34 56 78', reference: 'sdk-php-' . $s));
		$this->cleanups[] = fn () => $this->client->customers->delete($customer->uuid);
		$this->assertNotNull($customer->phoneNumber);

		$updated = $this->client->customers->update($customer->uuid, new Params\UpdateCustomerParams(phoneNumber: ''));
		$this->assertContains($updated->phoneNumber, [null, ''], 'cleared');
		$this->assertSame($customer->email, $updated->email);
	}

	#[Test]
	public function writes_a_checkout_session(): void
	{
		$this->requireWrites();
		try {
			$session = $this->client->checkoutSessions->createPayment(new Params\CreatePaymentSessionParams(productName: 'SDK PHP test', price: 1,
				reference: 'sdk-php-order-' . self::suffix(), successUrl: 'https://example.com/ok?id={{UUID}}', cancelUrl: 'https://example.com/cancel'));
		} catch (ConflictException $e) {
			if ($e->apiCode === 'merchant_not_ready') {
				$this->markTestSkipped('the space accepts no currency (merchant_not_ready)');
			}

			throw $e;
		}
		$this->assertNotSame('', $session->link);
		$this->assertNotNull($session->expiresAt);

		$this->assertSame(CheckoutSessionStatusValue::CREATED, $this->client->checkoutSessions->getStatus($session->uuid)->status);
		$this->assertSame(CheckoutSessionStatusValue::CREATED, $this->client->checkoutSessions->waitForCompletion($session->uuid, timeout: 1, interval: 1)->status, 'H3: the last status seen');
		$this->assertSame(CheckoutSessionStatusValue::EXPIRED, $this->client->checkoutSessions->expire($session->uuid)->status);
	}

	#[Test]
	public function writes_a_checkout_session_with_fees(): void
	{
		$this->requireWrites();
		// The catalog's feesCheckout values (3.99 + 0.75 + the processing fee stays under test mode's $5 cap).
		try {
			$session = $this->client->checkoutSessions->createPayment(new Params\CreatePaymentSessionParams(productName: 'T-shirt', description: 'Blue, size M',
				price: 3.99, reference: 'order-1044-' . self::suffix(), successUrl: 'https://shop.example.com/orders/success?uuid={{UUID}}',
				cancelUrl: 'https://shop.example.com/orders/cancel', fees: new Params\CheckoutFees(processingFee: true, items: [
					new Params\FeeItem(label: 'Shipping', amountUsd: 0.75, description: 'Standard, 3 to 5 days'),
				])));
		} catch (ConflictException $e) {
			if ($e->apiCode === 'merchant_not_ready') {
				$this->markTestSkipped('the space accepts no currency (merchant_not_ready)');
			}

			throw $e;
		}
		$this->cleanups[] = fn () => $this->client->checkoutSessions->expire($session->uuid);
		$this->assertNotSame('', $session->link);
		$this->assertStringStartsWith('pay@', $session->uuid);

		// No API-key route reads a session's fees or amount back (the checkout page's read is frontend-only): the round trip only.
		$this->assertSame(CheckoutSessionStatusValue::CREATED, $this->client->checkoutSessions->getStatus($session->uuid)->status);
	}

	#[Test]
	public function writes_a_webhook_endpoint(): void
	{
		$this->requireWrites();
		$created = $this->client->webhooks->endpoints->create(new Params\CreateWebhookEndpointParams(url: 'https://example.com/qbitflow-sdk-test',
			events: [EventType::PAYMENT_COMPLETED], description: 'PHP SDK test ' . self::suffix()));
		$this->cleanups[] = fn () => $this->client->webhooks->endpoints->delete($created->uuid);
		$this->assertNotSame('', $created->secret);

		$this->assertSame($created->url, $this->client->webhooks->endpoints->get($created->uuid)->url);
		$updated = $this->client->webhooks->endpoints->update($created->uuid, new Params\UpdateWebhookEndpointParams(description: '', enabled: false));
		$this->assertSame('', $updated->description);
		$this->assertNotNull($updated->disabledAt);
	}
}
