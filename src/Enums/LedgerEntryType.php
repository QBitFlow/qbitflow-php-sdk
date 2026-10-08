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
	public const PAYMENT = 'payment';

	public const SUBSCRIPTION_HISTORY = 'subscriptionHistory';

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
			self::SUBSCRIPTION_HISTORY,
			self::REFUND,
		];
	}

	private function __construct()
	{
	}
}
