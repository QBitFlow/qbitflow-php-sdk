<?php

declare(strict_types=1);

namespace QBitFlow\Enums;

/**
 * A held-funds line's kind.
 *
 * An open enum: the API's value is kept as a plain `string`, and a value this SDK does not
 * know yet is kept as is, never rejected. Compare against these constants.
 */
final class LedgerEntryType
{
	/** A payment made to the member while the organization held their funds. */
	public const PAYMENT = 'payment';

	/** A subscription bill paid to the member while held (was `subscriptionHistory`). */
	public const BILL = 'bill';

	/** A refund the organization sent of one of them. */
	public const REFUND = 'refund';

	/**
	 * Every value this SDK knows.
	 *
	 * @return list<string>
	 */
	public static function values(): array
	{
		return [
			self::PAYMENT,
			self::BILL,
			self::REFUND,
		];
	}

	private function __construct()
	{
	}
}
