<?php

declare(strict_types=1);

namespace QBitFlow;

use QBitFlow\Support\Cast;

/**
 * One page of a cursor-paginated list.
 *
 * Pass `nextCursor` back as the params' `cursor`, verbatim, to read the next page; or use the
 * list's `iterate*()` twin, which walks every page lazily.
 *
 * @template T
 */
final readonly class Page
{
	/**
	 * @param list<T>     $items      The page's rows.
	 * @param string|null $nextCursor The next page's cursor; null on the last page.
	 */
	public function __construct(
		public array $items = [],
		public ?string $nextCursor = null,
	) {
	}

	/** Whether another page follows. */
	public function hasMore(): bool
	{
		return $this->nextCursor !== null;
	}

	/**
	 * @template U
	 *
	 * @param array<string,mixed>              $data `{items, nextCursor}`, decoded.
	 * @param callable(array<string,mixed>): U $item Builds one row.
	 *
	 * @return self<U>
	 */
	public static function fromArray(array $data, callable $item): self
	{
		return new self(Cast::listOf($data, 'items', $item), Cast::nullableString($data, 'nextCursor'));
	}
}
