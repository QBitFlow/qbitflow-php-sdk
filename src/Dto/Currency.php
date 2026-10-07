<?php

declare(strict_types=1);

namespace QBitFlow\Dto;

use QBitFlow\Support\Cast;
use QBitFlow\Support\Dto;

/**
 * A cryptocurrency accepted for payment — either a native chain currency or a token.
 *
 * Returned by the currency lookups, and nested as `currency` on every payment,
 * subscription and billing record. Resolve the currency IDs found on sessions with
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
		/** Token contract (EVM) or mint (Solana) address; `''` for a native currency. */
		public readonly string $address = '',
		/** For a token, the ID of the native currency it settles on; null for a native currency. */
		public readonly ?int $mainCurrencyId = null,
		/** For a token, the native currency it settles on; null for a native currency. */
		public readonly ?self $mainCurrency = null,
	) {
	}

	/**
	 * @param array<array-key,mixed> $data
	 */
	public static function fromArray(array $data): self
	{
		return new self(
			Cast::int($data, 'id'),
			Cast::string($data, 'symbol'),
			Cast::string($data, 'name'),
			Cast::int($data, 'decimals'),
			Cast::bool($data, 'test'),
			Cast::string($data, 'address'),
			Cast::nullableInt($data, 'mainCurrencyId'),
			Cast::nullableObject($data, 'mainCurrency', self::fromArray(...)),
		);
	}

	/** Whether this currency is a token settling on another (main) currency. */
	public function isToken(): bool
	{
		return $this->mainCurrencyId !== null;
	}
}
