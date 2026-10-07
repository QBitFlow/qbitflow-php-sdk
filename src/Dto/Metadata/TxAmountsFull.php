<?php

declare(strict_types=1);

namespace QBitFlow\Dto\Metadata;

use QBitFlow\Support\Cast;
use QBitFlow\Support\Dto;

/**
 * Computed per-party amounts, expressed both in USD and in smallest currency units.
 */
final class TxAmountsFull extends Dto
{
	public function __construct(
		/** Amounts in USD. */
		public readonly TxAmountsUSD $usd,
		/** Amounts in the smallest currency units, as decimal strings. */
		public readonly TxAmountsMinUnits $minUnits,
	) {
	}

	/**
	 * @param array<array-key,mixed> $data
	 */
	public static function fromArray(array $data): self
	{
		return new self(
			Cast::object($data, 'usd', TxAmountsUSD::fromArray(...)),
			Cast::object($data, 'minUnits', TxAmountsMinUnits::fromArray(...)),
		);
	}
}
