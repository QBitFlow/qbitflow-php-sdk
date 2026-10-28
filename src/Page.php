<?php

declare(strict_types=1);

namespace QBitFlow;

use QBitFlow\Support\Cast;

/**
 * One page of a cursor-paginated list, newest first (members by when they joined).
 *
 * When `hasMore` is true, pass `nextCursor` back as the params' `cursor`, verbatim, to read the
 * next page; or use the list's `iterate*()` twin, which walks every page lazily. The cursor is
 * opaque: never build, parse or store it as an id. The bounded lists (currencies, wallets, the
 * members' held funds, the accounting export) answer the same envelope in one page; their
 * methods return its `items`.
 *
 * @template T
 */
final readonly class Page
{
	/**
	 * @param list<T>     $items      The page's rows; `[]` when there are none.
	 * @param bool        $hasMore    Whether another page follows. A full last page answers false: there is never an extra empty page.
	 * @param string|null $nextCursor The next page's cursor, opaque (pass it back verbatim); null when `hasMore` is false.
	 */
	public function __construct(
		public array $items = [],
		public bool $hasMore = false,
		public ?string $nextCursor = null,
	) {
	}

	/**
	 * @template U
	 *
	 * @param array<string,mixed>              $data `{items, hasMore, nextCursor?}`, decoded.
	 * @param callable(array<string,mixed>): U $item Builds one row.
	 *
	 * @return self<U>
	 */
	public static function fromArray(array $data, callable $item): self
	{
		$cursor = Cast::nullableString($data, 'nextCursor');

		return new self(Cast::listOf($data, 'items', $item), Cast::bool($data, 'hasMore'), $cursor === '' ? null : $cursor);
	}
}
