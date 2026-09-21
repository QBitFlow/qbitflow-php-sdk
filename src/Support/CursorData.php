<?php

declare(strict_types=1);

namespace QBitFlow\Support;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use QBitFlow\Exceptions\ValidationException;
use Traversable;

/**
 * One page of a cursor-paginated collection.
 *
 * ```php
 * $cursor = null;
 *
 * do {
 *     $page = $client->oneTimePayments->getAll(limit: 50, cursor: $cursor);
 *
 *     foreach ($page as $payment) {
 *         echo $payment->uuid, PHP_EOL;
 *     }
 *
 *     $cursor = $page->nextCursor;
 * } while ($page->hasMore());
 * ```
 *
 * @template T of object
 *
 * @implements IteratorAggregate<int,T>
 */
final class CursorData implements Countable, IteratorAggregate
{
	/**
	 * @param list<T>     $items      Items on this page.
	 * @param string|null $nextCursor Cursor for the next page, or null on the last page.
	 */
	public function __construct(
		public readonly array $items = [],
		public readonly ?string $nextCursor = null,
	) {
	}

	/**
	 * Hydrate a paginated response.
	 *
	 * @template TItem of object
	 *
	 * @param array<string,mixed>                  $data
	 * @param callable(array<string,mixed>): TItem $factory
	 *
	 * @return self<TItem>
	 */
	public static function fromArray(array $data, callable $factory): self
	{
		$cursor = $data['nextCursor'] ?? null;

		return new self(
			Cast::listOf($data['items'] ?? [], $factory),
			is_scalar($cursor) && (string) $cursor !== '' ? (string) $cursor : null,
		);
	}

	/** Whether another page is available. */
	public function hasMore(): bool
	{
		return $this->nextCursor !== null;
	}

	/** Number of items on this page. */
	public function count(): int
	{
		return count($this->items);
	}

	/**
	 * @return Traversable<int,T>
	 */
	public function getIterator(): Traversable
	{
		return new ArrayIterator($this->items);
	}

	/**
	 * Build the query parameters for a cursor-paginated request.
	 *
	 * @return array<string,scalar>
	 *
	 * @throws ValidationException If the limit is not positive.
	 *
	 * @internal
	 */
	public static function queryParams(?int $limit = null, ?string $cursor = null): array
	{
		$params = [];

		if ($limit !== null) {
			if ($limit <= 0) {
				throw new ValidationException('Limit must be greater than 0');
			}

			$params['limit'] = $limit;
		}

		if ($cursor !== null && $cursor !== '') {
			$params['cursor'] = $cursor;
		}

		return $params;
	}
}
