<?php

declare(strict_types=1);

namespace QBitFlow\Params;

use QBitFlow\Support\Query;

/**
 * Pages `members->list()` / `iterate()`.
 */
final readonly class MemberListParams
{
	use WithCursor;

	public function __construct(
		/** The page size (server default 20, max 100). */
		public ?int $limit = null,
		/** The previous page's `nextCursor` (a member's userUuid); null for the first page. */
		public ?string $cursor = null,
	) {
	}

	/** Nothing to check: present for uniformity. */
	public function validate(): void
	{
	}

	/** @return array<string,string> */
	public function toQuery(): array
	{
		return (new Query())->page($this->limit, $this->cursor)->toArray();
	}
}
