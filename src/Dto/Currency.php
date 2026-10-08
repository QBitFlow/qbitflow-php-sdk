<?php

declare(strict_types=1);

namespace QBitFlow\Dto;

use QBitFlow\Support\Cast;
use QBitFlow\Support\Dto;

/**
 * A cryptocurrency accepted for payment — either a native chain currency or a token.
 *
 * Resolve the currency IDs found on sessions and payments with
 * {@see \QBitFlow\Requests\CurrencyRequests::getAllAvailable()}.
 */
final class Currency extends Dto
{
	public function __construct(
		/** Unique identifier for the currency. */
		public readonly int $id,
		/** Currency symbol, e.g. `BTC` or `USDC`. */
		public readonly string $symbol,
		/** Full name of the currency, e.g. `Bitcoin`. */
		public readonly string $name,
		/** Number of decimal places used by the smallest unit. */
		public readonly int $decimals,
		/** Whether this is a test-network currency. */
		public readonly bool $test,
		/** Contract/mint address, or the chain identifier for a native currency. */
		public readonly ?string $address = null,
		/** For a token, the ID of the native currency it settles on. */
		public readonly ?int $mainCurrencyId = null,
		/** For a token, the native currency it settles on. */
		public readonly ?self $mainCurrency = null,
	) {
	}

	/**
	 * @param array<string,mixed> $data
	 */
	public static function fromArray(array $data): self
	{
		return new self(
			Cast::int($data, 'id'),
			Cast::string($data, 'symbol'),
			Cast::string($data, 'name'),
			Cast::int($data, 'decimals'),
			Cast::bool($data, 'test'),
			Cast::nullableString($data, 'address'),
			Cast::nullableInt($data, 'mainCurrencyId'),
			Cast::nested($data, 'mainCurrency', self::fromArray(...)),
		);
	}

	/** Whether this currency is a token settling on another (main) currency. */
	public function isToken(): bool
	{
		return $this->mainCurrencyId !== null;
	}
}
