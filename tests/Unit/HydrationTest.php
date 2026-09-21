<?php

declare(strict_types=1);

namespace QBitFlow\Tests\Unit;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use QBitFlow\Dto\AccountingEvent;
use QBitFlow\Dto\CombinedPayment;
use QBitFlow\Dto\Currency;
use QBitFlow\Dto\Customer;
use QBitFlow\Dto\Payment;
use QBitFlow\Dto\RefundEntry;
use QBitFlow\Dto\Session\SessionCheckout;
use QBitFlow\Dto\Session\SessionWebhookResponse;
use QBitFlow\Dto\Session\SubscriptionSession;
use QBitFlow\Dto\Subscription;
use QBitFlow\Dto\User;
use QBitFlow\Enums\AccountingEventType;
use QBitFlow\Enums\CombinedPaymentSource;
use QBitFlow\Enums\RefundStatus;
use QBitFlow\Enums\SubscriptionStatus;
use QBitFlow\Enums\TransactionStatusValue;
use QBitFlow\Enums\TransactionType;
use QBitFlow\Enums\UserRole;

/**
 * Covers the mapping from raw API payloads onto typed objects.
 */
final class HydrationTest extends TestCase
{
	#[Test]
	public function it_hydrates_a_customer_and_parses_its_timestamp(): void
	{
		$customer = Customer::fromArray([
			'uuid' => '01997c89-d0e9-7c9a-9886-fe7709919695',
			'name' => 'John',
			'lastName' => 'Doe',
			'email' => 'john@example.com',
			'createdAt' => '2026-01-15T10:30:00.000Z',
			'reference' => 'CRM-1',
		]);

		$this->assertSame('John Doe', $customer->fullName());
		$this->assertInstanceOf(DateTimeImmutable::class, $customer->createdAt);
		$this->assertSame('2026-01-15 10:30:00', $customer->createdAt->format('Y-m-d H:i:s'));
		$this->assertNull($customer->phoneNumber);
		$this->assertNull($customer->organizationId, 'Authenticated-only fields stay null when absent.');
	}

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
	}

	#[Test]
	public function it_maps_the_reserved_looking_from_field(): void
	{
		$payment = Payment::fromArray([
			'uuid' => 'p1',
			'createdAt' => '2026-01-01T00:00:00Z',
			'from' => '0xSender',
			'to' => '0xReceiver',
			'name' => 'Widget',
			'description' => 'A widget',
			'amount' => 49.99,
			'amountMinUnits' => '49990000',
			'currencyId' => 2,
			'transactionHash' => '0xhash',
			'customerUUID' => 'c1',
			'test' => false,
		]);

		$this->assertSame('0xSender', $payment->from);
		$this->assertSame('0xReceiver', $payment->to);
		$this->assertSame('49990000', $payment->amountMinUnits, 'Min units stay a string for precision.');
		$this->assertNull($payment->currency);
	}

	#[Test]
	public function it_hydrates_nested_payment_metadata(): void
	{
		$payment = Payment::fromArray([
			'uuid' => 'p1',
			'createdAt' => '2026-01-01T00:00:00Z',
			'from' => '0xa',
			'to' => '0xb',
			'name' => 'n',
			'description' => 'd',
			'amount' => 100.0,
			'currencyId' => 1,
			'transactionHash' => '0xh',
			'customerUUID' => 'c1',
			'test' => true,
			'currency' => [
				'id' => 5,
				'symbol' => 'USDC',
				'name' => 'USD Coin',
				'decimals' => 6,
				'test' => true,
				'mainCurrencyId' => 1,
				'mainCurrency' => ['id' => 1, 'symbol' => 'ETH', 'name' => 'Ether', 'decimals' => 18, 'test' => true],
			],
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
		]);

		$this->assertNotNull($payment->currency);
		$this->assertTrue($payment->currency->isToken());
		$this->assertSame('ETH', $payment->currency->mainCurrency?->symbol);

		$metadata = $payment->metadata;
		$this->assertNotNull($metadata);
		$this->assertSame(1.5, $metadata->feePercent());
		$this->assertSame(50, $metadata->organizationFee?->feeBps);
		$this->assertSame('2026-12-31', $metadata->referralFee?->deadline?->format('Y-m-d'));
		$this->assertSame(21000, $metadata->txMetadata->networkFees->unitsConsumed);
		$this->assertSame('19123456', $metadata->txMetadata->blockData->number);
		$this->assertSame(3120.55, $metadata->txMetadata->mainCurrencyPriceUSD);
		$this->assertSame(98.0, $metadata->txAmounts->usd->merchant);
		$this->assertSame('98000000', $metadata->txAmounts->minUnits->merchant);
	}

	#[Test]
	public function it_survives_metadata_arriving_only_partly_populated(): void
	{
		$metadata = Payment::fromArray([
			'uuid' => 'p1',
			'from' => 'a',
			'to' => 'b',
			'metadata' => ['feeBps' => 150],
		])->metadata;

		$this->assertNotNull($metadata);
		$this->assertSame('0', $metadata->txMetadata->networkFees->amount);
		$this->assertSame(0.0, $metadata->txAmounts->usd->merchant);
		$this->assertNull($metadata->organizationFee);
	}

	#[Test]
	public function it_hydrates_a_subscription(): void
	{
		$subscription = Subscription::fromArray([
			'uuid' => 'sub1',
			'createdAt' => '2026-01-01T00:00:00Z',
			'updatedAt' => '2026-02-01T00:00:00Z',
			'from' => '0xa',
			'to' => '0xb',
			'productId' => 1,
			'subscriptionHash' => '0xhash',
			'currencyId' => 1,
			'test' => true,
			'customerUUID' => 'c1',
			'frequency' => 2592000,
			'allowance' => '299.97',
			'subscriptionStatus' => 'trial',
			'stopped' => false,
			'nextBillingDate' => '2026-03-01T00:00:00Z',
			'lastBillingDate' => null,
		]);

		$this->assertSame(SubscriptionStatus::TRIAL, $subscription->subscriptionStatus);
		$this->assertTrue($subscription->isActive(), 'A trialing subscription counts as active.');
		$this->assertSame('299.97', $subscription->allowance);
		$this->assertNull($subscription->lastBillingDate);
		$this->assertSame('2026-03-01', $subscription->nextBillingDate->format('Y-m-d'));
	}

	#[Test]
	public function it_falls_back_to_a_default_for_an_unknown_enum_value(): void
	{
		// A status the API adds later must not break an otherwise valid response.
		$subscription = Subscription::fromArray(['uuid' => 's', 'subscriptionStatus' => 'something_new']);

		$this->assertSame(SubscriptionStatus::ACTIVE, $subscription->subscriptionStatus);
	}

	#[Test]
	public function it_discriminates_the_three_session_shapes(): void
	{
		$payment = SessionCheckout::discriminate(['uuid' => 's1', 'txType' => 'payment']);
		$this->assertInstanceOf(\QBitFlow\Dto\Session\OneTimePaymentSession::class, $payment);
		$this->assertSame(TransactionType::ONE_TIME_PAYMENT, $payment->txType);

		$subscription = SessionCheckout::discriminate([
			'uuid' => 's2',
			'frequency' => 2592000,
			'trialPeriod' => 604800,
			'minPeriods' => 3,
			'availableCurrencies' => [1, 2, 5],
		]);
		$this->assertInstanceOf(SubscriptionSession::class, $subscription);
		$this->assertSame(2592000, $subscription->frequency);
		$this->assertTrue($subscription->hasTrial());
		$this->assertSame([1, 2, 5], $subscription->availableCurrencies);

		$payg = SessionCheckout::discriminate([
			'uuid' => 's3',
			'frequency' => ['value' => 1, 'unit' => 'months'],
			'freeCredits' => 5.0,
		]);
		$this->assertInstanceOf(\QBitFlow\Dto\Session\PaygSubscriptionSession::class, $payg);
		$this->assertSame('1 month', (string) $payg->frequency);
		$this->assertSame(5.0, $payg->freeCredits);
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
		$this->assertSame(TransactionStatusValue::COMPLETED, $event->status->status);
		$this->assertInstanceOf(SubscriptionSession::class, $event->session);
		$this->assertSame('order-1234', $event->session->reference);
	}

	#[Test]
	public function it_hydrates_a_combined_payment_entry(): void
	{
		$entry = CombinedPayment::fromArray([
			'source' => 'subscription_history',
			'uuid' => 'h1',
			'from' => 'a',
			'to' => 'b',
			'subscriptionUUID' => 'sub1',
			'amount' => 29.99,
		]);

		$this->assertSame(CombinedPaymentSource::SUBSCRIPTION_HISTORY, $entry->source);
		$this->assertTrue($entry->isSubscriptionBilling());
		$this->assertSame('sub1', $entry->subscriptionUUID);
	}

	#[Test]
	public function it_hydrates_a_refund(): void
	{
		$refund = RefundEntry::fromArray([
			'uuid' => 'r1',
			'txId' => 'pay@p1',
			'test' => true,
			'reason' => 'Changed my mind',
			'status' => 'pending',
			'createdAt' => '2026-01-01T00:00:00Z',
			'respondedAt' => null,
			'amountMinUnits' => '10000000',
		]);

		$this->assertSame(RefundStatus::PENDING, $refund->status);
		$this->assertTrue($refund->isPending());
		$this->assertNull($refund->respondedAt);
		$this->assertNull($refund->txHash);
	}

	#[Test]
	public function it_hydrates_an_accounting_event(): void
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
		$this->assertNull($event->networkFeesUsd, 'Network fees are absent unless QBitFlow paid them.');
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
		], $currency->toArray());

		$this->assertSame('{"id":1,"symbol":"BTC","name":"Bitcoin","decimals":8,"test":false}', json_encode($currency));
	}
}
