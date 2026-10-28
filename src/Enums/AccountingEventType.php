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
	/** A one-time payment (`pay@…`). */
	public const PAYMENT = 'payment';

	/** A subscription's bill (`sub-hist@…`; was `subscriptionHistory`). */
	public const BILL = 'bill';

	/** A refund the space sent (`refund@…`). */
	public const REFUND = 'refund';

	/** The fee the organization earned on a member's payment or bill. */
	public const ORGANIZATION_FEE = 'organizationFee';

	/** The referrer's share earned on a referred organization's payment or bill. */
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
			self::BILL,
			self::REFUND,
			self::ORGANIZATION_FEE,
			self::REFERRAL_FEE,
		];
	}

	private function __construct()
	{
	}
}
