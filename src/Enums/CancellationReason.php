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
	/** Its customer cancelled it (it was stopped until its paid period ended). */
	public const CUSTOMER = 'customer';

	/** The merchant cancelled it (`subscriptions->cancel()`). */
	public const MERCHANT = 'merchant';

	/** Its bill's last attempt failed. */
	public const BILLING_FAILED = 'billingFailed';

	/** Its trial ended and its customer didn't subscribe within the grace period. */
	public const TRIAL_NOT_CONVERTED = 'trialNotConverted';

	/** Its merchant's space closed (a member removed, the organization closed). */
	public const MERCHANT_CLOSED = 'merchantClosed';

	/** The contract no longer had it active (cancelled outside QBitFlow). */
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
