<?php

declare(strict_types=1);

namespace QBitFlow\Exceptions;

/**
 * Thrown when a requested resource does not exist (HTTP 404).
 */
class NotFoundException extends QBitFlowException
{
	public function __construct(
		string $message = 'Resource not found',
		?int $statusCode = null,
		?array $response = null,
		?\Throwable $previous = null,
	) {
		parent::__construct($message, $statusCode, $response, $previous);
	}
}
