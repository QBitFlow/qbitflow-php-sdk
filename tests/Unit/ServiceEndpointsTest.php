<?php

declare(strict_types=1);

namespace QBitFlow\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use QBitFlow\Dto\CreateCustomerDto;
use QBitFlow\Dto\CreateProductDto;
use QBitFlow\Dto\CreateUserDto;
use QBitFlow\Dto\Session\CreatePaymentSessionDto;
use QBitFlow\Dto\Session\CreateSubscriptionSessionDto;
use QBitFlow\Dto\SuccessResponse;
use QBitFlow\Dto\UpdateCustomerDto;
use QBitFlow\Dto\UpdateProductDto;
use QBitFlow\Dto\UpdateUserDto;
use QBitFlow\Enums\TransactionType;
use QBitFlow\Enums\UserRole;
use QBitFlow\Exceptions\ConflictException;
use QBitFlow\Exceptions\ForbiddenException;
use QBitFlow\Exceptions\UnauthorizedException;
use QBitFlow\Support\Duration;
use QBitFlow\Tests\Support\TestCase;

/**
 * Pins every service method to the HTTP method, path and payload the REST API documents.
 *
 * Fixtures carry only a few fields: anything absent hydrates to its zero value.
 */
final class ServiceEndpointsTest extends TestCase
{
	private const AT = '2026-01-01T00:00:00Z';

	private const CUSTOMER_UUID = '01997c89-d0e9-7c9a-9886-fe7709919695';

	/** @return array<string,mixed> */
	private static function customer(string $uuid = 'c1'): array
	{
		return ['uuid' => $uuid, 'createdAt' => self::AT];
	}

	/** @return array<string,mixed> */
	private static function product(int $id = 1): array
	{
		return ['id' => $id, 'createdAt' => self::AT];
	}

	/** @return array<string,mixed> */
	private static function user(int $id = 1): array
	{
		return ['id' => $id, 'createdAt' => self::AT, 'updatedAt' => self::AT];
	}

	/** @return array<string,mixed> */
	private static function payment(string $uuid = 'p1'): array
	{
		return ['uuid' => $uuid, 'createdAt' => self::AT];
	}

	/** @return array<string,mixed> */
	private static function subscription(string $uuid = 'sub1'): array
	{
		return ['uuid' => $uuid, 'createdAt' => self::AT, 'updatedAt' => self::AT];
	}

	// -------------------------------------------------------------------------
	// Customers
	// -------------------------------------------------------------------------

	#[Test]
	public function customer_endpoints(): void
	{
		$client = $this->client();

		$this->http->push(self::customer());
		$client->customers->create(new CreateCustomerDto('John', 'Doe', 'john@example.com'));
		$this->assertSame('POST', $this->http->lastMethod());
		$this->assertSame('/v1/customer/', $this->http->lastPath());
		$this->assertSame([
			'name' => 'John',
			'lastName' => 'Doe',
			'email' => 'john@example.com',
		], $this->http->lastBody());

		$this->http->push(self::customer());
		$client->customers->get('c1');
		$this->assertSame('/v1/customer/uuid/c1', $this->http->lastPath());

		$this->http->push(self::customer());
		$client->customers->getByReference('CRM/12345');
		$this->assertSame('/v1/customer/reference/CRM%2F12345', $this->http->lastPath());

		$this->http->push(self::customer());
		$client->customers->getByEmail('john+tag@example.com');
		$this->assertSame('/v1/customer/email/john%2Btag%40example.com', $this->http->lastPath());

		$this->http->push(['items' => [], 'nextCursor' => null]);
		$client->customers->getAll(limit: 25, cursor: 'abc');
		$this->assertSame('/v1/customer/all?limit=25&cursor=abc', $this->http->lastPath());

		$this->http->push(self::customer());
		$client->customers->update('c1', new UpdateCustomerDto(email: 'new@example.com'));
		$this->assertSame('PUT', $this->http->lastMethod());
		$this->assertSame('/v1/customer/c1', $this->http->lastPath());
		$this->assertSame(['email' => 'new@example.com'], $this->http->lastBody());

		$this->http->push(['message' => 'deleted']);
		$client->customers->delete('c1');
		$this->assertSame('DELETE', $this->http->lastMethod());
		$this->assertSame('/v1/customer/uuid/c1', $this->http->lastPath());
	}

	// -------------------------------------------------------------------------
	// Products
	// -------------------------------------------------------------------------

	#[Test]
	public function product_endpoints(): void
	{
		$client = $this->client();

		$this->http->push(self::product());
		$client->products->create(new CreateProductDto('Widget', 'A widget', 9.99, 'PROD-1'));
		$this->assertSame('POST', $this->http->lastMethod());
		$this->assertSame('/v1/product/', $this->http->lastPath());
		$this->assertSame([
			'name' => 'Widget',
			'description' => 'A widget',
			'price' => 9.99,
			'reference' => 'PROD-1',
		], $this->http->lastBody());

		$this->http->push(self::product());
		$client->products->get(1);
		$this->assertSame('/v1/product/id/1', $this->http->lastPath());

		$this->http->push([self::product(1), self::product(2)]);
		$this->assertCount(2, $client->products->getAll());
		$this->assertSame('/v1/product/', $this->http->lastPath());

		$this->http->push(self::product());
		$client->products->getByReference('PROD-1');
		$this->assertSame('/v1/product/reference/PROD-1', $this->http->lastPath());

		$this->http->push(self::product());
		$client->products->update(1, new UpdateProductDto('Widget', 'Updated', 19.99));
		$this->assertSame('PUT', $this->http->lastMethod());
		$this->assertSame('/v1/product/1', $this->http->lastPath());

		$this->http->push(['message' => 'deleted']);
		$client->products->delete(1);
		$this->assertSame('DELETE', $this->http->lastMethod());
		$this->assertSame('/v1/product/1', $this->http->lastPath());
	}

	// -------------------------------------------------------------------------
	// Users and API keys
	// -------------------------------------------------------------------------

	#[Test]
	public function user_endpoints(): void
	{
		$client = $this->client();

		$this->http->push(self::user());
		$client->users->create(new CreateUserDto('Jane', 'Smith', 'jane@example.com', UserRole::ADMIN, 100));
		$this->assertSame('/v1/user/', $this->http->lastPath());
		$this->assertSame([
			'name' => 'Jane',
			'lastName' => 'Smith',
			'email' => 'jane@example.com',
			'role' => 'admin',
			'organizationFeeBps' => 100,
		], $this->http->lastBody());

		$this->http->push(self::user());
		$client->users->get();
		$this->assertSame('/v1/user/', $this->http->lastPath());
		$this->assertSame('GET', $this->http->lastMethod());

		$this->http->push([self::user()]);
		$client->users->getAll();
		$this->assertSame('/v1/user/all', $this->http->lastPath());

		$this->http->push(self::user(5));
		$client->users->getById(5);
		$this->assertSame('/v1/user/id/5', $this->http->lastPath());

		$this->http->push(self::user(5));
		$client->users->getByEmail('jane@example.com');
		$this->assertSame('/v1/user/email/jane%40example.com', $this->http->lastPath());

		$this->http->push(self::user(5));
		$client->users->update(5, new UpdateUserDto('Jane', 'Doe', 'jane@example.com', organizationFeeBps: 150));
		$this->assertSame('PUT', $this->http->lastMethod());
		$this->assertSame('/v1/user/5', $this->http->lastPath());

		$this->http->push(['message' => 'deleted']);
		$client->users->delete(5);
		$this->assertSame('DELETE', $this->http->lastMethod());
		$this->assertSame('/v1/user/5', $this->http->lastPath());
	}

	#[Test]
	public function api_key_endpoints(): void
	{
		$client = $this->client();

		$this->http->push([]);
		$client->apiKeys->getAll();
		$this->assertSame('/v1/api-key/', $this->http->lastPath());

		$this->http->push([]);
		$client->apiKeys->getForUser(42);
		$this->assertSame('/v1/api-key/user/42', $this->http->lastPath());
	}

	// -------------------------------------------------------------------------
	// Payments and sessions
	// -------------------------------------------------------------------------

	#[Test]
	public function payment_endpoints(): void
	{
		$client = $this->client();

		$this->http->push(['uuid' => 's1', 'link' => 'https://pay.qbitflow.app/s1']);
		$link = $client->oneTimePayments->createSession(new CreatePaymentSessionDto(
			reference: 'order-1234',
			productId: 1,
			customerUUID: self::CUSTOMER_UUID,
			successUrl: 'https://example.com/ok',
		));
		$this->assertSame('POST', $this->http->lastMethod());
		$this->assertSame('/v1/transaction/session-checkout/new/payment', $this->http->lastPath());
		$this->assertSame([
			'reference' => 'order-1234',
			'productId' => 1,
			'successUrl' => 'https://example.com/ok',
			'customerUUID' => self::CUSTOMER_UUID,
		], $this->http->lastBody());
		$this->assertSame('https://pay.qbitflow.app/s1', $link->link);

		$this->http->push(['uuid' => 's1']);
		$client->oneTimePayments->getSession('s1');
		$this->assertSame('/v1/transaction/session-checkout/s1', $this->http->lastPath());

		$this->http->push(['uuid' => 's1']);
		$client->oneTimePayments->getSession('s1', closeToExpireError: false);
		$this->assertSame('/v1/transaction/session-checkout/s1?closeToExpireError=false', $this->http->lastPath());

		$this->http->push(self::payment());
		$client->oneTimePayments->get('p1');
		$this->assertSame('/v1/transaction/payment/p1', $this->http->lastPath());

		$this->http->push(self::payment());
		$client->oneTimePayments->getByReference('order 1234');
		$this->assertSame('/v1/transaction/payment/reference/order%201234', $this->http->lastPath());

		$this->http->push(['items' => [], 'nextCursor' => null]);
		$client->oneTimePayments->getAll(limit: 10);
		$this->assertSame('/v1/transaction/payments?limit=10', $this->http->lastPath());

		$this->http->push(['items' => [], 'nextCursor' => null]);
		$client->oneTimePayments->getAllCombined(limit: 20, cursor: 'cur');
		$this->assertSame('/v1/transaction/payments/combined?limit=20&cursor=cur', $this->http->lastPath());

		$this->http->push(self::customer());
		$client->oneTimePayments->getCustomerForTransaction('tx1');
		$this->assertSame('/v1/transaction/customer/tx1', $this->http->lastPath());
	}

	// -------------------------------------------------------------------------
	// Subscriptions
	// -------------------------------------------------------------------------

	#[Test]
	public function subscription_endpoints(): void
	{
		$client = $this->client();

		$this->http->push(['uuid' => 's1', 'link' => 'https://pay.qbitflow.app/s1']);
		$client->subscriptions->createSession(new CreateSubscriptionSessionDto(
			frequency: Duration::months(1),
			productId: 1,
			trialPeriod: Duration::days(7),
			minPeriods: 3,
			customerUUID: self::CUSTOMER_UUID,
		));
		$this->assertSame('/v1/transaction/session-checkout/new/subscription', $this->http->lastPath());
		$this->assertSame([
			'frequency' => ['value' => 1, 'unit' => 'months'],
			'trialPeriod' => ['value' => 7, 'unit' => 'days'],
			'minPeriods' => 3,
			'productId' => 1,
			'customerUUID' => self::CUSTOMER_UUID,
		], $this->http->lastBody());

		$this->http->push(['uuid' => 's1', 'txType' => 'createSubscription', 'frequency' => 2592000]);
		$session = $client->subscriptions->getSession('s1');
		$this->assertSame('/v1/transaction/session-checkout/s1', $this->http->lastPath());
		$this->assertInstanceOf(\QBitFlow\Dto\Session\SubscriptionSession::class, $session);

		$this->http->push(self::subscription());
		$client->subscriptions->get('sub1');
		$this->assertSame('/v1/transaction/subscription/sub1', $this->http->lastPath());

		$this->http->push(self::subscription());
		$client->subscriptions->getByReference('sub-1234');
		$this->assertSame('/v1/transaction/subscription/reference/subscription/sub-1234', $this->http->lastPath());

		$this->http->push([]);
		$client->subscriptions->getPaymentHistory('sub1');
		$this->assertSame('/v1/transaction/subscription/history/sub1', $this->http->lastPath());

		$this->http->push(['message' => 'Subscription cancelled successfully']);
		$cancelled = $client->subscriptions->forceCancel('sub1');
		$this->assertSame('GET', $this->http->lastMethod());
		$this->assertSame('/v1/transaction/subscription/processing/force-cancel/sub1', $this->http->lastPath());
		$this->assertInstanceOf(SuccessResponse::class, $cancelled);

		// The API answers execute-billing with a plain JSONMessage.
		$this->http->push(['message' => 'Billing executed successfully']);
		$billed = $client->subscriptions->executeTestBilling('sub1');
		$this->assertSame('/v1/transaction/subscription/processing/execute-billing/sub1', $this->http->lastPath());
		$this->assertInstanceOf(SuccessResponse::class, $billed);
		$this->assertSame('Billing executed successfully', $billed->message);
	}

	#[Test]
	public function execute_billing_before_the_due_date_is_a_conflict(): void
	{
		$this->http->push(['error' => 'Subscription is not due for billing yet'], 409);

		$this->expectException(ConflictException::class);
		$this->expectExceptionMessage('not due for billing');

		$this->client()->subscriptions->executeTestBilling('sub1');
	}

	// -------------------------------------------------------------------------
	// Status, refunds, accounting, claims, currencies, webhooks
	// -------------------------------------------------------------------------

	#[Test]
	public function transaction_status_endpoint(): void
	{
		$this->http->push(['status' => 'completed', 'txHash' => '0xabc']);

		$status = $this->client()->transactionStatus->get('tx1', TransactionType::ONE_TIME_PAYMENT);

		$this->assertSame('/v1/transaction/status?txUUID=tx1&txType=payment', $this->http->lastPath());
		$this->assertTrue($status->isCompleted());
	}

	#[Test]
	public function refund_endpoints(): void
	{
		$client = $this->client();

		$this->http->push(['uuid' => 'r1', 'createdAt' => self::AT]);
		$client->refunds->getByTransaction('tx1');
		$this->assertSame('/v1/transaction/refunds/by-transaction/tx1', $this->http->lastPath());

		$this->http->push([]);
		$client->refunds->getAll();
		$this->assertSame('/v1/transaction/refunds/all', $this->http->lastPath());

		$this->http->push(['items' => [], 'nextCursor' => null]);
		$client->refunds->getAllInactive(limit: 20);
		$this->assertSame('/v1/transaction/refunds/all/inactive?limit=20', $this->http->lastPath());
	}

	#[Test]
	public function accounting_endpoints(): void
	{
		$client = $this->client();

		$this->http->push([['paymentId' => 'pay_1', 'txTimeUtc' => self::AT]]);
		$events = $client->accounting->exportJson('2026-01-01', '2026-01-31');
		$this->assertSame(
			'/v1/accounting/export?from=2026-01-01&to=2026-01-31&format=json',
			$this->http->lastPath(),
		);
		$this->assertCount(1, $events);
		$this->assertSame('pay_1', $events[0]->paymentId);

		$csv = "paymentId,amount\npay_1,10.00\n";
		$this->http->pushRaw($csv);
		$this->assertSame($csv, $client->accounting->exportCsv('2026-01-01', '2026-01-31'));
		$this->assertSame(
			'/v1/accounting/export?from=2026-01-01&to=2026-01-31&format=csv',
			$this->http->lastPath(),
		);
	}

	#[Test]
	public function claim_endpoints(): void
	{
		$client = $this->client();

		$this->http->push(['message' => 'ok', 'link' => 'https://claim']);
		$client->claims->createRequest(42);
		$this->assertSame('POST', $this->http->lastMethod());
		$this->assertSame('/v1/user/claim/request', $this->http->lastPath());
		$this->assertSame(['userId' => 42], $this->http->lastBody());

		$this->http->push(['message' => 'ok', 'link' => 'https://claim']);
		$client->claims->getRequestByUser(42);
		$this->assertSame('/v1/user/claim/request/42', $this->http->lastPath());

		$this->http->push([]);
		$client->claims->getFunds();
		$this->assertSame('/v1/user/claim/funds', $this->http->lastPath());

		// The REST reference documents the user ID as a path segment.
		$this->http->push(['message' => 'triggered']);
		$client->claims->triggerTestClaimFunds(42);
		$this->assertSame('/v1/user/claim/funds/test-trigger/42', $this->http->lastPath());
	}

	#[Test]
	public function the_transaction_status_accepts_a_raw_type_string(): void
	{
		$this->http->push(['status' => 'pending']);

		$this->client()->transactionStatus->get('tx1', 'claimFunds');

		$this->assertSame('/v1/transaction/status?txUUID=tx1&txType=claimFunds', $this->http->lastPath());
	}

	#[Test]
	public function currency_endpoints(): void
	{
		$client = $this->client();

		$this->http->push([]);
		$client->currencies->getAllAvailable();
		$this->assertSame('/v1/utils/all-available-currencies?test=false', $this->http->lastPath());

		$this->http->push([]);
		$client->currencies->getAllMain(true);
		$this->assertSame('/v1/utils/all-main-currencies?test=true', $this->http->lastPath());
	}

	#[Test]
	public function webhook_verify_endpoint(): void
	{
		$this->http->push(['message' => 'verified']);

		$verified = $this->client()->webhooks->verify('{"uuid":"s1"}', 'sig', '1700000000');

		$this->assertTrue($verified);
		$this->assertSame('POST', $this->http->lastMethod());
		$this->assertSame('/v1/webhooks/verify', $this->http->lastPath());
		$this->assertSame([
			'payload' => ['uuid' => 's1'],
			'receivedSignature' => 'sig',
			'receivedTimestamp' => '1700000000',
		], $this->http->lastBody());
	}

	#[Test]
	public function webhook_verify_forwards_the_raw_payload_byte_for_byte(): void
	{
		// Decoding into PHP arrays and re-encoding would turn {} into [], drop the sign of
		// -0 and change the bytes the API re-canonicalises — so a genuine webhook would
		// fail to verify.
		$raw = '{"session":{"meta":{}},"list":[],"n":-0,"amountMinUnits":"1000000000000000000","s":"\u00e9"}';
		$this->http->push(['message' => 'webhook verified!']);

		$this->assertTrue($this->client()->webhooks->verify("  {$raw}\n", 'sha256=ab', '1700000000'));
		$this->assertSame(
			'{"payload":' . $raw . ',"receivedSignature":"sha256=ab","receivedTimestamp":"1700000000"}',
			(string) $this->http->lastRequest()->getBody(),
		);
	}

	#[Test]
	public function webhook_verify_sends_a_decoded_payload_in_canonical_form(): void
	{
		$this->http->push(['message' => 'webhook verified!']);

		$this->client()->webhooks->verify(
			['b' => 19.99, 'a' => (object) [], 'c' => ['x' => '1000000000000000000']],
			'sig',
			'1',
		);

		$this->assertSame(
			'{"payload":{"a":{},"b":19.99,"c":{"x":"1000000000000000000"}},"receivedSignature":"sig","receivedTimestamp":"1"}',
			(string) $this->http->lastRequest()->getBody(),
		);
	}

	#[Test]
	public function webhook_verify_rejects_payloads_json_cannot_carry(): void
	{
		foreach ([['n' => NAN], ['n' => INF], '"just a string"', '{bad json'] as $payload) {
			try {
				$this->client()->webhooks->verify($payload, 'sig', '1');
				$this->fail('Expected a ValidationException.');
			} catch (\QBitFlow\Exceptions\ValidationException $e) {
				$this->assertNull($e->getStatusCode());
			}
		}

		$this->assertSame(0, $this->http->requestCount());
	}

	#[Test]
	public function webhook_verify_reports_a_rejected_signature_as_false(): void
	{
		// The API answers 400 when the signature does not match.
		$this->http->push(['error' => 'signature mismatch'], 400);

		$this->assertFalse(
			$this->client()->webhooks->verify('{"uuid":"s1"}', 'bad-sig', '1700000000'),
		);
	}

	/**
	 * @return iterable<string,array{int,class-string<\Throwable>}>
	 */
	public static function webhookVerifyFailureProvider(): iterable
	{
		yield 'expired api key' => [401, UnauthorizedException::class];
		yield 'insufficient role' => [403, ForbiddenException::class];
		yield 'conflict' => [409, ConflictException::class];
	}

	#[Test]
	#[DataProvider('webhookVerifyFailureProvider')]
	public function webhook_verify_propagates_non_rejection_failures(
		int $status,
		string $expected,
	): void {
		// Reporting these as "not verified" would make a credentials problem look like a
		// forged webhook, and a handler that drops unverified events would discard real
		// payments. Only a 400 means "signature rejected".
		$this->http->push(['error' => 'nope'], $status);

		$this->expectException($expected);

		$this->client()->webhooks->verify('{"uuid":"s1"}', 'sig', '1700000000');
	}
}
