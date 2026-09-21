<?php

declare(strict_types=1);

namespace QBitFlow\Exceptions;

/**
 * Thrown when a request is rejected as invalid (HTTP 400), and for local input validation before a request is sent.
 */
class ValidationException extends QBitFlowException
{
	public function __construct(
		string $message = 'Validation failed',
		?int $statusCode = null,
		?array $response = null,
		?\Throwable $previous = null,
	) {
		parent::__construct($message, $statusCode, $response, $previous);
	}
}
