<?php

declare(strict_types=1);

namespace QBitFlow\Enums;

/**
 * Whose wallet paid an accounting event's network fees ({@see \QBitFlow\Models\AccountingEvent::$networkFeesPaidBy}).
 *
 * An open enum: the API's value is kept as a plain `string`, and a value this SDK does not
 * know yet is kept as is, never rejected. Compare against these constants.
 */
final class NetworkFeesPayer
{
	/** A payment or a bill: the payer, on top of the amount. */
	public const PAYER = 'payer';

	/** A refund the space sent from its wallet. */
	public const MERCHANT = 'merchant';

	/** A refund of a payment the organization held for its member, sent from the organization's wallet. */
	public const ORGANIZATION = 'organization';

	/**
	 * Every value this SDK knows.
	 *
	 * @return list<string>
	 */
	public static function values(): array
	{
		return [
			self::PAYER,
			self::MERCHANT,
			self::ORGANIZATION,
		];
	}

	private function __construct()
	{
	}
}
