<?php

declare(strict_types=1);

namespace QBitFlow\Enums;

/**
 * What a combined feed row is.
 *
 * An open enum: the API's value is kept as a plain `string`, and a value this SDK does not
 * know yet is kept as is, never rejected. Compare against these constants.
 */
final class CombinedPaymentSource
{
	/** A one-time payment (pay@…). */
	public const PAYMENT = 'payment';

	/** A subscription's bill (sub-hist@…). */
	public const SUBSCRIPTION_HISTORY = 'subscriptionHistory';

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
		];
	}

	private function __construct()
	{
	}
}
