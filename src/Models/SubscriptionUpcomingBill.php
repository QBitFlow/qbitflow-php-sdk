<?php

declare(strict_types=1);

namespace QBitFlow\Models;

use DateTimeImmutable;
use QBitFlow\Support\Cast;

/**
 * `subscription.upcomingBill`'s data: a bill (or a trial's end) is near.
 */
final readonly class SubscriptionUpcomingBill extends Subscription
{
	/**
	 * When it is billed (for a trial: when the trial ends).
	 */
	public DateTimeImmutable $billingDate;

	/**
	 * The bill, in USD (a number, unlike SubscriptionBillingFailed's).
	 */
	public float $amountUsd;

	/**
	 * True when the trial ends then: the customer must confirm it to be billed.
	 */
	public bool $trialEnding;

	/**
	 * Whether the wallet holds the bill; null for a trial.
	 */
	public ?bool $balanceSufficient;

	/**
	 * Whether the remaining allowance covers the bill; null for a trial.
	 */
	public ?bool $allowanceSufficient;

	/**
	 * @param array<string,mixed> $data
	 */
	protected function __construct(array $data)
	{
		parent::__construct($data);
		$this->billingDate = Cast::date($data, 'billingDate');
		$this->amountUsd = Cast::float($data, 'amountUsd');
		$this->trialEnding = Cast::bool($data, 'trialEnding');
		$this->balanceSufficient = Cast::nullableBool($data, 'balanceSufficient');
		$this->allowanceSufficient = Cast::nullableBool($data, 'allowanceSufficient');
	}
}
