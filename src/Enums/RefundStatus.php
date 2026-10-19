<?php

declare(strict_types=1);

namespace QBitFlow\Enums;

/**
 * Where a refund stands. An approval and a rejection are final: the transaction never gets
 * another refund.
 *
 * An open enum: the API's value is kept as a plain `string`, and a value this SDK does not
 * know yet is kept as is, never rejected. Compare against these constants.
 */
final class RefundStatus
{
	/** A customer's request not answered yet. */
	public const PENDING = 'pending';

	/**
	 * The refund transfer is confirmed (`refund.approved`); the merchant's own refund is recorded
	 * only then.
	 */
	public const APPROVED = 'approved';

	/** Rejected by whoever decides it (`refund.rejected`). */
	public const REJECTED = 'rejected';

	/** Declared, never set: a failed approval leaves the request pending. */
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
			self::FAILED,
		];
	}

	private function __construct()
	{
	}
}
