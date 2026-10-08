<?php

declare(strict_types=1);

namespace QBitFlow\Models;

use QBitFlow\Support\Cast;

/**
 * A transaction's network fees, in the native coin's min units.
 */
final readonly class NetworkFees extends Model
{
	/**
	 * What the sender paid: its gas and, on Base, its L1 data fee (a decimal string).
	 */
	public string $amount;

	/**
	 * The gas used.
	 */
	public int $unitsConsumed;

	/**
	 * The part of amount that paid Base's L1 data fee; null on other networks.
	 */
	public ?string $l1Fee;

	/**
	 * @param array<string,mixed> $data
	 */
	protected function __construct(array $data)
	{
		$this->amount = Cast::string($data, 'amount');
		$this->unitsConsumed = Cast::uint($data, 'unitsConsumed');
		$this->l1Fee = Cast::nullableString($data, 'l1Fee');
	}
}
