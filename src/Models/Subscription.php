<?php

declare(strict_types=1);

namespace QBitFlow\Models;

use DateTimeImmutable;
use QBitFlow\Support\Cast;

/**
 * A subscription. Grant access while now < `currentPeriodEnd`.
 */
readonly class Subscription extends Model
{
	/**
	 * The subscription's id (`sub@…`), its checkout session's for life.
	 */
	public string $uuid;

	/**
	 * The merchant's reference, set when creating its checkout.
	 */
	public ?string $reference;

	/**
	 * When it was created.
	 */
	public DateTimeImmutable $createdAt;

	/**
	 * When it last changed.
	 */
	public DateTimeImmutable $updatedAt;

	/**
	 * The subscriber's wallet.
	 */
	public string $from;

	/**
	 * The wallet that receives the bills.
	 */
	public string $to;

	/**
	 * The subscription's product.
	 */
	public string $productUuid;

	/**
	 * The transaction that created it on-chain (`''` on a trial until confirmed).
	 */
	public string $subscriptionHash;

	/**
	 * The currency billed.
	 */
	public int $currencyId;

	/**
	 * That currency.
	 */
	public ?Currency $currency;

	/**
	 * The billing interval.
	 */
	public Duration $frequency;

	/**
	 * What remains of the allowance the subscriber granted, in the token's min units (a decimal string).
	 */
	public string $allowance;

	/**
	 * `trial`, `trialExpired`, `active`, `pastDue`, `paused`, `stopped` or `cancelled` ({@see \QBitFlow\Enums\SubscriptionStatus}).
	 */
	public string $status;

	/**
	 * What its customer must do (`topUpAllowance`, `raiseMaximum`, `confirmTrial`); null for nothing.
	 */
	public ?string $actionRequired;

	/**
	 * The end of the period paid for (or of the trial): grant access while now < currentPeriodEnd.
	 */
	public ?DateTimeImmutable $currentPeriodEnd;

	/**
	 * The price per period the customer subscribed at, in USD (a decimal string; `"0"` for old v1 subscriptions).
	 */
	public string $priceUsd;

	/**
	 * The customer's maximum per period, in min units (a decimal string); null on trials and old subscriptions.
	 */
	public ?string $maxAmountPerPeriod;

	/**
	 * The date of the last bill.
	 */
	public DateTimeImmutable $lastBillingDate;

	/**
	 * When the next bill is due (a trial: when it ends; stopped: when it is cancelled); null once cancelled.
	 */
	public ?DateTimeImmutable $nextBillingDate;

	/**
	 * When it was cancelled.
	 */
	public ?DateTimeImmutable $cancelledAt;

	/**
	 * Why it stopped or was cancelled ({@see \QBitFlow\Enums\CancellationReason}).
	 */
	public ?string $cancellationReason;

	/**
	 * The earliest date it can be cancelled (minPeriods).
	 */
	public ?DateTimeImmutable $minimumCancellationDate;

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
	 * The merchant's reference of the customer.
	 */
	public ?string $customerReference;

	/**
	 * Names the customer (API reads only, never in webhooks).
	 */
	public ?CustomerSummary $customer;

	/**
	 * The failing bill's retries, while past due (API reads only).
	 */
	public ?DunningStatus $dunning;

	/**
	 * @param array<string,mixed> $data
	 */
	protected function __construct(array $data)
	{
		$this->uuid = Cast::string($data, 'uuid');
		$this->reference = Cast::nullableString($data, 'reference');
		$this->createdAt = Cast::date($data, 'createdAt');
		$this->updatedAt = Cast::date($data, 'updatedAt');
		$this->from = Cast::string($data, 'from');
		$this->to = Cast::string($data, 'to');
		$this->productUuid = Cast::string($data, 'productUuid');
		$this->subscriptionHash = Cast::string($data, 'subscriptionHash');
		$this->currencyId = Cast::uint($data, 'currencyId');
		$this->currency = Cast::nullableObject($data, 'currency', Currency::fromArray(...));
		$this->frequency = Cast::object($data, 'frequency', Duration::fromArray(...));
		$this->allowance = Cast::string($data, 'allowance');
		$this->status = Cast::string($data, 'status');
		$this->actionRequired = Cast::nullableString($data, 'actionRequired');
		$this->currentPeriodEnd = Cast::nullableDate($data, 'currentPeriodEnd');
		$this->priceUsd = Cast::string($data, 'priceUsd');
		$this->maxAmountPerPeriod = Cast::nullableString($data, 'maxAmountPerPeriod');
		$this->lastBillingDate = Cast::date($data, 'lastBillingDate');
		$this->nextBillingDate = Cast::nullableDate($data, 'nextBillingDate');
		$this->cancelledAt = Cast::nullableDate($data, 'cancelledAt');
		$this->cancellationReason = Cast::nullableString($data, 'cancellationReason');
		$this->minimumCancellationDate = Cast::nullableDate($data, 'minimumCancellationDate');
		$this->test = Cast::bool($data, 'test');
		$this->userUuid = Cast::nullableString($data, 'userUuid');
		$this->customerUuid = Cast::nullableString($data, 'customerUuid');
		$this->customerReference = Cast::nullableString($data, 'customerReference');
		$this->customer = Cast::nullableObject($data, 'customer', CustomerSummary::fromArray(...));
		$this->dunning = Cast::nullableObject($data, 'dunning', DunningStatus::fromArray(...));
	}
}
