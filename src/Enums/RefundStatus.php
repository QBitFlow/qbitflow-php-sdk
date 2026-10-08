<?php

declare(strict_types=1);

namespace QBitFlow\Enums;

/**
 * A refund's status.
 *
 * An open enum: the API's value is kept as a plain `string`, and a value this SDK does not
 * know yet is kept as is, never rejected. Compare against these constants.
 */
final class RefundStatus
{
	public const PENDING = 'pending';

	public const APPROVED = 'approved';

	public const REJECTED = 'rejected';

	/**
	 * Every value this SDK knows.
	 *
	 * @return list<string>
	 */
	public static function values(): array
	{
		return [
			self::PENDING,
			self::APPROVED,
			self::REJECTED,
		];
	}

	private function __construct()
	{
	}
}
