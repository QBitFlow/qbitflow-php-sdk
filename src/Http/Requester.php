<?php

declare(strict_types=1);

namespace QBitFlow\Http;

use Closure;
use Generator;
use QBitFlow\Page;
use QBitFlow\RequestOptions;
use QBitFlow\Support\Cast;
use stdClass;

/**
 * What a client's services call: the shared {@see Transport} plus the client's default
 * `On-Behalf-Of`, and the decoding helpers.
 *
 * @internal
 */
final class Requester
{
	public function __construct(
		public readonly Transport $transport,
		public readonly ?string $onBehalfOf = null,
	) {
	}

	/**
	 * Formats an API path, escaping every segment (`a/b` is sent as `a%2Fb`).
	 *
	 *     Requester::path('/product/reference/%s', $reference)
	 */
	public static function path(string $format, string ...$segments): string
	{
		return sprintf($format, ...array_map(Transport::escapeSegment(...), $segments));
	}

	/**
	 * Sends a call and hydrates its JSON answer.
	 *
	 * @template T
	 *
	 * @param callable(mixed): T                $hydrate
	 * @param array<string,string>              $query
	 * @param array<string,mixed>|stdClass|null $body
	 *
	 * @return T
	 */
	public function call(
		string $method,
		string $path,
		callable $hydrate,
		array $query = [],
		array|stdClass|null $body = null,
		bool $idempotent = false,
		?RequestOptions $options = null,
	): mixed {
		return $this->callWithStatus($method, $path, $hydrate, $query, $body, $idempotent, $options)[0];
	}

	/**
	 * {@see call()} that also returns the HTTP status (force-cancel: 202 = still confirming).
	 *
	 * @template T
	 *
	 * @param callable(mixed): T                $hydrate
	 * @param array<string,string>              $query
	 * @param array<string,mixed>|stdClass|null $body
	 *
	 * @return array{0: T, 1: int}
	 */
	public function callWithStatus(
		string $method,
		string $path,
		callable $hydrate,
		array $query = [],
		array|stdClass|null $body = null,
		bool $idempotent = false,
		?RequestOptions $options = null,
	): array {
		$response = $this->transport->send($method, $path, $query, $body, $idempotent, $this->onBehalfOf, $options);

		return [Transport::decode($response, $hydrate), $response->status];
	}

	/**
	 * Sends a call and ignores its answer's body (deletes answer `{"message": …}`, 204, or nothing).
	 *
	 * @param array<string,mixed>|stdClass|null $body
	 */
	public function void(string $method, string $path, array|stdClass|null $body = null, ?RequestOptions $options = null): void
	{
		$this->transport->send($method, $path, [], $body, false, $this->onBehalfOf, $options);
	}

	/**
	 * Sends a call and returns its answer's body as text (the CSV export); error answers are
	 * still parsed as JSON errors.
	 *
	 * @param array<string,string> $query
	 */
	public function text(string $path, array $query, ?RequestOptions $options = null): string
	{
		return $this->transport->send('GET', $path, $query, null, false, $this->onBehalfOf, $options, 'text/csv, application/json')->body;
	}

	/**
	 * Hydrates a JSON object into one model.
	 *
	 * @template T
	 *
	 * @param callable(array<string,mixed>): T $factory
	 *
	 * @return Closure(mixed): T
	 */
	public static function one(callable $factory): Closure
	{
		return static fn (mixed $decoded): mixed => Cast::one($decoded, $factory);
	}

	/**
	 * Hydrates a JSON list (null: empty) into models.
	 *
	 * @template T
	 *
	 * @param callable(array<string,mixed>): T $factory
	 *
	 * @return Closure(mixed): list<T>
	 */
	public static function list(callable $factory): Closure
	{
		return static fn (mixed $decoded): array => Cast::list($decoded, $factory);
	}

	/**
	 * Hydrates a page `{items, nextCursor}`.
	 *
	 * @template T
	 *
	 * @param callable(array<string,mixed>): T $factory
	 *
	 * @return Closure(mixed): Page<T>
	 */
	public static function page(callable $factory): Closure
	{
		return static fn (mixed $decoded): Page => Cast::one($decoded, static fn (array $data): Page => Page::fromArray($data, $factory));
	}

	/**
	 * Walks a cursor-paginated list lazily, one request per page, from `$cursor` (null: the
	 * first page). It stops when the caller stops, on the last page (`nextCursor` null), on an
	 * empty page, or on a cursor that does not move; an error is thrown from the iteration.
	 *
	 * @template T
	 *
	 * @param Closure(?string): Page<T> $fetch Requests one page with the given cursor.
	 *
	 * @return Generator<int,T>
	 */
	public static function walk(?string $cursor, Closure $fetch): Generator
	{
		while (true) {
			$page = $fetch($cursor);
			foreach ($page->items as $item) {
				yield $item;
			}
			if ($page->nextCursor === null || $page->items === [] || $page->nextCursor === $cursor) {
				return;
			}
			$cursor = $page->nextCursor;
		}
	}
}
