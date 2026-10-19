<?php

declare(strict_types=1);

namespace QBitFlow\Models;

use QBitFlow\Support\Cast;

/**
 * What the organization holds for a member in one mode (held funds).
 */
final readonly class HeldFunds extends Model
{
	/**
	 * The lines not settled yet, oldest first: the payments and bills held (positive), and the
	 * refunds sent of them (negative, type `refund`). `[]` when nothing is held.
	 *
	 * @var list<LedgerEntry>
	 */
	public array $ledgers;

	/**
	 * What the organization owes the member, in USD, a decimal string: the sum of the lines'
	 * `owedUsd`. A release pays it, converted at the release's price. `"0"` or negative when
	 * refunds cancel out the payments (nothing to send: the held funds are closed instead).
	 */
	public string $totalUsd;

	/**
	 * @param array<string,mixed> $data
	 */
	protected function __construct(array $data)
	{
		$this->ledgers = Cast::listOf($data, 'ledgers', LedgerEntry::fromArray(...));
		$this->totalUsd = Cast::string($data, 'totalUsd');
	}
}
