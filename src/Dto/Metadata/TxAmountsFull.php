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
	 * @param array<string,mixed> $data
	 */
	public static function fromArray(array $data): self
	{
		return new self(
			Cast::nested($data, 'usd', TxAmountsUSD::fromArray(...))
				?? new TxAmountsUSD(0.0, 0.0),
			Cast::nested($data, 'minUnits', TxAmountsMinUnits::fromArray(...))
				?? new TxAmountsMinUnits('0', '0', '0', '0'),
		);
	}
}
