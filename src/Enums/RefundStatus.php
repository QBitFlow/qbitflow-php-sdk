<?php

declare(strict_types=1);

namespace QBitFlow\Enums;

/**
 * Where a refund stands. An approval and a rejection are final: the transaction never gets
 * another refund. A cancelled one never moved money and blocks nothing.
 *
 * An open enum: the API's value is kept as a plain `string`, and a value this SDK does not
 * know yet is kept as is, never rejected. Compare against these constants.
 */
final class RefundStatus
{
	/** Requested, or started by the merchant and not sent yet. */
	public const PENDING = 'pending';

	/** The refund transfer is confirmed (`refund.approved`). */
	public const APPROVED = 'approved';

	/** Rejected by whoever decides it (`refund.rejected`). */
	public const REJECTED = 'rejected';

	/** A merchant's refund dropped before it was sent (announced by no event). */
	public const CANCELLED = 'cancelled';

	/** Declared, never set: a failed approval leaves the refund pending. */
	public const FAILED = 'failed';

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
			self::CANCELLED,
			self::FAILED,
		];
	}

	private function __construct()
	{
	}
}
