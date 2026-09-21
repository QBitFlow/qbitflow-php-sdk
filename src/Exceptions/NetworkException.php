<?php

declare(strict_types=1);

namespace QBitFlow\Exceptions;

/**
 * Thrown when the request never reached the API: connection failures, DNS errors and timeouts.
 */
class NetworkException extends QBitFlowException
{
	public function __construct(
		string $message = 'Network request failed',
		?int $statusCode = null,
		?array $response = null,
		?\Throwable $previous = null,
	) {
		parent::__construct($message, $statusCode, $response, $previous);
	}
}
