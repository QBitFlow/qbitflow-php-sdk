<?php

declare(strict_types=1);

namespace QBitFlow\Exceptions;

/**
 * Thrown when authentication fails (HTTP 401) — usually an invalid or missing API key.
 */
class UnauthorizedException extends QBitFlowException
{
	public function __construct(
		string $message = 'Unauthorized: Invalid API key',
		?int $statusCode = null,
		?array $response = null,
		?\Throwable $previous = null,
		array $fields = [],
	) {
		parent::__construct($message, $statusCode, $response, $previous, $fields);
	}
}
