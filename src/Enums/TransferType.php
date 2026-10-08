<?php

declare(strict_types=1);

namespace QBitFlow\Enums;

/**
 * A transfer's kind (`heldFunds.released`).
 *
 * An open enum: the API's value is kept as a plain `string`, and a value this SDK does not
 * know yet is kept as is, never rejected. Compare against these constants.
 */
final class TransferType
{
	/** v1: funds held for a user, paid when they claimed their account. */
	public const ACCOUNT_CLAIM = 'accountClaim';

	/** The funds an organization held for a member, paid out to them. */
	public const HELD_FUNDS_RELEASE = 'heldFundsRelease';

	public const INTERNAL = 'internal';

	public const EXTERNAL = 'external';

	/**
	 * Every value this SDK knows.
	 *
	 * @return list<string>
	 */
	public static function values(): array
	{
		return [
			self::ACCOUNT_CLAIM,
			self::HELD_FUNDS_RELEASE,
			self::INTERNAL,
			self::EXTERNAL,
		];
	}

	private function __construct()
	{
	}
}
