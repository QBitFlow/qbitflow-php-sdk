<?php

declare(strict_types=1);

namespace QBitFlow\Models;

use QBitFlow\Support\Cast;

/**
 * What a refund or a held-funds release records of its transfer: the transaction only (a
 * transfer pays no fee and has no split).
 */
final readonly class TransferSettlement extends Model
{
	/**
	 * The transfer's transaction; null (absent) before it was confirmed.
	 */
	public ?SettlementTransaction $transaction;

	/**
	 * @param array<string,mixed> $data
	 */
	protected function __construct(array $data)
	{
		$this->transaction = Cast::nullableObject($data, 'transaction', SettlementTransaction::fromArray(...));
	}
}
