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
	public const SECONDS = 'seconds';

	public const MINUTES = 'minutes';

	public const HOURS = 'hours';

	public const DAYS = 'days';

	public const WEEKS = 'weeks';

	public const MONTHS = 'months';

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
