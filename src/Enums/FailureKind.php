<?php

declare(strict_types=1);

namespace QBitFlow\Enums;

/**
 * What a failed attempt was paying.
 *
 * An open enum: the API's value is kept as a plain `string`, and a value this SDK does not
 * know yet is kept as is, never rejected. Compare against these constants.
 */
final class FailureKind
{
	/** A one-time payment's attempt (pay@…). */
	public const PAYMENT = 'payment';

	/** A subscription checkout's: its creation and first bill (sub@…). */
	public const SUBSCRIPTION_CHECKOUT = 'subscriptionCheckout';

	/** A subscription's bill (sub-hist@…). */
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
			self::SUBSCRIPTION_CHECKOUT,
			self::BILL,
		];
	}

	private function __construct()
	{
	}
}
