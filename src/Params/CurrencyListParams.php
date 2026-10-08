<?php

declare(strict_types=1);

namespace QBitFlow\Params;

use QBitFlow\Support\Query;

/**
 * `currencies->listAvailable()`'s and `listMain()`'s options.
 */
final readonly class CurrencyListParams
{
	public function __construct(
		/** Lists the testnet currencies (whatever the key's mode). */
		public bool $test = false,
	) {
	}

	/** Nothing to check: present for uniformity. */
	public function validate(): void
	{
	}

	/** @return array<string,string> */
	public function toQuery(): array
	{
		return (new Query())->flag('test', $this->test)->toArray();
	}
}
