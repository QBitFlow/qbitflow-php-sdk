<?php

declare(strict_types=1);

namespace QBitFlow\Models;

use QBitFlow\Support\Cast;

/**
 * A subscription checkout session, as `checkout.expired` sends it.
 */
final readonly class SubscriptionSessionData extends PaymentSessionData
{
	/**
	 * How often it bills.
	 */
	public Duration $frequency;

	/**
	 * The free trial; null without one.
	 */
	public ?Duration $trialPeriod;

	/**
	 * The periods the customer commits to; null without a minimum.
	 */
	public ?int $minPeriods;

	/**
	 * True when it upgrades a trial.
	 */
	public ?bool $upgradingFromTrial;

	/**
	 * @param array<string,mixed> $data
	 */
	protected function __construct(array $data)
	{
		parent::__construct($data);
		$this->frequency = Cast::object($data, 'frequency', Duration::fromArray(...));
		$this->trialPeriod = Cast::nullableObject($data, 'trialPeriod', Duration::fromArray(...));
		$this->minPeriods = Cast::nullableUint($data, 'minPeriods', 4294967295);
		$this->upgradingFromTrial = Cast::nullableBool($data, 'upgradingFromTrial');
	}
}
