<?php

declare(strict_types=1);

namespace QBitFlow\Models;

use QBitFlow\Support\Amount;
use QBitFlow\Support\Cast;

/**
 * A currency (token or native coin) of the catalog.
 */
final readonly class Currency extends Model
{
	/**
	 * The currency's id (`$client->currencies->get($id)`).
	 */
	public int $id;

	/**
	 * Its full name (e.g. "USD Coin").
	 */
	public string $name;

	/**
	 * Its symbol (e.g. "USDC").
	 */
	public string $symbol;

	/**
	 * Its number of decimals (6 for USDC).
	 */
	public int $decimals;

	/**
	 * The token's contract address (or mint); `''` for a chain's native coin.
	 */
	public string $address;

	/**
	 * The chain's native coin, for a token; null for a native coin.
	 */
	public ?int $mainCurrencyId;

	/**
	 * That native coin; null for a native coin.
	 */
	public ?Currency $mainCurrency;

	/**
	 * True for a testnet currency.
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
		$this->name = Cast::string($data, 'name');
		$this->symbol = Cast::string($data, 'symbol');
		$this->decimals = Cast::uint($data, 'decimals', 255);
		$this->address = Cast::string($data, 'address');
		$this->mainCurrencyId = Cast::nullableUint($data, 'mainCurrencyId');
		$this->mainCurrency = Cast::nullableObject($data, 'mainCurrency', Currency::fromArray(...));
		$this->test = Cast::bool($data, 'test');
	}
}
