<?php

declare(strict_types=1);

namespace QBitFlow\Enums;

/**
 * A subscription's lifecycle status.
 *
 * An open enum: the API's value is kept as a plain `string`, and a value this SDK does not
 * know yet is kept as is, never rejected. Compare against these constants.
 */
final class SubscriptionStatus
{
	/** In its free trial. */
	public const TRIAL = 'trial';

	/** The trial ended without its customer confirming it. */
	public const TRIAL_EXPIRED = 'trialExpired';

	/** Billed normally. */
	public const ACTIVE = 'active';

	/** A bill failed: retried (dunning) until it is paid or the subscription cancelled. */
	public const PAST_DUE = 'pastDue';

	/** Paused by its customer: not billed until resumed. */
	public const PAUSED = 'paused';

	/** Stopped: cancelled at the end of the current period. */
	public const STOPPED = 'stopped';

	/** Cancelled. Final. */
	public const CANCELLED = 'cancelled';

	/**
	 * Every value this SDK knows.
	 *
	 * @return list<string>
	 */
	public static function values(): array
	{
		return [
			self::TRIAL,
			self::TRIAL_EXPIRED,
			self::ACTIVE,
			self::PAST_DUE,
			self::PAUSED,
			self::STOPPED,
			self::CANCELLED,
		];
	}

	private function __construct()
	{
	}
}
