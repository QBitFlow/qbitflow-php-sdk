<?php

declare(strict_types=1);

namespace QBitFlow\Models;

use QBitFlow\Support\Cast;

/**
 * An amount of a token (or of a chain's native coin, when its field says so) as responses write
 * it: the exact amount in the token's units and its on-chain integer, always together, and its
 * USD value when a price is known. Its currency is the resource's.
 *
 * ```json
 * {"amount": "12.5", "amountMinUnits": "12500000", "amountUsd": "12.5"}
 * ```
 *
 * Every amount is a decimal string, never a float: convert it with {@see \QBitFlow\Support\Amount}.
 */
readonly class TokenAmount extends Model
{
	/**
	 * The amount in the token's units, exact: `amountMinUnits / 10^decimals`, a decimal string (`"12.5"`).
	 */
	public string $amount;

	/**
	 * The on-chain integer, in the token's smallest unit, a string of digits (`"12500000"`).
	 */
	public string $amountMinUnits;

	/**
	 * Its value in USD, a decimal string; null (absent) when no price was known.
	 */
	public ?string $amountUsd;

	/**
	 * @param array<string,mixed> $data
	 */
	protected function __construct(array $data)
	{
		$this->amount = Cast::string($data, 'amount');
		$this->amountMinUnits = Cast::string($data, 'amountMinUnits');
		$this->amountUsd = Cast::nullableString($data, 'amountUsd');
	}
}
