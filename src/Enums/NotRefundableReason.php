<?php

declare(strict_types=1);

namespace QBitFlow\Enums;

/**
 * Why a transaction cannot be refunded now.
 *
 * An open enum: the API's value is kept as a plain `string`, and a value this SDK does not
 * know yet is kept as is, never rejected. Compare against these constants.
 */
final class NotRefundableReason
{
	/**
	 * The transaction has its refund (a customer's request, pending or answered, or a merchant's
	 * refund sent and confirmed): never refunded twice.
	 */
	public const REFUND_EXISTS = 'refundExists';

	/** Held for a member and released to them since. */
	public const HELD_FUNDS_RELEASED = 'heldFundsReleased';

	/**
	 * Every value this SDK knows.
	 *
	 * @return list<string>
	 */
	public static function values(): array
	{
		return [
			self::REFUND_EXISTS,
			self::HELD_FUNDS_RELEASED,
		];
	}

	private function __construct()
	{
	}
}
