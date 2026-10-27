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
	/** The customer's wallet doesn't hold the bill: they add funds to it. */
	public const INSUFFICIENT_BALANCE = 'insufficientBalance';

	/**
	 * What remains of the allowance the customer signed doesn't cover the bill (or it expired):
	 * they top it up on the management page.
	 */
	public const ALLOWANCE_EXHAUSTED = 'allowanceExhausted';

	/**
	 * The customer's wallet revoked the token approval the subscription pulls through: they
	 * approve it again on the management page.
	 */
	public const APPROVAL_REVOKED = 'approvalRevoked';

	/**
	 * The bill, at today's price, exceeds the maximum the customer signed: they sign a new maximum
	 * on the management page.
	 */
	public const MAX_AMOUNT_EXCEEDED = 'maxAmountExceeded';

	/**
	 * Another refusal of the charge caused by the customer's wallet (e.g. a transaction reverted
	 * on-chain); retried like the others.
	 */
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
