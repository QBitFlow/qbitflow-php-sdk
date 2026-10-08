<?php

declare(strict_types=1);

namespace QBitFlow\Enums;

/**
 * An accounting export row's kind.
 *
 * An open enum: the API's value is kept as a plain `string`, and a value this SDK does not
 * know yet is kept as is, never rejected. Compare against these constants.
 */
final class AccountingEventType
{
	public const PAYMENT = 'payment';

	public const SUBSCRIPTION_HISTORY = 'subscriptionHistory';

	public const REFUND = 'refund';

	public const ORGANIZATION_FEE = 'organizationFee';

	public const REFERRAL_FEE = 'referralFee';

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
			self::ORGANIZATION_FEE,
			self::REFERRAL_FEE,
		];
	}

	private function __construct()
	{
	}
}
