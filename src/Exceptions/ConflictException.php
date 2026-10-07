<?php

declare(strict_types=1);

namespace QBitFlow\Exceptions;

/**
 * Thrown when the request conflicts with the current state of the resource (HTTP 409).
 *
 * The one place the API answers 409 today is `executeTestBilling()` on a subscription that
 * is not yet due. Retrying immediately would fail again; wait for the schedule instead.
 */
class ConflictException extends QBitFlowException
{
	public function __construct(
		string $message = 'Conflict',
		?int $statusCode = null,
		?array $response = null,
		?\Throwable $previous = null,
		array $fields = [],
	) {
		parent::__construct($message, $statusCode, $response, $previous, $fields);
	}
}
