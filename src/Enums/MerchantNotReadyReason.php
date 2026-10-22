<?php

declare(strict_types=1);

namespace QBitFlow\Enums;

/**
 * `details.reason` of a 409 `merchant_not_ready`.
 *
 * An open enum: the API's value is kept as a plain `string`, and a value this SDK does not
 * know yet is kept as is, never rejected. Compare against these constants.
 */
final class MerchantNotReadyReason
{
	public const NO_WALLET = 'noWallet';

	public const ORGANIZATION_NO_WALLET = 'organizationNoWallet';

	public const NO_TOKEN_WALLET = 'noTokenWallet';

	/**
	 * A subscription checkout of a member whose funds the organization holds: one-time payments
	 * only until `members->trust()`.
	 */
	public const FUNDS_HELD = 'fundsHeld';

	/**
	 * Every value this SDK knows.
	 *
	 * @return list<string>
	 */
	public static function values(): array
	{
		return [
			self::NO_WALLET,
			self::ORGANIZATION_NO_WALLET,
			self::NO_TOKEN_WALLET,
			self::FUNDS_HELD,
		];
	}

	private function __construct()
	{
	}
}
