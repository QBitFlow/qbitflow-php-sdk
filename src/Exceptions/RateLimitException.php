<?php

declare(strict_types=1);

namespace QBitFlow\Exceptions;

use Throwable;

/**
 * A 429. Thrown once the retries are exhausted, or at once when the API asks to wait more
 * than 60 seconds.
 */
class RateLimitException extends ApiException
{
	/**
	 * @param array<string,mixed> $details
	 * @param list<FieldError>    $fieldErrors
	 */
	public function __construct(
		string $message = '',
		int $status = 429,
		string $apiCode = '',
		array $details = [],
		string $requestId = '',
		array $fieldErrors = [],
		string $rawBody = '',
		?Throwable $previous = null,
		/** How long the API asked to wait, in seconds (`Retry-After`, else `details.retryAfterSeconds`); 0 when unknown. */
		public readonly int $retryAfter = 0,
		/** The number of requests allowed per period (`details.limit`); 0 when unknown. */
		public readonly int $limit = 0,
		/** The limit's period, in seconds (`details.periodSeconds`); 0 when unknown. */
		public readonly int $periodSeconds = 0,
	) {
		parent::__construct($message, $status, $apiCode, $details, $requestId, $fieldErrors, $rawBody, $previous);
	}

	/** Seconds the API asked to wait; 0 when unknown. */
	public function getRetryAfter(): int
	{
		return $this->retryAfter;
	}

	public function getLimit(): int
	{
		return $this->limit;
	}

	public function getPeriodSeconds(): int
	{
		return $this->periodSeconds;
	}
}
