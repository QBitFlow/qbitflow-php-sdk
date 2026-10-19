<?php

declare(strict_types=1);

namespace QBitFlow\Http;

use Closure;
use Generator;
use QBitFlow\Exceptions\NotFoundException;
use QBitFlow\Page;
use QBitFlow\RequestOptions;
use QBitFlow\Support\Cast;
use QBitFlow\Support\Validator;
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
	 *     Requester::path('/products/%s', $uuid)
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
	 * @param array<string,string|list<string>> $query
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
	 * {@see call()} that also returns the HTTP status (a subscription's cancel: 202 = still
	 * confirming on-chain).
	 *
	 * @template T
	 *
	 * @param callable(mixed): T                $hydrate
	 * @param array<string,string|list<string>> $query
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
	 * Sends a call and ignores its answer's body (every DELETE answers 204, no body).
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
	 * @param array<string,string|list<string>> $query
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
	 * Hydrates a page `{items, hasMore, nextCursor?}`.
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
	 * Sends a bounded list's call (one page, `hasMore` false: currencies, wallets, held funds,
	 * the accounting export) and returns its items. Defensively, a page that claims more is
	 * followed with its `nextCursor` (the query kept) until `hasMore` is false, so a list the
	 * server ever splits is never cut short silently; the walk stops on the same guards as
	 * {@see walk()} (no next cursor, a cursor that did not move, an empty page).
	 *
	 * @template T
	 *
	 * @param callable(array<string,mixed>): T  $factory
	 * @param array<string,string|list<string>> $query
	 *
	 * @return list<T>
	 */
	public function items(string $path, callable $factory, array $query = [], ?RequestOptions $options = null): array
	{
		$items = [];
		$cursor = null;
		while (true) {
			$page = $this->call('GET', $path, self::page($factory), $query, options: $options);
			array_push($items, ...$page->items);
			if (! self::hasNext($page, $cursor)) {
				return $items;
			}
			$cursor = $page->nextCursor;
			$query['cursor'] = $cursor;
		}
	}

	/**
	 * Runs a list's `?reference=` filter (a page of 0 or 1 item) and returns the item whose
	 * reference is exactly the one asked for: checking it means a server that ignored the filter
	 * can never hand back another resource. None is a {@see NotFoundException}, like a 404
	 * (status 404, code `not_found`), carrying the list answer's request id. The reference is
	 * checked like a created one's (1 to 100 of `A-Z a-z 0-9 . _ : @ -`).
	 *
	 * @template T of object
	 *
	 * @param callable(array<string,mixed>): T $factory
	 * @param callable(T): ?string             $reference Reads an item's reference.
	 * @param string                           $what      Names the resource in the error's message (`product`).
	 *
	 * @return T
	 *
	 * @throws \QBitFlow\Exceptions\ValidationException For a blank or malformed reference (nothing is sent).
	 * @throws NotFoundException                         When no item has this reference.
	 */
	public function getByReference(string $path, string $what, string $value, callable $factory, callable $reference, ?RequestOptions $options = null): object
	{
		$v = new Validator();
		if ($v->required('reference', $value)) {
			$v->reference('reference', $value);
		}
		$v->throwIfAny();

		$response = $this->transport->send('GET', $path, ['reference' => $value], null, false, $this->onBehalfOf, $options);
		$page = Transport::decode($response, self::page($factory));
		foreach ($page->items as $item) {
			if ($reference($item) === $value) {
				return $item;
			}
		}

		throw new NotFoundException(
			sprintf('no %s with this reference', $what),
			404,
			'not_found',
			requestId: $response->header('X-Request-Id'),
		);
	}

	/**
	 * Walks a cursor-paginated list lazily, one request per page, from `$cursor` (null: the
	 * first page). It stops when the caller stops, or after the page whose `hasMore` is false;
	 * an error is thrown from the iteration. Defensively, it also stops on a page claiming more
	 * without a `nextCursor`, on one whose `nextCursor` is the cursor just requested (which would
	 * loop on the same page forever), and on an empty page that claims more (the API never
	 * answers one: a server fault, which could otherwise cycle between cursors).
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
			if (! self::hasNext($page, $cursor)) {
				return;
			}
			$cursor = $page->nextCursor;
		}
	}

	/**
	 * Whether a walk goes on after `$page`, read with `$cursor`.
	 *
	 * @param Page<mixed> $page
	 */
	private static function hasNext(Page $page, ?string $cursor): bool
	{
		return $page->hasMore && $page->nextCursor !== null && $page->nextCursor !== '' && $page->nextCursor !== $cursor && $page->items !== [];
	}
}
