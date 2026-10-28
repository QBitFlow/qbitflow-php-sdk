<?php

declare(strict_types=1);

namespace QBitFlow\Enums;

/**
 * What a subscription's customer must do (`null` = nothing).
 *
 * An open enum: the API's value is kept as a plain `string`, and a value this SDK does not
 * know yet is kept as is, never rejected. Compare against these constants.
 */
final class ActionRequired
{
	/** The allowance is too low for the next bill. */
	public const TOP_UP_ALLOWANCE = 'topUpAllowance';

	/** The bill is above the customer's maximum per period. */
	public const RAISE_MAXIMUM = 'raiseMaximum';

	/** The trial must be confirmed to be billed. */
	public const CONFIRM_TRIAL = 'confirmTrial';

	/**
	 * Every value this SDK knows.
	 *
	 * @return list<string>
	 */
	public static function values(): array
	{
		return [
			self::TOP_UP_ALLOWANCE,
			self::RAISE_MAXIMUM,
			self::CONFIRM_TRIAL,
		];
	}

	private function __construct()
	{
	}
}
