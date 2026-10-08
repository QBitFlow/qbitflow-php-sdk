<?php

declare(strict_types=1);

namespace QBitFlow\Models;

use QBitFlow\Support\Cast;

/**
 * What the organization holds for a member (trust layer).
 */
final readonly class HeldFunds extends Model
{
	/**
	 * The lines not settled yet, oldest first: payments and bills held (positive), and the refunds sent of them (negative).
	 *
	 * @var list<LedgerEntry>
	 */
	public array $ledgers;

	/**
	 * What the organization owes the member, in USD (may be 0 or less).
	 */
	public float $totalAmount;

	/**
	 * @param array<string,mixed> $data
	 */
	protected function __construct(array $data)
	{
		$this->ledgers = Cast::listOf($data, 'ledgers', LedgerEntry::fromArray(...));
		$this->totalAmount = Cast::float($data, 'totalAmount');
	}
}
