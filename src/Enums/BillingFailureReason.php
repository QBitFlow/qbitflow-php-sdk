<?php

declare(strict_types=1);

namespace QBitFlow\Enums;

/**
 * Why a bill failed (`subscription.billingFailed`).
 *
 * An open enum: the API's value is kept as a plain `string`, and a value this SDK does not
 * know yet is kept as is, never rejected. Compare against these constants.
 */
final class BillingFailureReason
{
	public const INSUFFICIENT_BALANCE = 'insufficientBalance';

	public const ALLOWANCE_EXHAUSTED = 'allowanceExhausted';

	public const APPROVAL_REVOKED = 'approvalRevoked';

	public const MAX_AMOUNT_EXCEEDED = 'maxAmountExceeded';

	public const OTHER = 'other';

	/**
	 * Every value this SDK knows.
	 *
	 * @return list<string>
	 */
	public static function values(): array
	{
		return [
			self::INSUFFICIENT_BALANCE,
			self::ALLOWANCE_EXHAUSTED,
			self::APPROVAL_REVOKED,
			self::MAX_AMOUNT_EXCEEDED,
			self::OTHER,
		];
	}

	private function __construct()
	{
	}
}
