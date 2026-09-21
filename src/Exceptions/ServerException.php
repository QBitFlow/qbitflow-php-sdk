<?php

declare(strict_types=1);

namespace QBitFlow\Exceptions;

/**
 * Thrown when the API returns a server error (HTTP 5xx) and all retries have been exhausted.
 */
class ServerException extends QBitFlowException
{
	public function __construct(
		string $message = 'Internal server error',
		?int $statusCode = null,
		?array $response = null,
		?\Throwable $previous = null,
	) {
		parent::__construct($message, $statusCode, $response, $previous);
	}
}
