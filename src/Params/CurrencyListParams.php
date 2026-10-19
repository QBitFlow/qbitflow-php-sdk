<?php

declare(strict_types=1);

namespace QBitFlow\Params;

use QBitFlow\Support\Query;

/**
 * `currencies->list()`'s options.
 */
final readonly class CurrencyListParams
{
	public function __construct(
		/** Lists the test networks' currencies (whatever the key's mode); false: mainnet. */
		public bool $test = false,
		/** Lists only the chains' native coins (ETH, BASE, SOL), no tokens. */
		public bool $main = false,
	) {
	}

	/** Nothing to check: present for uniformity. */
	public function validate(): void
	{
	}

	/** @return array<string,string|list<string>> */
	public function toQuery(): array
	{
		return (new Query())->flag('test', $this->test)->flag('main', $this->main)->toArray();
	}
}
