<?php

declare(strict_types=1);

namespace QBitFlow\Enums;

/**
 * Where a billing run is (`BillingState::$stage`).
 *
 * An open enum: the API's value is kept as a plain `string`, and a value this SDK does not
 * know yet is kept as is, never rejected. Compare against these constants.
 */
final class BillingStage
{
	/** Being planned, charged or recorded. */
	public const RUNNING = 'running';

	/** Waiting for its next attempt, its due date, or the end of a trial's grace period. */
	public const WAITING = 'waiting';

	/** Over the customer's maximum: waiting for their new one. */
	public const PENDING = 'pending';

	public const DONE = 'done';

	/**
	 * Every value this SDK knows.
	 *
	 * @return list<string>
	 */
	public static function values(): array
	{
		return [
			self::RUNNING,
			self::WAITING,
			self::PENDING,
			self::DONE,
		];
	}

	private function __construct()
	{
	}
}
