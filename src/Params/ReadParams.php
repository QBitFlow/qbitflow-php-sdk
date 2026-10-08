<?php

declare(strict_types=1);

namespace QBitFlow\Params;

use QBitFlow\Support\Query;

/**
 * The options of the reads by id that can reach a member's row (`payments->get()`,
 * `subscriptions->get()`, `subscriptions->getBill()`).
 */
final readonly class ReadParams
{
	public function __construct(
		/** Reads a member's row from the organization's space (organization key without On-Behalf-Of). */
		public bool $includeMembers = false,
	) {
	}

	/** Nothing to check: present for uniformity. */
	public function validate(): void
	{
	}

	/** @return array<string,string> */
	public function toQuery(): array
	{
		return (new Query())->flag('includeMembers', $this->includeMembers)->toArray();
	}
}
