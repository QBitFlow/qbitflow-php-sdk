<?php

declare(strict_types=1);

namespace QBitFlow\Models;

use QBitFlow\Support\Cast;

/**
 * A token wallet's balance.
 */
final readonly class Balance extends Model
{
	/**
	 * The token (e.g. "SOL:USDC").
	 */
	public string $currency;

	/**
	 * The balance in the token (a decimal string).
	 */
	public string $balance;

	/**
	 * The same in USD.
	 */
	public float $balanceUsd;

	/**
	 * @param array<string,mixed> $data
	 */
	protected function __construct(array $data)
	{
		$this->currency = Cast::string($data, 'currency');
		$this->balance = Cast::string($data, 'balance');
		$this->balanceUsd = Cast::float($data, 'balanceUsd');
	}
}
