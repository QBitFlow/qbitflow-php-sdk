<?php

declare(strict_types=1);

namespace QBitFlow\Params;

use QBitFlow\Support\Query;
use QBitFlow\Support\Validator;

/**
 * Pages `subscriptions->listBills()` / `iterateBills()`.
 */
final readonly class BillListParams
{
	use WithCursor;

	public function __construct(
		/** The page size (server default 20, max 100). */
		public ?int $limit = null,
		/** The previous page's `nextCursor`, verbatim; null for the first page. */
		public ?string $cursor = null,
	) {
	}

	/** @throws \QBitFlow\Exceptions\ValidationException */
	public function validate(): void
	{
		$v = new Validator();
		$v->cursor($this->cursor);
		$v->throwIfAny();
	}

	/** @return array<string,string|list<string>> */
	public function toQuery(): array
	{
		return (new Query())->page($this->limit, $this->cursor)->toArray();
	}
}
