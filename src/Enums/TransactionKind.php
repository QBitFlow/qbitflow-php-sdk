<?php

declare(strict_types=1);

namespace QBitFlow\Enums;

/**
 * What a row of the transactions feed is (`Transaction::$type`). Not
 * {@see TransactionType}, which is a checkout's `txType`.
 *
 * An open enum: the API's value is kept as a plain `string`, and a value this SDK does not
 * know yet is kept as is, never rejected. Compare against these constants.
 */
final class TransactionKind
{
	/** A one-time payment (`pay@…`): `payments->get()`. */
	public const PAYMENT = 'payment';

	/** A subscription's bill (`sub-hist@…`): `subscriptions->getBill()`. */
	public const BILL = 'bill';

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
		];
	}

	private function __construct()
	{
	}
}
