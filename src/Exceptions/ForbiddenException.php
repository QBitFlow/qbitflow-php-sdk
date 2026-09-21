<?php

declare(strict_types=1);

namespace QBitFlow\Exceptions;

/**
 * Thrown when the API key is valid but not allowed to perform the request (HTTP 403).
 */
class ForbiddenException extends QBitFlowException
{
	public function __construct(
		string $message = 'Forbidden: Access denied',
		?int $statusCode = null,
		?array $response = null,
		?\Throwable $previous = null,
	) {
		parent::__construct($message, $statusCode, $response, $previous);
	}
}
