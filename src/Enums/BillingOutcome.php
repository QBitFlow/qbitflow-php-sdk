<?php

declare(strict_types=1);

namespace QBitFlow\Enums;

/**
 * How a finished billing run ended (`BillingState::$outcome`).
 *
 * An open enum: the API's value is kept as a plain `string`, and a value this SDK does not
 * know yet is kept as is, never rejected. Compare against these constants.
 */
final class BillingOutcome
{
	public const PAID = 'paid';

	/** Nothing to bill: paid already, cancelled, or gone. */
	public const NOT_DUE = 'notDue';

	/** Below the minimum amount: the period moved on without a charge. */
	public const SKIPPED = 'skipped';

	/** The subscription was cancelled. */
	public const CANCELLED = 'cancelled';

	/** Its customer paused the subscription: not charged. */
	public const PAUSED = 'paused';

	/**
	 * Every value this SDK knows.
	 *
	 * @return list<string>
	 */
	public static function values(): array
	{
		return [
			self::PAID,
			self::NOT_DUE,
			self::SKIPPED,
			self::CANCELLED,
			self::PAUSED,
		];
	}

	private function __construct()
	{
	}
}
