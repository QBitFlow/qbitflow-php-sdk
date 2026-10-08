<?php

declare(strict_types=1);

namespace QBitFlow\Enums;

/**
 * What a failure means, from its code.
 *
 * An open enum: the API's value is kept as a plain `string`, and a value this SDK does not
 * know yet is kept as is, never rejected. Compare against these constants.
 */
final class FailureCategory
{
	/** The customer's wallet couldn't pay. */
	public const INSUFFICIENT_BALANCE = 'insufficientBalance';

	/** A subscription's allowance is used up or expired. */
	public const ALLOWANCE_EXHAUSTED = 'allowanceExhausted';

	/** The wallet no longer lets the subscription pull the token. */
	public const APPROVAL_REVOKED = 'approvalRevoked';

	/** A bill above the customer's maximum per period. */
	public const MAX_AMOUNT_EXCEEDED = 'maxAmountExceeded';

	/** Sent and reverted on-chain. */
	public const REVERTED = 'reverted';

	/** Sent, never taken or confirmed in time. */
	public const NOT_CONFIRMED = 'notConfirmed';

	/** The customer's signature was refused. */
	public const SIGNATURE_REJECTED = 'signatureRejected';

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
			self::REVERTED,
			self::NOT_CONFIRMED,
			self::SIGNATURE_REJECTED,
			self::OTHER,
		];
	}

	private function __construct()
	{
	}
}
