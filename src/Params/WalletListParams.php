<?php

declare(strict_types=1);

namespace QBitFlow\Params;

use QBitFlow\Support\Query;

/**
 * `wallets->list()`'s options.
 */
final readonly class WalletListParams
{
	public function __construct(
		/** Adds each token wallet's balance. */
		public bool $withBalances = false,
	) {
	}

	/** Nothing to check: present for uniformity. */
	public function validate(): void
	{
	}

	/** @return array<string,string> */
	public function toQuery(): array
	{
		return (new Query())->flag('withBalances', $this->withBalances)->toArray();
	}
}
