<?php

declare(strict_types=1);

namespace QBitFlow\Enums;

/**
 * A Duration's unit (months = 30 days, years = 365 days).
 *
 * An open enum: the API's value is kept as a plain `string`, and a value this SDK does not
 * know yet is kept as is, never rejected. Compare against these constants.
 */
final class DurationUnit
{
	/** Seconds. */
	public const SECONDS = 'seconds';

	/** Minutes. */
	public const MINUTES = 'minutes';

	/** Hours. */
	public const HOURS = 'hours';

	/** Days. */
	public const DAYS = 'days';

	/** Weeks (7 days). */
	public const WEEKS = 'weeks';

	/** Months, counted as 30 days. */
	public const MONTHS = 'months';

	/** Years, counted as 365 days. */
	public const YEARS = 'years';

	/**
	 * Every value this SDK knows.
	 *
	 * @return list<string>
	 */
	public static function values(): array
	{
		return [
			self::SECONDS,
			self::MINUTES,
			self::HOURS,
			self::DAYS,
			self::WEEKS,
			self::MONTHS,
			self::YEARS,
		];
	}

	private function __construct()
	{
	}
}
