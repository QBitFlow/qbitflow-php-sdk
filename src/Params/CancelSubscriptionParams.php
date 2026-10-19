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
		/**
		 * Cancels now (null or true, the default: status `cancelled`); false stops it now (status
		 * `stopped`, never billed again) and cancels it when its paid period ends (`nextBillingAt`).
		 * A trial is cancelled immediately only (false: 400).
		 */
		public ?bool $immediate = null,
	) {
	}

	/** Nothing to check: present for uniformity. */
	public function validate(): void
	{
	}

	/** @return array<string,string|list<string>> */
	public function toQuery(): array
	{
		return (new Query())->bool('immediate', $this->immediate)->toArray();
	}
}
