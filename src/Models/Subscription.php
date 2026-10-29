<?php

declare(strict_types=1);

namespace QBitFlow\Models;

use DateTimeImmutable;
use DateTimeInterface;
use QBitFlow\Support\Cast;

/**
 * A subscription (`sub@…`). Grant access while now < `accessUntil`, whatever the status
 * ({@see hasAccess()}): the status says why, `actionRequired` what its customer must do.
 */
readonly class Subscription extends Model
{
	/**
	 * The subscription's id (`sub@…`), its checkout session's for life.
	 */
	public string $uuid;

	/**
	 * Your reference for the subscription (an order or customer id), set when creating its checkout.
	 */
	public ?string $reference;

	/**
	 * When it was created.
	 */
	public DateTimeImmutable $createdAt;

	/**
	 * Its last change (a bill, a status, its metadata).
	 */
	public DateTimeImmutable $updatedAt;

	/**
	 * The subscriber's wallet, the one it bills; null (absent) when unknown (a trial not
	 * confirmed yet may have none).
	 */
	public ?string $from;

	/**
	 * The wallet its bills pay: the merchant's (or, while the organization holds a member's
	 * funds, the organization's).
	 */
	public string $to;

	/**
	 * Its product (`products->get()`); null (absent) without one.
	 */
	public ?string $productUuid;

	/**
	 * The transaction that created it on-chain; null (absent) until then (a trial, until its
	 * customer confirms it).
	 */
	public ?string $subscriptionHash;

	/**
	 * The currency (token) billed.
	 */
	public Currency $currency;

	/**
	 * The billing interval.
	 */
	public Duration $frequency;

	/**
	 * `trial`, `trialExpired`, `active`, `pastDue`, `paused`, `stopped` or `cancelled`
	 * ({@see \QBitFlow\Enums\SubscriptionStatus}). Every change sends `subscription.statusChanged`.
	 */
	public string $status;

	/**
	 * What its customer must do (`topUpAllowance`, `raiseMaximum`, `confirmTrial`;
	 * {@see \QBitFlow\Enums\ActionRequired}); null (absent) for nothing. Every change sends
	 * `subscription.actionRequiredChanged`.
	 */
	public ?string $actionRequired;

	/**
	 * The paid-through date, to display ("paid through"). A trial: its end; active, paused,
	 * stopped: the end of the period paid for; pastDue: the start of the unpaid period (past);
	 * cancelled: when it was cancelled, or the end of its last paid period if that came first.
	 * For access, read `accessUntil`: an active subscription is renewed a few minutes after this
	 * date. Null when absent.
	 */
	public ?DateTimeImmutable $currentPeriodEnd;

	/**
	 * When access ends: grant it while now < `accessUntil`, whatever the status
	 * ({@see hasAccess()}). An active subscription: `currentPeriodEnd` plus 1 hour, since its
	 * renewal is billed within minutes of its due date (a subscriber whose bill is on its way keeps
	 * access; a failed bill makes it pastDue). Every other status: `currentPeriodEnd`. Null on a
	 * subscription stored before the API sent it ({@see hasAccess()} then reads `currentPeriodEnd`).
	 */
	public ?DateTimeImmutable $accessUntil;

	/**
	 * The price per period the customer subscribed at, in USD (a decimal string, `"9.99"`),
	 * locked at signup (a free trial's too); never `"0"`. Every bill charges it plus `fees`,
	 * converted to the token at the bill's price.
	 */
	public string $priceUsd;

	/**
	 * What its checkout added to the price, line by line, as the customer saw them (a tax,
	 * shipping, the processing fee when the customer pays it), locked at signup with the price:
	 * every bill charges `priceUsd` plus these lines. `[]` without any.
	 *
	 * @var list<FeeLine>
	 */
	public array $fees;

	/**
	 * What the subscriber's wallet still lets QBitFlow bill, and the most one bill may charge,
	 * at today's price; null (absent) during a free trial (nothing signed yet).
	 */
	public ?Allowance $allowance;

	/**
	 * When it was last billed; null (absent) before its first bill (a trial).
	 */
	public ?DateTimeImmutable $lastBilledAt;

	/**
	 * When its next bill is due (a trial: when it ends; stopped: when it is cancelled); null
	 * (absent) once cancelled.
	 */
	public ?DateTimeImmutable $nextBillingAt;

	/**
	 * When it was cancelled; null until then.
	 */
	public ?DateTimeImmutable $cancelledAt;

	/**
	 * Why it stopped or was cancelled ({@see \QBitFlow\Enums\CancellationReason}); null while it runs.
	 */
	public ?string $cancellationReason;

	/**
	 * The earliest its customer may cancel it (the checkout's `minPeriods`); null when they may
	 * cancel it any time.
	 */
	public ?DateTimeImmutable $cancellableAt;

	/**
	 * Your own data: its checkout session's, copied when the subscription was created, then
	 * changed with `subscriptions->update()` (merged). `[]` without any.
	 *
	 * @var array<string,string>
	 */
	public array $metadata;

	/**
	 * The subscription's page for its customer: where they top up its allowance, raise its
	 * maximum, pause, resume or cancel it, and confirm a trial. Share it with them.
	 */
	public ?string $managementPageLink;

	/**
	 * True in test mode.
	 */
	public bool $test;

	/**
	 * The member whose space it is in; null for the organization's own.
	 */
	public ?string $userUuid;

	/**
	 * The customer, when QBitFlow has one.
	 */
	public ?string $customerUuid;

	/**
	 * Your reference of the customer, as the checkout was given it.
	 */
	public ?string $customerReference;

	/**
	 * Names the customer, even once deleted (API reads only).
	 */
	public ?CustomerSummary $customer;

	/**
	 * Where its failing bill's retries stand (API reads only: webhooks tell each failure with
	 * `subscription.billingFailed`); null while no bill is failing.
	 */
	public ?DunningStatus $dunning;

	/**
	 * Whether the subscription grants access at `$at` (default: now): `$at` is before
	 * `accessUntil`, whatever the status (a cancelled subscription keeps access to the end of the
	 * period paid for; an active one an hour past `currentPeriodEnd`, while its renewal is billed).
	 * Without `accessUntil` (a subscription stored before the API sent it), it reads
	 * `currentPeriodEnd`. False when neither is set.
	 */
	public function hasAccess(?DateTimeInterface $at = null): bool
	{
		$until = $this->accessUntil ?? $this->currentPeriodEnd;
		return $until !== null && ($at ?? new DateTimeImmutable()) < $until;
	}

	/**
	 * @param array<string,mixed> $data
	 */
	protected function __construct(array $data)
	{
		$this->uuid = Cast::string($data, 'uuid');
		$this->reference = Cast::nullableString($data, 'reference');
		$this->createdAt = Cast::date($data, 'createdAt');
		$this->updatedAt = Cast::date($data, 'updatedAt');
		$this->from = Cast::nullableString($data, 'from');
		$this->to = Cast::string($data, 'to');
		$this->productUuid = Cast::nullableString($data, 'productUuid');
		$this->subscriptionHash = Cast::nullableString($data, 'subscriptionHash');
		$this->currency = Cast::object($data, 'currency', Currency::fromArray(...));
		$this->frequency = Cast::object($data, 'frequency', Duration::fromArray(...));
		$this->status = Cast::string($data, 'status');
		$this->actionRequired = Cast::nullableString($data, 'actionRequired');
		$this->currentPeriodEnd = Cast::nullableDate($data, 'currentPeriodEnd');
		$this->accessUntil = Cast::nullableDate($data, 'accessUntil');
		$this->priceUsd = Cast::string($data, 'priceUsd');
		$this->fees = Cast::listOf($data, 'fees', FeeLine::fromArray(...));
		$this->allowance = Cast::nullableObject($data, 'allowance', Allowance::fromArray(...));
		$this->lastBilledAt = Cast::nullableDate($data, 'lastBilledAt');
		$this->nextBillingAt = Cast::nullableDate($data, 'nextBillingAt');
		$this->cancelledAt = Cast::nullableDate($data, 'cancelledAt');
		$this->cancellationReason = Cast::nullableString($data, 'cancellationReason');
		$this->cancellableAt = Cast::nullableDate($data, 'cancellableAt');
		$this->metadata = Cast::stringMap($data, 'metadata');
		$this->managementPageLink = Cast::nullableString($data, 'managementPageLink');
		$this->test = Cast::bool($data, 'test');
		$this->userUuid = Cast::nullableString($data, 'userUuid');
		$this->customerUuid = Cast::nullableString($data, 'customerUuid');
		$this->customerReference = Cast::nullableString($data, 'customerReference');
		$this->customer = Cast::nullableObject($data, 'customer', CustomerSummary::fromArray(...));
		$this->dunning = Cast::nullableObject($data, 'dunning', DunningStatus::fromArray(...));
	}
}
