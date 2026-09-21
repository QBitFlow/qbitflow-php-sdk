<?php

declare(strict_types=1);

namespace QBitFlow\Exceptions;

use Throwable;

/**
 * Thrown when the API rate limit is exceeded (HTTP 429).
 *
 * When the API sends a `Retry-After` header its value is exposed through
 * {@see RateLimitException::getRetryAfter()}. The SDK never retries rate-limited
 * requests automatically — back off and retry on your own schedule.
 */
class RateLimitException extends QBitFlowException
{
	public function __construct(
		string $message = 'Rate limit exceeded',
		?int $statusCode = null,
		?array $response = null,
		private readonly ?int $retryAfter = null,
		?Throwable $previous = null,
	) {
		parent::__construct($message, $statusCode, $response, $previous);
	}

	/** Seconds to wait before retrying, as advertised by the `Retry-After` header. */
	public function getRetryAfter(): ?int
	{
		return $this->retryAfter;
	}
}
