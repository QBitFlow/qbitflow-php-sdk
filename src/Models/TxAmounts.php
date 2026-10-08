<?php

declare(strict_types=1);

namespace QBitFlow\Models;

use QBitFlow\Support\Cast;

/**
 * How a payment was split, in USD and in the token's min units.
 */
final readonly class TxAmounts extends Model
{
	/**
	 * The split in USD at the transaction's time.
	 */
	public TxAmountsUsd $usd;

	/**
	 * The split in the token's min units, exactly as paid.
	 */
	public TxAmountsMinUnits $minUnits;

	/**
	 * @param array<string,mixed> $data
	 */
	protected function __construct(array $data)
	{
		$this->usd = Cast::object($data, 'usd', TxAmountsUsd::fromArray(...));
		$this->minUnits = Cast::object($data, 'minUnits', TxAmountsMinUnits::fromArray(...));
	}
}
