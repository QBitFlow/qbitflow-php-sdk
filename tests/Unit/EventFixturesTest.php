<?php

declare(strict_types=1);

namespace QBitFlow\Tests\Unit;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use QBitFlow\Enums\ActionRequired;
use QBitFlow\Enums\BillingFailureReason;
use QBitFlow\Enums\Chain;
use QBitFlow\Enums\DurationUnit;
use QBitFlow\Enums\EventType;
use QBitFlow\Enums\LedgerEntryType;
use QBitFlow\Enums\RefundInitiator;
use QBitFlow\Enums\RefundStatus;
use QBitFlow\Enums\SubscriptionStatus;
use QBitFlow\Enums\TransactionType;
use QBitFlow\Enums\TransferType;
use QBitFlow\Events;
use QBitFlow\Events\Event;
use QBitFlow\Models;
use QBitFlow\Tests\Support\TestCase;
use QBitFlow\Webhooks\Webhook;
use stdClass;

/**
 * The docs' example body of each event type (`docs/api` webhooks.md § Examples, generated from the
 * API's code; the Go reference's `testdata/events`, copied verbatim in `tests/Fixtures/events`):
 * every one parses, every key of its data is modelled, and the values come through typed.
 */
final class EventFixturesTest extends TestCase
{
	/** Each event type, its event class and its data model. */
	private const TYPES = [
		EventType::PAYMENT_COMPLETED => [Events\PaymentCompletedEvent::class, Models\PaymentCompleted::class],
		EventType::SUBSCRIPTION_CREATED => [Events\SubscriptionCreatedEvent::class, Models\Subscription::class],
		EventType::SUBSCRIPTION_BILLED => [Events\SubscriptionBilledEvent::class, Models\SubscriptionBilled::class],
		EventType::SUBSCRIPTION_STATUS_CHANGED => [Events\SubscriptionStatusChangedEvent::class, Models\SubscriptionStatusChanged::class],
		EventType::SUBSCRIPTION_ACTION_REQUIRED_CHANGED => [Events\SubscriptionActionRequiredChangedEvent::class, Models\SubscriptionActionRequiredChanged::class],
		EventType::SUBSCRIPTION_BILLING_FAILED => [Events\SubscriptionBillingFailedEvent::class, Models\SubscriptionBillingFailed::class],
		EventType::SUBSCRIPTION_UPCOMING_BILL => [Events\SubscriptionUpcomingBillEvent::class, Models\SubscriptionUpcomingBill::class],
		EventType::REFUND_REQUESTED => [Events\RefundRequestedEvent::class, Models\Refund::class],
		EventType::REFUND_APPROVED => [Events\RefundApprovedEvent::class, Models\Refund::class],
		EventType::REFUND_REJECTED => [Events\RefundRejectedEvent::class, Models\Refund::class],
		EventType::MEMBER_JOINED => [Events\MemberJoinedEvent::class, Models\MemberJoined::class],
		EventType::MEMBER_REMOVED => [Events\MemberRemovedEvent::class, Models\Member::class],
		EventType::HELD_FUNDS_RELEASED => [Events\HeldFundsReleasedEvent::class, Models\HeldFundsReleased::class],
		EventType::CHECKOUT_EXPIRED => [Events\CheckoutExpiredEvent::class, Models\PaymentSessionData::class],
		EventType::WEBHOOK_TEST => [Events\WebhookTestEvent::class, Models\WebhookTest::class],
	];

	private static function raw(string $type): string
	{
		return (string) file_get_contents(dirname(__DIR__) . '/Fixtures/events/' . $type . '.json');
	}

	private function load(string $type): Event
	{
		$event = Webhook::parseEvent(self::raw($type));
		$this->assertSame($type, $event->type);
		$this->assertSame('v2', $event->version);
		$this->assertStringStartsWith('evt_', $event->id);
		$this->assertGreaterThan(new DateTimeImmutable('2000-01-01'), $event->createdAt);

		return $event;
	}

	/** @return array<string,array{0: string}> */
	public static function types(): array
	{
		$out = [];
		foreach (array_keys(self::TYPES) as $type) {
			$out[$type] = [$type];
		}

		return $out;
	}

	#[Test]
	public function there_is_one_fixture_per_type(): void
	{
		$this->assertCount(15, EventType::values());
		$this->assertSame(EventType::values(), array_keys(self::TYPES));
		$this->assertCount(15, glob(dirname(__DIR__) . '/Fixtures/events/*.json') ?: []);
	}

	#[Test]
	#[DataProvider('types')]
	public function every_fixture_is_fully_modelled(string $type): void
	{
		[$eventClass, $dataClass] = self::TYPES[$type];
		$event = $this->load($type);
		$this->assertInstanceOf($eventClass, $event);
		$this->assertInstanceOf($dataClass, $event->data);
		$this->assertModelled(json_decode(self::raw($type), false, 512, JSON_THROW_ON_ERROR)->data, $event->data, $type);
	}

	/** Every key of the wire object is a property of the model, at every level (no unknown key). */
	private function assertModelled(mixed $wire, mixed $model, string $path): void
	{
		if ($wire instanceof stdClass && is_array($model)) {
			$this->assertSame(array_keys(get_object_vars($wire)), array_keys($model), $path);
		} elseif ($wire instanceof stdClass) {
			$this->assertIsObject($model, $path);
			foreach (get_object_vars($wire) as $key => $value) {
				$this->assertTrue(property_exists($model, $key), $path . '.' . $key . ' is modelled');
				$this->assertModelled($value, $model->{$key}, $path . '.' . $key);
			}
		} elseif (is_array($wire)) {
			$this->assertIsArray($model, $path);
			foreach ($wire as $i => $item) {
				$this->assertModelled($item, $model[$i], $path . '[' . $i . ']');
			}
		}
	}

	#[Test]
	public function payment_completed(): void
	{
		$p = $this->load(EventType::PAYMENT_COMPLETED)->data;
		$this->assertInstanceOf(Models\PaymentCompleted::class, $p);
		$this->assertSame(['pay@01a0f755-f200-7000-8000-000000000001', '10', '10000000', '10', 'order-1042', 'crm-5521', Chain::BASE, 'Pro plan (lifetime)'],
			[$p->uuid, $p->amount, $p->amountMinUnits, $p->amountUsd, $p->reference, $p->customerReference, $p->chain, $p->productName]);
		$this->assertNotNull($p->managementPageLink);
		$this->assertSame(['10.0042', '10004200', '10.0042'], [$p->paid, $p->paidMinUnits, $p->paidUsd]);
		$this->assertSame(['order-1042', 'C-5521'], [$p->metadata['orderId'], $p->metadata['crm:customer']]);
		$this->assertSame(['BASE:USDC', 'USDC', Chain::BASE, 3], [$p->currency->code, $p->currency->symbol, $p->currency->chain, $p->currency->mainCurrencyId]);
		$this->assertNotNull($p->currency->address);

		$st = $p->settlement;
		$this->assertSame(['0.99', '9901000', '0.099', '0.0042'], [$st->feePercent, $st->split?->merchant->amountMinUnits, $st->split?->platform->amount, $st->split?->networkFee?->amount]);
		$this->assertSame([null, null, null, null], [$st->split->organization, $st->split->referral, $st->organizationFee, $st->referralFee]);
		$this->assertSame(['52468103', 95000, '4512.37', null, '0.005144'],
			[$st->transaction?->blockNumber, $st->transaction?->gasUsed, $st->transaction?->nativePriceUsd, $st->transaction?->l1Fee, $st->transaction?->fee->amountUsd]);
		// Webhooks never carry the API-only fields.
		$this->assertSame([null, null, null, null], [$p->customer, $p->refund, $p->refundable, $p->notRefundableReason]);
		$this->assertNotNull($p->confirmedAt);
		$this->assertNotNull($p->checkoutOpenedAt);
		// The docs' payment has a price and no fees: amountUsd = priceUsd.
		$this->assertSame(['10', []], [$p->priceUsd, $p->fees]);
		$this->assertSame($p->amountUsd, $p->priceUsd);
	}

	#[Test]
	public function subscriptions(): void
	{
		$created = $this->load(EventType::SUBSCRIPTION_CREATED)->data;
		$this->assertInstanceOf(Models\Subscription::class, $created);
		$this->assertStringStartsWith('sub@', $created->uuid);
		$this->assertSame([1, DurationUnit::MONTHS, SubscriptionStatus::ACTIVE, '10', 'pro', 'customer-7731', 'BASE:USDC'],
			[$created->frequency->value, $created->frequency->unit, $created->status, $created->priceUsd, $created->metadata['plan'], $created->reference, $created->currency->code]);
		$this->assertNotNull($created->currentPeriodEnd);
		$this->assertNotNull($created->nextBillingAt);
		$this->assertNotNull($created->lastBilledAt);
		$this->assertNotNull($created->managementPageLink);
		$this->assertNull($created->dunning);
		$this->assertSame(['110', 11, '30000000'], [$created->allowance?->amount, $created->allowance?->remainingPeriods, $created->allowance?->maxPerPeriodMinUnits]);
		$this->assertTrue($created->hasAccess(new DateTimeImmutable('2026-10-30T00:00:00Z')));
		$this->assertFalse($created->hasAccess($created->currentPeriodEnd), 'equal: no access');

		$billed = $this->load(EventType::SUBSCRIPTION_BILLED)->data;
		$this->assertInstanceOf(Models\SubscriptionBilled::class, $billed);
		[$b, $sub] = [$billed->bill, $billed->subscription];
		$this->assertStringStartsWith('sub-hist@', $b->uuid);
		$this->assertSame([$sub->uuid, '10', 'Pro plan', '0.99', '10.0042'], [$b->subscriptionUuid, $b->amount, $b->productName, $b->settlement->feePercent, $b->paid]);
		$this->assertNotNull($b->periodStart);
		$this->assertNotNull($b->settlement->split);
		$this->assertSame([SubscriptionStatus::ACTIVE, 'customer-7731', 'A-1042'], [$sub->status, $sub->reference, $sub->metadata['crm:account']]);
		$this->assertEquals($b->periodEnd, $sub->nextBillingAt, 'the next bill is due when this one\'s period ends');

		$changed = $this->load(EventType::SUBSCRIPTION_STATUS_CHANGED)->data;
		$this->assertSame([SubscriptionStatus::PAST_DUE, SubscriptionStatus::ACTIVE], [$changed->previousStatus, $changed->status]);
		$this->assertNotNull($changed->managementPageLink);

		$action = $this->load(EventType::SUBSCRIPTION_ACTION_REQUIRED_CHANGED)->data;
		$this->assertSame([ActionRequired::TOP_UP_ALLOWANCE, null, 0], [$action->actionRequired, $action->previousActionRequired, $action->allowance?->remainingPeriods]);

		$failed = $this->load(EventType::SUBSCRIPTION_BILLING_FAILED)->data;
		$this->assertSame([BillingFailureReason::INSUFFICIENT_BALANCE, '10', 1, 4, 'sub-hist@01a0f755-f200-7000-8000-000000000005', 'insufficient_funds', SubscriptionStatus::PAST_DUE],
			[$failed->reason, $failed->amountUsd, $failed->attempt, $failed->remainingAttempts, $failed->billUuid, $failed->failureCode, $failed->status]);
		$this->assertNotNull($failed->nextAttemptAt);
		$this->assertNotNull($failed->managementPageLink);

		$upcoming = $this->load(EventType::SUBSCRIPTION_UPCOMING_BILL)->data;
		$this->assertSame(['10', false, true], [$upcoming->amountUsd, $upcoming->trialEnding, $upcoming->balanceSufficient]);
		$this->assertSame('2026-10-04T12:00:00+00:00', $upcoming->billingAt->format(DATE_ATOM));
		$this->assertNotNull($upcoming->allowanceSufficient);
	}

	#[Test]
	public function refunds(): void
	{
		foreach ([EventType::REFUND_REQUESTED => RefundStatus::PENDING, EventType::REFUND_APPROVED => RefundStatus::APPROVED, EventType::REFUND_REJECTED => RefundStatus::REJECTED] as $type => $status) {
			$r = $this->load($type)->data;
			$this->assertInstanceOf(Models\Refund::class, $r, $type);
			$this->assertSame([$status, RefundInitiator::CUSTOMER, '10.0042', 'BASE:USDC'], [$r->status, $r->initiatedBy, $r->paid, $r->currency->code], $type);
			$this->assertStringStartsWith('refund@', $r->uuid);
			$this->assertStringStartsWith('pay@', $r->txUuid);
			$this->assertNotSame('', $r->refundPercent);
			$this->assertNotNull($r->reason);
			$this->assertSame($status === RefundStatus::PENDING, $r->respondedAt === null, $type . ': respondedAt');
			$this->assertSame([null, null, null, null], [$r->approval, $r->customer, $r->customerUuid, $r->productName], $type . ': no API-only field');
		}
		$approved = $this->load(EventType::REFUND_APPROVED)->data;
		$this->assertSame(['50', '5.0021', null], [$approved->refundPercent, $approved->amount, $approved->merchantMessage]);
		$this->assertNotNull($approved->settlement?->transaction);
		$this->assertNotNull($approved->txHash);
		$this->assertNotNull($approved->explorerUrl);
		$rejected = $this->load(EventType::REFUND_REJECTED)->data;
		$this->assertNotNull($rejected->merchantMessage);
		$this->assertSame([null, null], [$rejected->settlement, $rejected->txHash]);
		$requested = $this->load(EventType::REFUND_REQUESTED)->data;
		$this->assertSame('100', $requested->refundPercent);
		$this->assertSame($requested->paid, $requested->amount);
	}

	#[Test]
	public function members_and_held_funds(): void
	{
		$joined = $this->load(EventType::MEMBER_JOINED)->data;
		$this->assertInstanceOf(Models\MemberJoined::class, $joined);
		$this->assertSame(['Alice', '5', null, 1, 8], [$joined->firstName, $joined->organizationFeePercent, $joined->trustedAt, count($joined->acceptedCurrencies), $joined->acceptedCurrencies[0]->id]);
		$this->assertNotSame('', $joined->invitationUuid);
		$this->assertNotSame('', $joined->spaceUuid);

		$removed = $this->load(EventType::MEMBER_REMOVED)->data;
		$this->assertSame('Martin', $removed->lastName);
		$this->assertNotNull($removed->trustedAt);

		$event = $this->load(EventType::HELD_FUNDS_RELEASED);
		$this->assertSame('019eca82-5680-7b00-8000-0000000000b1', $event->userUuid);
		$released = $event->data;
		$this->assertInstanceOf(Models\HeldFundsReleased::class, $released);
		$this->assertSame([true, TransferType::HELD_FUNDS_RELEASE, 3, '98.9068', '98.9068', 'BASE:USDC'],
			[$released->received, $released->type, count($released->ledgers), $released->amount, $released->amountUsd, $released->currency->code]);
		$this->assertStringStartsWith('transfer@', $released->uuid);
		$this->assertNotNull($released->settlement->transaction);
		$first = $released->ledgers[0];
		$this->assertSame([LedgerEntryType::PAYMENT, '99.01', '99010000', '99.01', '100'], [$first->type, $first->owed, $first->owedMinUnits, $first->owedUsd, $first->amountUsd]);
		$this->assertNotNull($first->settlement?->split);
		$refund = $released->ledgers[2];
		$this->assertSame([LedgerEntryType::REFUND, null], [$refund->type, $refund->settlement]);
		$this->assertNotNull($refund->refundedTxUuid);
		$this->assertStringStartsWith('-', $refund->owedMinUnits);
		// amount is the sum of the lines' owed (exact, in min units).
		$this->assertSame($released->amountMinUnits, (string) array_sum(array_map(static fn (Models\LedgerEntry $l): int => (int) $l->owedMinUnits, $released->ledgers)));
	}

	#[Test]
	public function checkout_expired(): void
	{
		$p = $this->load(EventType::CHECKOUT_EXPIRED)->data;
		$this->assertInstanceOf(Models\PaymentSessionData::class, $p);
		$this->assertNotInstanceOf(Models\SubscriptionSessionData::class, $p);
		$this->assertSame([TransactionType::PAYMENT, '10', '10', 2, null, 'BASE:USDC', 'order-1043', 'Example Shop', 'order-1043', null, []],
			[$p->txType, $p->priceUsd, $p->amountUsd, count($p->availableCurrencies), $p->availableCurrencies[0]->address, $p->availableCurrencies[1]->code,
				$p->metadata['orderId'], $p->organizationName, $p->reference, $p->redirectUrl, $p->fees]);
		$this->assertNotNull($p->expiresAt);
		$this->assertNotNull($p->createdAt);
	}

	#[Test]
	public function webhook_test(): void
	{
		$w = $this->load(EventType::WEBHOOK_TEST)->data;
		$this->assertInstanceOf(Models\WebhookTest::class, $w);
		$this->assertNotSame('', $w->endpointUuid);
		$this->assertNotSame('', $w->message);
	}

	#[Test]
	public function decode_data_reads_another_shape(): void
	{
		$event = $this->load(EventType::SUBSCRIPTION_BILLING_FAILED);
		$plain = $event->decodeData(Models\Subscription::fromArray(...));
		$this->assertSame(Models\Subscription::class, $plain::class);
		$this->assertSame(SubscriptionStatus::PAST_DUE, $plain->status);
		$this->assertSame('10', $event->rawData['amountUsd']);
	}
}
