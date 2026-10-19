<?php

declare(strict_types=1);

namespace QBitFlow\Models;

use DateTimeImmutable;
use QBitFlow\Support\Cast;

/**
 * `subscription.upcomingBill`'s data: a bill (or a trial's end) is near, sent once per bill some
 * days before (3 by default). Remind the customer when `balanceSufficient` or
 * `allowanceSufficient` is false, or when `trialEnding`.
 */
final readonly class SubscriptionUpcomingBill extends Subscription
{
	/**
	 * When it is billed (for a trial: when the trial ends).
	 */
	public DateTimeImmutable $billingAt;

	/**
	 * What the bill charges, in USD, a decimal string: its `priceUsd` plus its `fees` lines.
	 */
	public string $amountUsd;

	/**
	 * True when the trial ends then: the customer confirms the subscription (its
	 * `managementPageLink`) to be billed.
	 */
	public bool $trialEnding;

	/**
	 * Whether the wallet holds the bill at today's price; null for a trial, or when it couldn't
	 * be checked.
	 */
	public ?bool $balanceSufficient;

	/**
	 * Whether the remaining allowance covers the bill; null for a trial, or without a price.
	 */
	public ?bool $allowanceSufficient;

	/**
	 * @param array<string,mixed> $data
	 */
	protected function __construct(array $data)
	{
		parent::__construct($data);
		$this->billingAt = Cast::date($data, 'billingAt');
		$this->amountUsd = Cast::string($data, 'amountUsd');
		$this->trialEnding = Cast::bool($data, 'trialEnding');
		$this->balanceSufficient = Cast::nullableBool($data, 'balanceSufficient');
		$this->allowanceSufficient = Cast::nullableBool($data, 'allowanceSufficient');
	}
}
