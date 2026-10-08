<?php

declare(strict_types=1);

namespace QBitFlow\Enums;

/**
 * Who started a refund.
 *
 * An open enum: the API's value is kept as a plain `string`, and a value this SDK does not
 * know yet is kept as is, never rejected. Compare against these constants.
 */
final class RefundInitiator
{
	/** Requested by the customer; the merchant answers. */
	public const CUSTOMER = 'customer';

	/** Sent by the merchant without a request. */
	public const MERCHANT = 'merchant';

	/**
	 * Every value this SDK knows.
	 *
	 * @return list<string>
	 */
	public static function values(): array
	{
		return [
			self::CUSTOMER,
			self::MERCHANT,
		];
	}

	private function __construct()
	{
	}
}
