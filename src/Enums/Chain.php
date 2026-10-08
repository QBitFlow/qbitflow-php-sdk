<?php

declare(strict_types=1);

namespace QBitFlow\Enums;

/**
 * A blockchain (the testnet's in test mode).
 *
 * An open enum: the API's value is kept as a plain `string`, and a value this SDK does not
 * know yet is kept as is, never rejected. Compare against these constants.
 */
final class Chain
{
	/** Ethereum. */
	public const ETH = 'ETH';

	/** Base. */
	public const BASE = 'BASE';

	/** Solana. */
	public const SOL = 'SOL';

	/**
	 * Every value this SDK knows.
	 *
	 * @return list<string>
	 */
	public static function values(): array
	{
		return [
			self::ETH,
			self::BASE,
			self::SOL,
		];
	}

	private function __construct()
	{
	}
}
