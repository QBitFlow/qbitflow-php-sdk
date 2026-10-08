<?php

declare(strict_types=1);

namespace QBitFlow\Enums;

/**
 * A failed attempt's status.
 *
 * An open enum: the API's value is kept as a plain `string`, and a value this SDK does not
 * know yet is kept as is, never rejected. Compare against these constants.
 */
final class AttemptStatus
{
	/** Not sent, not confirmed, or failed on the network: nothing was paid. */
	public const FAILED = 'failed';

	/**
	 * Every value this SDK knows.
	 *
	 * @return list<string>
	 */
	public static function values(): array
	{
		return [
			self::FAILED,
		];
	}

	private function __construct()
	{
	}
}
