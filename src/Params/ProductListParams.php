<?php

declare(strict_types=1);

namespace QBitFlow\Params;

use QBitFlow\Support\Query;

/**
 * Filters `products->list()`.
 */
final readonly class ProductListParams
{
	public function __construct(
		/** Lists the hidden products too (`isActive` false). */
		public bool $includeHidden = false,
		/** Keeps only the subscription products (true) or the one-time ones (false). */
		public ?bool $subscription = null,
	) {
	}

	/** Nothing to check: present for uniformity. */
	public function validate(): void
	{
	}

	/** @return array<string,string> */
	public function toQuery(): array
	{
		return (new Query())->flag('includeHidden', $this->includeHidden)->bool('subscription', $this->subscription)->toArray();
	}
}
