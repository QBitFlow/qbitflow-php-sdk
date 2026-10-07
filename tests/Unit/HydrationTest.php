<?php

declare(strict_types=1);

namespace QBitFlow\Tests\Unit;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use QBitFlow\Dto\AccountingEvent;
use QBitFlow\Dto\ApiKey;
use QBitFlow\Dto\CombinedPayment;
use QBitFlow\Dto\Currency;
use QBitFlow\Dto\Customer;
use QBitFlow\Dto\Metadata\PaymentMetadata;
use QBitFlow\Dto\Payment;
use QBitFlow\Dto\Product;
use QBitFlow\Dto\RefundEntry;
use QBitFlow\Dto\Session\OneTimePaymentSession;
use QBitFlow\Dto\Session\SessionCheckout;
use QBitFlow\Dto\Session\SessionWebhookResponse;
use QBitFlow\Dto\Session\SubscriptionSession;
use QBitFlow\Dto\Subscription;
use QBitFlow\Dto\SubscriptionHistory;
use QBitFlow\Dto\SubscriptionStatusTransition;
use QBitFlow\Dto\SubscriptionWebhook;
use QBitFlow\Dto\TransactionStatus;
use QBitFlow\Dto\User;
use QBitFlow\Enums\AccountingEventType;
use QBitFlow\Enums\CombinedPaymentSource;
use QBitFlow\Enums\RefundStatus;
use QBitFlow\Enums\SubscriptionStatus;
use QBitFlow\Enums\SubscriptionWebhookType;
use QBitFlow\Enums\TransactionStatusValue;
use QBitFlow\Enums\TransactionType;
use QBitFlow\Enums\UserRole;
use QBitFlow\Exceptions\ServerException;
use QBitFlow\Support\Cast;
use QBitFlow\Support\CursorData;
use QBitFlow\Support\Enums;

/**
 * Covers the mapping from raw API payloads onto typed objects, under the decoding policy
 * every QBitFlow SDK shares: absent or null → zero value; wrong JSON type → ServerException.
 */
final class HydrationTest extends TestCase
{
	private const GO_ZERO_TIME = '0001-01-01T00:00:00Z';

	private const ETH = [
		'id' => 1,
		'name' => 'Ether',
		'symbol' => 'ETH',
		'decimals' => 18,
		'address' => '',
		'test' => false,
		'mainCurrency' => null,
	];

	/**
	 * @param array<string,mixed> $overrides
	 * @return array<string,mixed>
	 */
	private static function subscriptionPayload(array $overrides = []): array
	{
		return $overrides + [
			'uuid' => 'sub@01a0',
			'createdAt' => '2026-01-01T00:00:00Z',
			'updatedAt' => '2026-02-01T00:00:00Z',
			'from' => '0xa',
			'to' => '0xb',
			'productId' => 1,
			'subscriptionHash' => '0xhash',
			'currencyId' => 1,
			'currency' => self::ETH,
			'test' => true,
			'customerUUID' => 'c1',
			'frequency' => 2592000,
			'allowance' => '299.97',
			'subscriptionStatus' => 'active',
			'stopped' => false,
			'lastBillingDate' => '2026-02-01T00:00:00Z',
			'nextBillingDate' => '2026-03-01T00:00:00Z',
			'organizationId' => 3,
			'userId' => 0,
		];
	}

	/**
	 * @param array<string,mixed> $overrides
	 * @return array<string,mixed>
	 */
	private static function paymentPayload(array $overrides = []): array
	{
		return $overrides + [
			'uuid' => 'pay@p1',
			'createdAt' => '2026-01-01T00:00:00Z',
			'from' => '0xa',
			'to' => '0xb',
			'name' => 'n',
			'description' => 'd',
			'amount' => 100.0,
			'amountMinUnits' => '100000000',
			'currencyId' => 1,
			'currency' => self::ETH,
			'transactionHash' => '0xh',
			'test' => true,
			'organizationId' => 3,
			'metadata' => ['feeBps' => 150],
		];
	}

	// -------------------------------------------------------------------------
	// Absent or null → zero value
	// -------------------------------------------------------------------------

	#[Test]
	public function a_customer_hydrates_its_optional_strings_to_empty_not_null(): void
	{
		$customer = Customer::fromArray([
			'uuid' => '01997c89-d0e9-7c9a-9886-fe7709919695',
			'name' => 'John',
			'lastName' => 'Doe',
			'email' => 'john@example.com',
			'createdAt' => '2026-01-15T10:30:00.000Z',
			'organizationId' => 3,
		]);

		$this->assertSame('John Doe', $customer->fullName());
		$this->assertSame('2026-01-15 10:30:00', $customer->createdAt->format('Y-m-d H:i:s'));
		$this->assertSame('', $customer->phoneNumber, 'omitempty strings decode to ""');
		$this->assertSame('', $customer->address);
		$this->assertSame('', $customer->reference);
		$this->assertSame(3, $customer->organizationId);
		$this->assertSame(0, $customer->userId, 'The API omits userId for organization-level customers.');
	}

	#[Test]
	public function an_empty_object_hydrates_every_type_to_zero_values(): void
	{
		$product = Product::fromArray([]);
		$this->assertSame(0, $product->id);
		$this->assertSame('', $product->reference);
		$this->assertSame(0.0, $product->price);
		$this->assertFalse($product->isActive);
		$this->assertTrue(Cast::isZeroTime($product->createdAt), 'An absent timestamp is Go zero time, not an error.');

		$payment = Payment::fromArray([]);
		$this->assertSame('', $payment->amountMinUnits, 'An absent decimal string is "", as in the Go, JS and Python SDKs.');
		$this->assertSame(0, $payment->productId);
		$this->assertSame(0, $payment->userId);
		$this->assertNull($payment->reference);
		$this->assertNull($payment->customerUUID);
		$this->assertSame(0, $payment->currency->id, 'A required nested object decodes to its zero value.');
		$this->assertSame('', $payment->currency->address);
		$this->assertNull($payment->currency->mainCurrency);
		$this->assertSame(0, $payment->metadata->feeBps);
		$this->assertSame('', $payment->metadata->txAmounts->minUnits->merchant);
		$this->assertSame('', $payment->metadata->txMetadata->networkFees->amount);
		$this->assertSame(0.0, $payment->metadata->txMetadata->mainCurrencyPriceUSD);
		$this->assertNull($payment->metadata->organizationFee);

		$status = TransactionStatus::fromArray([]);
		$this->assertSame('', $status->status, 'An absent enum is the zero string, never a guessed member.');
		$this->assertSame('', $status->txHash);
		$this->assertSame('', $status->message);
		$this->assertNull($status->settlementDetails);
	}

	#[Test]
	public function null_is_decoded_like_absent(): void
	{
		$payment = Payment::fromArray(self::paymentPayload([
			'productId' => null,
			'organizationId' => null,
			'userId' => null,
			'currency' => null,
			'metadata' => null,
			'test' => null,
			'createdAt' => null,
		]));

		$this->assertSame(0, $payment->productId);
		$this->assertSame(0, $payment->organizationId);
		$this->assertSame(0, $payment->userId);
		$this->assertSame(0, $payment->currency->id);
		$this->assertSame(0, $payment->metadata->feeBps);
		$this->assertFalse($payment->test);
		$this->assertTrue(Cast::isZeroTime($payment->createdAt));
	}

	#[Test]
	public function null_lists_are_empty_lists(): void
	{
		$session = OneTimePaymentSession::fromArray(['uuid' => 's1', 'availableCurrencies' => null]);
		$this->assertSame([], $session->availableCurrencies);

		$page = CursorData::fromArray(['items' => null, 'nextCursor' => null], Payment::fromArray(...));
		$this->assertSame([], $page->items);
		$this->assertFalse($page->hasMore());

		$this->assertSame([], Cast::listOf(null, Payment::fromArray(...)));
	}

	// -------------------------------------------------------------------------
	// Wrong JSON type → ServerException
	// -------------------------------------------------------------------------

	/**
	 * @return iterable<string,array{callable(): mixed, string}>
	 */
	public static function wrongTypeProvider(): iterable
	{
		yield 'string where an int is expected' => [
			static fn () => Product::fromArray(['id' => '7']),
			'"id" must be an integer',
		];
		yield 'non-integral float where an int is expected' => [
			static fn () => Product::fromArray(['id' => 1.5]),
			'"id" must be an integer',
		];
		yield 'number where a string is expected' => [
			static fn () => Product::fromArray(['name' => 42]),
			'"name" must be a string',
		];
		yield 'object where a string is expected' => [
			static fn () => Customer::fromArray(['email' => ['x' => 1]]),
			'"email" must be a string',
		];
		yield 'string where a number is expected' => [
			static fn () => Product::fromArray(['price' => '29.99']),
			'"price" must be a number',
		];
		yield 'integer where a boolean is expected' => [
			static fn () => Product::fromArray(['isActive' => 1]),
			'"isActive" must be a boolean',
		];
		yield 'list where an object is expected' => [
			static fn () => Payment::fromArray(['currency' => [1, 2]]),
			'"currency" must be an object',
		];
		yield 'string where an object is expected' => [
			static fn () => Payment::fromArray(['metadata' => 'none']),
			'"metadata" must be an object',
		];
		yield 'object where a list is expected' => [
			static fn () => OneTimePaymentSession::fromArray(['availableCurrencies' => ['a' => 1]]),
			'"availableCurrencies" must be a list',
		];
		yield 'string inside an int list' => [
			static fn () => OneTimePaymentSession::fromArray(['availableCurrencies' => [1, '2']]),
			'"availableCurrencies[1]" must be an integer',
		];
		yield 'unparseable timestamp' => [
			static fn () => Product::fromArray(['createdAt' => 'yesterday']),
			'"createdAt" must be an RFC 3339 timestamp',
		];
		yield 'number where a timestamp is expected' => [
			static fn () => User::fromArray(['claimedAt' => 1767225600]),
			'"claimedAt" must be an RFC 3339 timestamp',
		];
		yield 'number where an enum is expected' => [
			static fn () => Subscription::fromArray(['subscriptionStatus' => 3]),
			'"subscriptionStatus" must be a string',
		];
		yield 'scalar list item where an object is expected' => [
			static fn () => Cast::listOf([['id' => 1], 'x'], Product::fromArray(...)),
			'"response[1]" must be an object',
		];
		yield 'a bare list where a cursor page is expected' => [
			static fn () => CursorData::fromArray([['uuid' => 'p1']], Payment::fromArray(...)),
			'"response" must be an object',
		];
		yield 'items that are not a list' => [
			static fn () => CursorData::fromArray(['items' => 'none'], Payment::fromArray(...)),
			'"items" must be a list',
		];
		yield 'an integer beyond PHP_INT_MAX' => [
			static fn () => Product::fromArray(['id' => '18446744073709551615']),
			'beyond PHP\'s range',
		];
	}

	/**
	 * @param callable(): mixed $hydrate
	 */
	#[Test]
	#[DataProvider('wrongTypeProvider')]
	public function a_field_of_the_wrong_type_is_a_malformed_response(callable $hydrate, string $message): void
	{
		$this->expectException(ServerException::class);
		$this->expectExceptionMessage($message);

		$hydrate();
	}

	#[Test]
	public function harmless_numeric_widening_is_accepted(): void
	{
		$product = Product::fromArray(['id' => 7.0, 'price' => 30]);

		$this->assertSame(7, $product->id, 'An integral float fits an int field.');
		$this->assertSame(30.0, $product->price, 'An integer fits a float field.');

		// An integer too large for PHP_INT (decoded as a digit string) still fits a float field.
		$this->assertSame(1.8446744073709552E+19, Payment::fromArray(['amount' => '18446744073709551615'])->amount);
	}

	#[Test]
	public function unknown_keys_are_ignored(): void
	{
		$this->assertSame(1, Product::fromArray(['id' => 1, 'somethingNew' => ['x' => true]])->id);
	}

	// -------------------------------------------------------------------------
	// Timestamps
	// -------------------------------------------------------------------------

	#[Test]
	public function timestamps_parse_rfc3339_with_nanoseconds_and_offsets(): void
	{
		$nanos = Cast::date(['d' => '2026-01-02T03:04:05.123456789Z'], 'd');
		$this->assertSame('2026-01-02T03:04:05.123456+00:00', $nanos->format('Y-m-d\TH:i:s.uP'));

		$offset = Cast::date(['d' => '2026-01-02T03:04:05+02:00'], 'd');
		$this->assertSame('2026-01-02T01:04:05+00:00', $offset->setTimezone(new \DateTimeZone('UTC'))->format('c'));
	}

	#[Test]
	public function go_zero_time_is_kept_as_a_date_on_required_timestamps(): void
	{
		$zero = Cast::date(['d' => self::GO_ZERO_TIME], 'd');

		$this->assertSame('0001-01-01', $zero->format('Y-m-d'));
		$this->assertTrue(Cast::isZeroTime($zero));
		$this->assertTrue(Cast::isZeroTime(Cast::zeroTime()));
		$this->assertFalse(Cast::isZeroTime(new DateTimeImmutable('2026-01-01T00:00:00Z')));
	}

	#[Test]
	public function nullable_timestamps_are_null_only_when_absent_or_null(): void
	{
		$this->assertNull(Cast::nullableDate([], 'd'));
		$this->assertNull(Cast::nullableDate(['d' => null], 'd'));
		$this->assertSame('2026', Cast::nullableDate(['d' => '2026-01-01T00:00:00Z'], 'd')?->format('Y'));
	}

	// -------------------------------------------------------------------------
	// Resources
	// -------------------------------------------------------------------------

	#[Test]
	public function it_tolerates_both_spellings_of_the_user_update_timestamp(): void
	{
		$base = [
			'id' => 1,
			'name' => 'Jane',
			'lastName' => 'Smith',
			'email' => 'jane@example.com',
			'createdAt' => '2026-01-01T00:00:00Z',
			'organizationId' => 3,
			'role' => 'admin',
			'organizationFeeBps' => 100,
		];

		$modern = User::fromArray($base + ['updatedAt' => '2026-02-01T00:00:00Z']);
		$legacy = User::fromArray($base + ['updateAt' => '2026-02-01T00:00:00Z']);

		$this->assertSame('2026-02-01', $modern->updatedAt->format('Y-m-d'));
		$this->assertSame('2026-02-01', $legacy->updatedAt->format('Y-m-d'));
		$this->assertSame(UserRole::ADMIN, $modern->role);
		$this->assertFalse($modern->hasClaimedAccount());
		$this->assertTrue(Cast::isZeroTime(User::fromArray($base)->updatedAt));
	}

	#[Test]
	public function a_payment_carries_its_currency_and_metadata(): void
	{
		$payment = Payment::fromArray(self::paymentPayload([
			'from' => '0xSender',
			'to' => '0xReceiver',
			'amount' => 49.99,
			'amountMinUnits' => '1000000000000000000',
			'currencyId' => 5,
			'currency' => [
				'id' => 5,
				'symbol' => 'USDC',
				'name' => 'USD Coin',
				'decimals' => 6,
				'test' => true,
				'address' => '0xa0b8',
				'mainCurrencyId' => 1,
				'mainCurrency' => self::ETH,
			],
			'customerUUID' => 'c1',
			'productId' => 9,
			'reference' => 'order-1',
			'userId' => 12,
			'metadata' => [
				'feeBps' => 150,
				'organizationFee' => ['organizationId' => 3, 'organization' => '0xorg', 'feeBps' => 50],
				'referralFee' => [
					'referralId' => 9,
					'referrer' => '0xref',
					'feeBps' => 25,
					'deadline' => '2026-12-31T23:59:59Z',
				],
				'txMetadata' => [
					'networkFees' => ['amount' => '21000000000000', 'unitsConsumed' => 21000],
					'blockData' => ['number' => '19123456', 'timestamp' => 1767225600],
					'mainCurrencyPriceUSD' => 3120.55,
				],
				'txAmounts' => [
					'usd' => ['platform' => 1.5, 'organization' => 0.5, 'merchant' => 98.0],
					'minUnits' => [
						'platform' => '1500000',
						'organization' => '500000',
						'referral' => '0',
						'merchant' => '98000000',
					],
				],
			],
		]));

		$this->assertSame('0xSender', $payment->from);
		$this->assertSame('1000000000000000000', $payment->amountMinUnits, 'Min units stay a string for precision.');
		$this->assertSame('c1', $payment->customerUUID);
		$this->assertSame('order-1', $payment->reference);
		$this->assertSame(9, $payment->productId);
		$this->assertSame(12, $payment->userId);

		$this->assertSame('USDC', $payment->currency->symbol);
		$this->assertTrue($payment->currency->isToken());
		$this->assertSame('ETH', $payment->currency->mainCurrency?->symbol);
		$this->assertNull($payment->currency->mainCurrency?->mainCurrency, 'A main currency has no main currency.');

		$metadata = $payment->metadata;
		$this->assertSame(1.5, $metadata->feePercent());
		$this->assertSame(3, $metadata->organizationFee?->organizationId);
		$this->assertSame('0xorg', $metadata->organizationFee?->organization);
		$this->assertSame(9, $metadata->referralFee?->referralId);
		$this->assertSame('2026-12-31', $metadata->referralFee?->deadline->format('Y-m-d'));
		$this->assertSame(21000, $metadata->txMetadata->networkFees->unitsConsumed);
		$this->assertSame('19123456', $metadata->txMetadata->blockData->number);
		$this->assertSame(3120.55, $metadata->txMetadata->mainCurrencyPriceUSD);
		$this->assertSame(98.0, $metadata->txAmounts->usd->merchant);
		$this->assertSame(0.0, $metadata->txAmounts->usd->referral, 'omitempty USD shares decode to 0.0');
		$this->assertSame('98000000', $metadata->txAmounts->minUnits->merchant);
	}

	#[Test]
	public function a_subscription_has_non_null_billing_dates_and_a_currency(): void
	{
		$subscription = Subscription::fromArray(self::subscriptionPayload(['subscriptionStatus' => 'trial']));

		$this->assertSame(SubscriptionStatus::TRIAL, $subscription->subscriptionStatus);
		$this->assertTrue($subscription->isActive(), 'A trialing subscription counts as active.');
		$this->assertSame('299.97', $subscription->allowance);
		$this->assertSame('2026-03-01', $subscription->nextBillingDate->format('Y-m-d'));
		$this->assertTrue($subscription->hasBeenBilled());
		$this->assertSame('ETH', $subscription->currency->symbol);
		$this->assertSame(3, $subscription->organizationId);
		$this->assertSame(0, $subscription->userId);
		$this->assertNull($subscription->reference);
		$this->assertNull($subscription->minimumCancellationDate);
	}

	#[Test]
	public function an_unbilled_subscription_reports_go_zero_time(): void
	{
		$subscription = Subscription::fromArray(self::subscriptionPayload([
			'lastBillingDate' => self::GO_ZERO_TIME,
			'nextBillingDate' => self::GO_ZERO_TIME,
		]));

		$this->assertTrue(Cast::isZeroTime($subscription->lastBillingDate));
		$this->assertTrue(Cast::isZeroTime($subscription->nextBillingDate));
		$this->assertFalse($subscription->hasBeenBilled());
	}

	#[Test]
	public function a_subscription_history_row_is_fully_typed(): void
	{
		$history = SubscriptionHistory::fromArray([
			'uuid' => 'sub-hist@h1',
			'createdAt' => '2026-02-01T00:00:00Z',
			'amount' => 29.99,
			'amountMinUnits' => '29990000',
			'currencyId' => 1,
			'currency' => self::ETH,
			'productId' => 4,
			'subscriptionUUID' => 'sub@s1',
			'organizationId' => 3,
			'metadata' => ['feeBps' => 100],
		]);

		$this->assertSame('sub@s1', $history->subscriptionUUID);
		$this->assertSame(4, $history->productId);
		$this->assertSame(0, $history->userId);
		$this->assertSame('ETH', $history->currency->symbol);
		$this->assertSame(100, $history->metadata->feeBps);
		$this->assertNull($history->customerUUID);
	}

	#[Test]
	public function an_unknown_enum_value_is_preserved_as_the_raw_string(): void
	{
		// Neither guessed (this used to fall back to ACTIVE — failing open on the one field
		// where that grants access) nor fatal: the rest of the response stays usable and
		// the caller can log, store or compare the value. Every QBitFlow SDK behaves this way.
		$subscription = Subscription::fromArray(self::subscriptionPayload(['subscriptionStatus' => 'something_new']));

		$this->assertSame('something_new', $subscription->subscriptionStatus);
		$this->assertFalse($subscription->isActive(), 'An unknown status is not treated as active.');
		$this->assertTrue(Enums::isUnknown($subscription->subscriptionStatus));
		$this->assertSame('something_new', Enums::value($subscription->subscriptionStatus));
		$this->assertFalse(Enums::is($subscription->subscriptionStatus, SubscriptionStatus::ACTIVE));

		$known = Subscription::fromArray(self::subscriptionPayload());
		$this->assertSame('active', Enums::value($known->subscriptionStatus));
		$this->assertFalse(Enums::isUnknown($known->subscriptionStatus));
	}

	#[Test]
	public function an_absent_enum_is_the_empty_string_not_a_default_member(): void
	{
		$payload = self::subscriptionPayload();
		unset($payload['subscriptionStatus']);

		$subscription = Subscription::fromArray($payload);

		$this->assertSame('', $subscription->subscriptionStatus);
		$this->assertFalse($subscription->isActive());
	}

	#[Test]
	public function it_hydrates_every_role_the_api_returns(): void
	{
		// `handle` was missing from the enum, so a handle user was silently downgraded to
		// USER — misreporting a below-user principal as a user.
		$base = ['id' => 1, 'email' => 'e@x.com', 'createdAt' => '2026-01-01T00:00:00Z', 'updatedAt' => '2026-01-01T00:00:00Z'];

		foreach (['handle', 'user', 'admin', 'owner'] as $role) {
			$user = User::fromArray($base + ['role' => $role]);

			$this->assertSame($role, Enums::value($user->role));
			$this->assertInstanceOf(UserRole::class, $user->role);
		}

		$this->assertSame('superuser', User::fromArray($base + ['role' => 'superuser'])->role);
		$this->assertSame('root', ApiKey::fromArray($base + ['role' => 'root', 'name' => 'k'])->role);
	}

	#[Test]
	public function it_accepts_both_documented_spellings_of_the_subscription_billing_row_type(): void
	{
		// The API reference names this row both `subscriptionHistory` and `subHistory`.
		foreach (['subscriptionHistory', 'subHistory'] as $wire) {
			$event = AccountingEvent::fromArray(['paymentId' => 'p1', 'type' => $wire, 'txTimeUtc' => '2026-01-15T10:00:00Z']);

			$this->assertSame(AccountingEventType::SUBSCRIPTION_HISTORY, $event->type, $wire);
		}
	}

	#[Test]
	public function an_unknown_accounting_event_type_is_preserved_not_counted_as_a_payment(): void
	{
		$event = AccountingEvent::fromArray(['paymentId' => 'p1', 'type' => 'someNewKind', 'txTimeUtc' => '2026-01-15T10:00:00Z']);

		$this->assertSame('someNewKind', $event->type);
		$this->assertNotSame(AccountingEventType::PAYMENT, $event->type);
	}

	#[Test]
	public function it_hydrates_an_accounting_event_with_every_field_required(): void
	{
		$event = AccountingEvent::fromArray([
			'paymentId' => 'pay_1',
			'type' => 'refund',
			'txTimeUtc' => '2026-01-15T10:00:00Z',
			'grossAmount' => '100000000',
			'grossAmountUsd' => 100.0,
			'netAmount' => '98000000',
			'netAmountUsd' => 98.0,
			'currencyDecimals' => 6,
		]);

		$this->assertSame(AccountingEventType::REFUND, $event->type);
		$this->assertSame('2026-01-15', $event->txTimeUtc->format('Y-m-d'));
		$this->assertSame('100000000', $event->grossAmount);
		$this->assertSame(98.0, $event->netAmountUsd);
		$this->assertSame(0.0, $event->networkFeesUsd);
		$this->assertSame('', $event->networkFees);
		$this->assertSame('', $event->relatedPaymentId);
	}

	#[Test]
	public function a_refund_has_plain_string_message_and_hash_until_answered(): void
	{
		$refund = RefundEntry::fromArray([
			'uuid' => 'refund@r1',
			'txId' => 'pay@p1',
			'test' => true,
			'reason' => 'Changed my mind',
			'status' => 'pending',
			'createdAt' => '2026-01-01T00:00:00Z',
			'merchantMessage' => '',
			'respondedAt' => null,
			'txHash' => '',
			'amountMinUnits' => '10000000',
			'organizationId' => 3,
			'userId' => 0,
			'metadata' => null,
		]);

		$this->assertSame(RefundStatus::PENDING, $refund->status);
		$this->assertTrue($refund->isPending());
		$this->assertNull($refund->respondedAt);
		$this->assertSame('', $refund->txHash);
		$this->assertSame('', $refund->merchantMessage);
		$this->assertSame(3, $refund->organizationId);
		$this->assertSame(0, $refund->userId);
		$this->assertNull($refund->metadata);

		$unknown = RefundEntry::fromArray(['uuid' => 'r', 'status' => 'escalated', 'createdAt' => '2026-01-01T00:00:00Z']);
		$this->assertSame('escalated', $unknown->status);
		$this->assertFalse($unknown->isPending());
	}

	#[Test]
	public function an_api_key_reports_zero_for_an_organization_level_owner(): void
	{
		$key = ApiKey::fromArray(['id' => 4, 'name' => 'ci', 'organizationId' => 7, 'createdAt' => '2026-01-01T00:00:00Z', 'role' => 'admin', 'test' => false]);

		$this->assertSame(0, $key->userId);
		$this->assertNull($key->expiresAt);
		$this->assertFalse($key->isExpired());

		$expired = ApiKey::fromArray(['id' => 4, 'createdAt' => '2026-01-01T00:00:00Z', 'expiresAt' => '2020-01-01T00:00:00Z']);
		$this->assertTrue($expired->isExpired());
	}

	#[Test]
	public function a_combined_entry_carries_its_currency_and_no_owner_fields(): void
	{
		$entry = CombinedPayment::fromArray([
			'source' => 'subscription_history',
			'uuid' => 'h1',
			'createdAt' => '2026-01-01T00:00:00Z',
			'from' => 'a',
			'to' => 'b',
			'customerUUID' => '00000000-0000-0000-0000-000000000000',
			'subscriptionUUID' => 'sub1',
			'amount' => 29.99,
			'amountMinUnits' => '29990000',
			'currency' => self::ETH,
			'metadata' => null,
		]);

		$this->assertSame(CombinedPaymentSource::SUBSCRIPTION_HISTORY, $entry->source);
		$this->assertTrue($entry->isSubscriptionBilling());
		$this->assertSame('sub1', $entry->subscriptionUUID);
		$this->assertSame('00000000-0000-0000-0000-000000000000', $entry->customerUUID);
		$this->assertSame('ETH', $entry->currency->symbol);
		$this->assertNull($entry->productId);
		$this->assertNull($entry->metadata);
		$this->assertFalse(property_exists($entry, 'organizationId'), 'CombinedPaymentItem has no owner fields.');
	}

	// -------------------------------------------------------------------------
	// Sessions and webhooks
	// -------------------------------------------------------------------------

	#[Test]
	public function it_discriminates_sessions_by_tx_type_first_then_by_frequency(): void
	{
		$payment = SessionCheckout::discriminate(['uuid' => 's1', 'txType' => 'payment', 'organizationId' => 3, 'feeBps' => 150]);
		$this->assertInstanceOf(OneTimePaymentSession::class, $payment);
		$this->assertTrue($payment->isPayment());
		$this->assertSame(TransactionType::ONE_TIME_PAYMENT, $payment->txType);
		$this->assertSame(3, $payment->organizationId);
		$this->assertSame(150, $payment->feeBps);
		$this->assertSame('', $payment->reference, 'omitempty session fields decode to their zero value');
		$this->assertSame(0, $payment->productId);
		$this->assertSame(0, $payment->userId);
		$this->assertNull($payment->customerUUID);

		$subscription = SessionCheckout::discriminate([
			'uuid' => 's2',
			'txType' => 'createSubscription',
			'frequency' => 2592000,
			'trialPeriod' => 604800,
			'minPeriods' => 3,
			'upgradingFromTrial' => true,
			'availableCurrencies' => [1, 2, 5],
		]);
		$this->assertInstanceOf(SubscriptionSession::class, $subscription);
		$this->assertFalse($subscription->isPayment());
		$this->assertSame(2592000, $subscription->frequency);
		$this->assertTrue($subscription->hasTrial());
		$this->assertTrue($subscription->upgradingFromTrial);
		$this->assertSame([1, 2, 5], $subscription->availableCurrencies);

		// txType wins when present.
		$this->assertInstanceOf(
			OneTimePaymentSession::class,
			SessionCheckout::discriminate(['uuid' => 's3', 'txType' => 'payment', 'frequency' => 60]),
		);

		// Without txType, frequency decides.
		$byFrequency = SessionCheckout::discriminate(['uuid' => 's4', 'frequency' => 2592000]);
		$this->assertInstanceOf(SubscriptionSession::class, $byFrequency);
		$this->assertFalse($byFrequency->upgradingFromTrial, 'Defaults to false when absent.');
		$this->assertSame(0, $byFrequency->trialPeriod);
		$this->assertSame(0, $byFrequency->minPeriods);
		$this->assertInstanceOf(OneTimePaymentSession::class, SessionCheckout::discriminate(['uuid' => 's5']));

		// An unknown txType is kept as a string and the frequency fallback applies.
		$unknown = SessionCheckout::discriminate(['uuid' => 's6', 'txType' => 'somethingElse']);
		$this->assertSame('somethingElse', $unknown->txType);
		$this->assertInstanceOf(OneTimePaymentSession::class, $unknown);
	}

	#[Test]
	public function it_hydrates_a_transaction_webhook_payload(): void
	{
		$event = SessionWebhookResponse::fromArray([
			'uuid' => 's1',
			'txType' => 'createSubscription',
			'managementPageLink' => 'https://qbitflow.app/manage/s1',
			'status' => ['status' => 'completed', 'txHash' => '0xabc'],
			'session' => [
				'uuid' => 's1',
				'reference' => 'order-1234',
				'frequency' => 2592000,
				'productName' => 'Premium',
				'price' => 29.99,
			],
		]);

		$this->assertSame(TransactionType::CREATE_SUBSCRIPTION, $event->txType);
		$this->assertTrue($event->isSubscription());
		$this->assertSame(TransactionStatusValue::COMPLETED, $event->status?->status);
		$this->assertSame('0xabc', $event->status?->txHash);
		$this->assertInstanceOf(SubscriptionSession::class, $event->session);
		$this->assertSame('order-1234', $event->session->reference);
		$this->assertSame('https://qbitflow.app/manage/s1', $event->managementPageLink);
	}

	#[Test]
	public function a_transaction_webhook_without_status_or_management_link(): void
	{
		$event = SessionWebhookResponse::fromArray(['uuid' => 's1', 'txType' => 'payment', 'session' => ['uuid' => 's1']]);

		$this->assertNull($event->status);
		$this->assertSame('', $event->managementPageLink);
		$this->assertFalse($event->isSubscription());

		$unknown = SessionWebhookResponse::fromArray(['uuid' => 's1', 'txType' => 'brandNew', 'session' => []]);
		$this->assertSame('brandNew', $unknown->txType);
	}

	#[Test]
	public function a_transaction_webhook_with_a_malformed_session_is_rejected(): void
	{
		$this->expectException(ServerException::class);
		$this->expectExceptionMessage('"session" must be an object');

		SessionWebhookResponse::fromArray(['uuid' => 's1', 'txType' => 'payment', 'session' => 'nope']);
	}

	#[Test]
	public function a_subscription_webhook_types_its_data_by_event_type(): void
	{
		$transition = SubscriptionWebhook::fromArray([
			'subscriptionUUID' => 'sub@s1',
			'type' => 'status_transition',
			'data' => ['previousStatus' => 'active', 'currentStatus' => 'paused', 'updatedAt' => '2026-03-01T12:00:00Z'],
		]);

		$this->assertSame(SubscriptionWebhookType::STATUS_TRANSITION, $transition->type);
		$this->assertInstanceOf(SubscriptionStatusTransition::class, $transition->data);
		$this->assertTrue($transition->isStatusTransition());
		$this->assertSame(SubscriptionStatus::ACTIVE, $transition->data->previousStatus);
		$this->assertSame('paused', $transition->data->currentStatus, 'Unknown statuses stay raw strings.');
		$this->assertSame('sub@s1', $transition->subscriptionUUID);
		$this->assertSame('', $transition->subscriptionReference, 'omitempty reference decodes to ""');

		$billing = SubscriptionWebhook::fromArray([
			'subscriptionUUID' => 'sub@s1',
			'subscriptionReference' => 'plan-7',
			'type' => 'billing',
			'data' => ['uuid' => 'sub-hist@h1', 'amount' => 9.99, 'currency' => self::ETH],
		]);

		$this->assertInstanceOf(SubscriptionHistory::class, $billing->data);
		$this->assertTrue($billing->isBilling());
		$this->assertSame(9.99, $billing->data->amount);
		$this->assertSame('plan-7', $billing->subscriptionReference);

		$unknown = SubscriptionWebhook::fromArray(['subscriptionUUID' => 'sub@s1', 'type' => 'refunded', 'data' => ['x' => 1]]);
		$this->assertSame('refunded', $unknown->type);
		$this->assertSame(['x' => 1], $unknown->data, 'An unknown event keeps its raw data.');
	}

	#[Test]
	public function a_transaction_status_has_an_empty_hash_until_broadcast(): void
	{
		$status = TransactionStatus::fromArray(['status' => 'created']);

		$this->assertSame(TransactionStatusValue::CREATED, $status->status);
		$this->assertSame('', $status->txHash);
		$this->assertSame('', $status->message);
		$this->assertFalse($status->isCompleted());

		$unknown = TransactionStatus::fromArray(['status' => 'brand_new', 'txHash' => '0x1']);
		$this->assertSame('brand_new', $unknown->status);
		$this->assertFalse($unknown->isCompleted());
		$this->assertFalse($unknown->isFailed());
		$this->assertSame('0x1', $unknown->txHash);
	}

	#[Test]
	public function payment_metadata_requires_its_nested_objects_but_not_the_fee_recipients(): void
	{
		$metadata = PaymentMetadata::fromArray(['feeBps' => 150]);

		$this->assertSame('', $metadata->txAmounts->minUnits->platform);
		$this->assertSame(0.0, $metadata->txAmounts->usd->merchant);
		$this->assertSame(0, $metadata->txMetadata->blockData->timestamp);
		$this->assertNull($metadata->organizationFee);
		$this->assertNull($metadata->referralFee);
	}

	#[Test]
	public function it_serializes_back_to_the_api_shape_and_omits_nulls(): void
	{
		$currency = new Currency(id: 1, symbol: 'BTC', name: 'Bitcoin', decimals: 8, test: false);

		$this->assertSame([
			'id' => 1,
			'symbol' => 'BTC',
			'name' => 'Bitcoin',
			'decimals' => 8,
			'test' => false,
			'address' => '',
		], $currency->toArray());

		$this->assertSame('{"id":1,"symbol":"BTC","name":"Bitcoin","decimals":8,"test":false,"address":""}', json_encode($currency));
	}
}
