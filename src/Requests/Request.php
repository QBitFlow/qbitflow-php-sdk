<?php

declare(strict_types=1);

namespace QBitFlow\Requests;

use QBitFlow\Exceptions\ValidationException;
use QBitFlow\Http\Transport;

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
	 * Requires an admin- or owner-level API key; a regular user key gets a 403
	 * ({@see \QBitFlow\Exceptions\ForbiddenException}).
	 *
	 * @param int $userId ID of the user to act as.
	 */
	public function onBehalfOf(int $userId): static
	{
		if ($userId <= 0) {
			throw new ValidationException('User ID must be positive');
		}

		return new static($this->transport->withHeader('On-Behalf-Of', (string) $userId));
	}

	/**
	 * Escape a value for safe interpolation into a URL path.
	 *
	 * References and email addresses routinely contain characters such as `/`, `#` and
	 * `+` that would otherwise change the shape of the request.
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
