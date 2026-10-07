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
	 * Hydrate a paginated response: `{"items": [...], "nextCursor": "<cursor>"|null}`.
	 *
	 * A `null` or absent `items` is an empty page (Go encodes a nil slice as `null`); a body
	 * that is not this envelope — a bare list, or `items` of the wrong type — is a
	 * response-shape failure.
	 *
	 * @template TItem of object
	 *
	 * @param array<array-key,mixed>                   $data
	 * @param callable(array<array-key,mixed>): TItem $factory
	 *
	 * @return self<TItem>
	 *
	 * @throws \QBitFlow\Exceptions\ServerException When the body is not a cursor page.
	 */
	public static function fromArray(array $data, callable $factory): self
	{
		return Cast::one($data, static function (array $page) use ($factory): self {
			$cursor = Cast::nullableString($page, 'nextCursor');

			return new self(
				Cast::listOf($page['items'] ?? null, $factory, 'items'),
				$cursor === '' ? null : $cursor,
			);
		});
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
