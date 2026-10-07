<?php

declare(strict_types=1);

namespace QBitFlow\Requests;

use QBitFlow\Exceptions\ValidationException;
use QBitFlow\Http\Transport;
use QBitFlow\Support\Cast;
use QBitFlow\Support\CursorData;

/**
 * Base class for every service exposed on {@see \QBitFlow\QBitFlow}.
 *
 * Services are immutable: {@see Request::onBehalfOf()} returns a scoped copy rather than
 * mutating the receiver, so organization-level and per-user calls can be freely mixed.
 */
abstract class Request
{
	public function __construct(protected readonly Transport $transport)
	{
	}

	/**
	 * Act on behalf of a user in your organization, without holding that user's own key.
	 *
	 * Returns a copy of this service that sends the `On-Behalf-Of` header; the service
	 * you called it on is untouched.
	 *
	 * ```php
	 * // List the products belonging to user 123
	 * $products = $client->products->onBehalfOf(123)->getAll();
	 *
	 * // Still organization-level
	 * $all = $client->products->getAll();
	 * ```
	 *
	 * Passing `0` means "act at the organization level": it returns a copy **without** the
	 * header, which is how you undo an earlier `onBehalfOf()` on a scoped service. The API
	 * reads `On-Behalf-Of: 0` the same way.
	 *
	 * Requires an admin- or owner-level API key; a regular user key gets a 403
	 * ({@see \QBitFlow\Exceptions\ForbiddenException}). A user outside your organization
	 * gets a 404 — existence is never revealed across organizations.
	 *
	 * @param int $userId ID of the user to act as, or 0 for the organization itself.
	 *
	 * @throws ValidationException If the ID is negative.
	 */
	public function onBehalfOf(int $userId): static
	{
		if ($userId < 0) {
			throw new ValidationException('User ID must be zero or positive');
		}

		if ($userId === 0) {
			return new static($this->transport->withoutHeader(Transport::ON_BEHALF_OF));
		}

		return new static($this->transport->withHeader(Transport::ON_BEHALF_OF, (string) $userId));
	}

	/**
	 * Hydrate a response body that must be one JSON object.
	 *
	 * @template T of object
	 *
	 * @param callable(array<array-key,mixed>): T $factory
	 *
	 * @return callable(mixed): T
	 */
	protected static function one(callable $factory): callable
	{
		return static fn (mixed $body): object => Cast::one($body, $factory);
	}

	/**
	 * Hydrate a response body that must be a JSON list of objects (`null` = empty).
	 *
	 * @template T of object
	 *
	 * @param callable(array<array-key,mixed>): T $factory
	 *
	 * @return callable(mixed): list<T>
	 */
	protected static function list(callable $factory): callable
	{
		return static fn (mixed $body): array => Cast::listOf($body, $factory);
	}

	/**
	 * Hydrate a cursor-paginated response body.
	 *
	 * @template T of object
	 *
	 * @param callable(array<array-key,mixed>): T $factory
	 *
	 * @return callable(mixed): CursorData<T>
	 */
	protected static function page(callable $factory): callable
	{
		return static fn (mixed $body): CursorData => CursorData::fromArray($body ?? [], $factory);
	}

	/**
	 * Escape a value for safe interpolation into a URL path.
	 *
	 * References and email addresses routinely contain characters such as `/`, `#` and
	 * `+` that would otherwise change the shape of the request. They are escaped correctly,
	 * but note that the API currently cannot route a reference containing `/` (it answers
	 * 404 even when escaped).
	 */
	protected function encode(string $segment): string
	{
		return rawurlencode($segment);
	}

	/**
	 * Guard a required string argument.
	 *
	 * @throws ValidationException
	 */
	protected function requireNonEmpty(string $value, string $label): string
	{
		if (trim($value) === '') {
			throw new ValidationException($label . ' is required');
		}

		return $value;
	}

	/**
	 * Guard a required positive integer argument.
	 *
	 * @throws ValidationException
	 */
	protected function requirePositive(int $value, string $label): int
	{
		if ($value <= 0) {
			throw new ValidationException($label . ' must be positive');
		}

		return $value;
	}
}
