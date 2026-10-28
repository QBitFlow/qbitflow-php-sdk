<?php

declare(strict_types=1);

namespace QBitFlow\Enums;

/**
 * A transaction's kind (`checkout.expired`'s `txType`: `payment` or `createSubscription`).
 *
 * An open enum: the API's value is kept as a plain `string`, and a value this SDK does not
 * know yet is kept as is, never rejected. Compare against these constants.
 */
final class TransactionType
{
	/** A one-time payment's checkout. */
	public const PAYMENT = 'payment';

	/** A subscription's checkout. */
	public const CREATE_SUBSCRIPTION = 'createSubscription';

	/**
	 * Every value this SDK knows.
	 *
	 * @return list<string>
	 */
	public static function values(): array
	{
		return [
			self::PAYMENT,
			self::CREATE_SUBSCRIPTION,
		];
	}

	private function __construct()
	{
	}
}
