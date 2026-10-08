<?php

declare(strict_types=1);

namespace QBitFlow\Models;

use QBitFlow\Support\Cast;

/**
 * A subscription product's terms.
 */
final readonly class SubscriptionTerms extends Model
{
	/**
	 * How often it bills (e.g. 1 month: 30 days).
	 */
	public Duration $frequency;

	/**
	 * A free trial before the first bill; null without one.
	 */
	public ?Duration $trialPeriod;

	/**
	 * The periods the customer commits to before cancelling; null without a minimum.
	 */
	public ?int $minPeriods;

	/**
	 * @param array<string,mixed> $data
	 */
	protected function __construct(array $data)
	{
		$this->frequency = Cast::object($data, 'frequency', Duration::fromArray(...));
		$this->trialPeriod = Cast::nullableObject($data, 'trialPeriod', Duration::fromArray(...));
		$this->minPeriods = Cast::nullableUint($data, 'minPeriods', 4294967295);
	}
}
