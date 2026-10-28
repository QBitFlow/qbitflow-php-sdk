<?php

declare(strict_types=1);

namespace QBitFlow\Models;

use QBitFlow\Support\Amount;
use QBitFlow\Support\Cast;

/**
 * A currency of the catalog: a chain's native coin (ETH, SOL; its chain is itself) or a token on
 * a chain (USDC on Base). It is the one currency object of every response
 * (`currencies->list()`, `Payment::$currency`, `Member::$acceptedCurrencies`, …).
 */
final readonly class Currency extends Model
{
	/**
	 * The currency's id (`$client->currencies->get($id)`): a public catalog, unique across test
	 * and live.
	 */
	public int $id;

	/**
	 * What requests name it by: `CHAIN:TOKEN` for a token (`"BASE:USDC"`), the chain's symbol for
	 * its native coin (`"ETH"`).
	 */
	public string $code;

	/**
	 * Its symbol (`"USDC"`, `"ETH"`).
	 */
	public string $symbol;

	/**
	 * Its full name (`"USD Coin"`, `"Ethereum"`).
	 */
	public string $name;

	/**
	 * The chain it is on, by its native coin's symbol: `ETH`, `BASE` or `SOL` ({@see \QBitFlow\Enums\Chain}).
	 */
	public string $chain;

	/**
	 * The decimals of its on-chain amounts: min units = amount × 10^decimals (6 for USDC).
	 * {@see formatAmount()} converts with it.
	 */
	public int $decimals;

	/**
	 * A token's contract address (EVM) or mint (Solana); null (absent) on a native coin.
	 */
	public ?string $address;

	/**
	 * A token's chain, by its native coin's id (`currencies->get()`); null (absent) on a native coin.
	 */
	public ?int $mainCurrencyId;

	/**
	 * True for a test network's currency, false for a live one.
	 */
	public bool $test;

	/**
	 * An amount in this currency's min units as a decimal string: `"1500000"` → `"1.5"` for
	 * USDC (6 decimals). Exact (see {@see Amount::format()}).
	 *
	 * @throws \QBitFlow\Exceptions\ValidationException When `$minUnits` is not an integer.
	 */
	public function formatAmount(string $minUnits): string
	{
		return Amount::format($minUnits, $this->decimals);
	}

	/**
	 * @param array<string,mixed> $data
	 */
	protected function __construct(array $data)
	{
		$this->id = Cast::uint($data, 'id');
		$this->code = Cast::string($data, 'code');
		$this->symbol = Cast::string($data, 'symbol');
		$this->name = Cast::string($data, 'name');
		$this->chain = Cast::string($data, 'chain');
		$this->decimals = Cast::uint($data, 'decimals', 255);
		$this->address = Cast::nullableString($data, 'address');
		$this->mainCurrencyId = Cast::nullableUint($data, 'mainCurrencyId');
		$this->test = Cast::bool($data, 'test');
	}
}
