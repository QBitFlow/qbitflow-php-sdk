<?php

declare(strict_types=1);

namespace QBitFlow\Params;

use QBitFlow\Support\Query;

/**
 * `wallets->list()`'s options.
 */
final readonly class WalletListParams
{
	/**
	 * Every filter is optional: pass the ones you need by name; one left null is not sent.
	 * Nothing is checked here: the service method checks the values before sending (a
	 * {@see \QBitFlow\Exceptions\ValidationException} naming each bad one, nothing sent).
	 */
	public function __construct(
		/** Adds each token wallet's balance. */
		public bool $withBalances = false,
	) {
	}

	/** Nothing to check: present for uniformity. */
	public function validate(): void
	{
	}

	/** @return array<string,string|list<string>> */
	public function toQuery(): array
	{
		return (new Query())->flag('withBalances', $this->withBalances)->toArray();
	}
}
