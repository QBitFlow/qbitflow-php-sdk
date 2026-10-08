<?php

declare(strict_types=1);

namespace QBitFlow\Params;

use QBitFlow\Support\Query;

/**
 * `subscriptions->cancel()`'s options.
 */
final readonly class CancelSubscriptionParams
{
	public function __construct(
		/** Cancels now (null or true, the default); false stops it at the end of the current period. */
		public ?bool $immediate = null,
	) {
	}

	/** Nothing to check: present for uniformity. */
	public function validate(): void
	{
	}

	/** @return array<string,string> */
	public function toQuery(): array
	{
		return (new Query())->bool('immediate', $this->immediate)->toArray();
	}
}
