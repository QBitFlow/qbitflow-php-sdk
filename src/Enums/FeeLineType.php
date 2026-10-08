<?php

declare(strict_types=1);

namespace QBitFlow\Enums;

/**
 * Who added a line to a checkout's price ({@see \QBitFlow\Models\FeeLine}).
 *
 * An open enum: the API's value is kept as a plain `string`, and a value this SDK does not
 * know yet is kept as is, never rejected. Compare against these constants.
 */
final class FeeLineType
{
	/** The merchant's own line: a tax, shipping, a service fee. */
	public const CUSTOM = 'custom';

	/** QBitFlow's fee, which the merchant has the customer pay (computed by QBitFlow). */
	public const PROCESSING_FEE = 'processingFee';

	/**
	 * Every value this SDK knows.
	 *
	 * @return list<string>
	 */
	public static function values(): array
	{
		return [
			self::CUSTOM,
			self::PROCESSING_FEE,
		];
	}

	private function __construct()
	{
	}
}
