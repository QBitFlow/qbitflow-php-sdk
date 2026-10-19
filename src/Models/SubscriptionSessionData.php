<?php

declare(strict_types=1);

namespace QBitFlow\Models;

use QBitFlow\Support\Cast;

/**
 * A subscription's checkout session, as `checkout.expired` sends it: the payment session's
 * fields and its terms.
 */
final readonly class SubscriptionSessionData extends PaymentSessionData
{
	/**
	 * How often it would have billed.
	 */
	public Duration $frequency;

	/**
	 * The free trial; null without one.
	 */
	public ?Duration $trialPeriod;

	/**
	 * The periods the customer would have committed to; null without a minimum.
	 */
	public ?int $minPeriods;

	/**
	 * True for a checkout confirming a free trial already started (always false in
	 * `checkout.expired`: such a session sends nothing when it expires).
	 */
	public bool $upgradingFromTrial;

	/**
	 * @param array<string,mixed> $data
	 */
	protected function __construct(array $data)
	{
		parent::__construct($data);
		$this->frequency = Cast::object($data, 'frequency', Duration::fromArray(...));
		$this->trialPeriod = Cast::nullableObject($data, 'trialPeriod', Duration::fromArray(...));
		$this->minPeriods = Cast::nullableUint($data, 'minPeriods', 4294967295);
		$this->upgradingFromTrial = Cast::bool($data, 'upgradingFromTrial');
	}
}
