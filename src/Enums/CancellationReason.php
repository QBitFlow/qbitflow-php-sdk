<?php

declare(strict_types=1);

namespace QBitFlow\Enums;

/**
 * Why a subscription stopped or was cancelled.
 *
 * An open enum: the API's value is kept as a plain `string`, and a value this SDK does not
 * know yet is kept as is, never rejected. Compare against these constants.
 */
final class CancellationReason
{
	public const CUSTOMER = 'customer';

	public const MERCHANT = 'merchant';

	public const BILLING_FAILED = 'billingFailed';

	public const TRIAL_NOT_CONVERTED = 'trialNotConverted';

	public const MERCHANT_CLOSED = 'merchantClosed';

	public const INACTIVE_ON_CHAIN = 'inactiveOnChain';

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
			self::BILLING_FAILED,
			self::TRIAL_NOT_CONVERTED,
			self::MERCHANT_CLOSED,
			self::INACTIVE_ON_CHAIN,
		];
	}

	private function __construct()
	{
	}
}
