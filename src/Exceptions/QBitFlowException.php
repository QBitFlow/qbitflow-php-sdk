<?php

declare(strict_types=1);

namespace QBitFlow\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Base class of every exception the SDK throws: catch it to catch them all.
 *
 * Every SDK exception carries the same payload, read through properties or getters:
 *
 * | Property       | Getter              | Meaning                                                               |
 * |----------------|---------------------|-----------------------------------------------------------------------|
 * | `status`       | `getStatus()`       | the HTTP status; `0` when no response was received                    |
 * | `apiCode`      | `getApiCode()`      | the API's machine-readable `code` (`not_found`…); `''` when absent    |
 * | `errorMessage` | `getErrorMessage()` | the API's message (else a status default), without the suffixes       |
 * | `details`      | `getDetails()`      | the error body's `details` object (e.g. `details.field`); never null  |
 * | `requestId`    | `getRequestId()`    | the body's `requestId`, else the `X-Request-Id` response header        |
 * | `fieldErrors`  | `getFieldErrors()`  | the failing inputs ({@see FieldError}), from `details.errors`          |
 * | `rawBody`      | `getRawBody()`      | the error response's body as received                                  |
 *
 * **`apiCode`, not `getCode()`:** PHP's `Exception::getCode()` is an integer, so the API's
 * string code lives in `apiCode` / `getApiCode()`. `getCode()` returns the HTTP status.
 *
 * `getMessage()` reads `"<message> (status <status>, code <code>, request <requestId>)"`,
 * followed by `"; <field>: <message>"` for each field error (parts without a value are left out).
 */
class QBitFlowException extends RuntimeException implements ExceptionInterface
{
	/** The HTTP status; `0` when no response was received (client-side checks, network errors). */
	public readonly int $status;

	/** The API's machine-readable error code (e.g. `unique_violation`); `''` when absent. */
	public readonly string $apiCode;

	/** The error's message (the body's `error`, else a status default), without the suffixes. */
	public readonly string $errorMessage;

	/**
	 * The error body's `details` object, decoded (e.g. `['field' => 'reference']`); `[]` when absent.
	 *
	 * @var array<string,mixed>
	 */
	public readonly array $details;

	/** The request's id (quote it to support): the body's `requestId`, else the `X-Request-Id` header. */
	public readonly string $requestId;

	/**
	 * The failing inputs, by wire name (`details.errors`, or the SDK's own checks).
	 *
	 * @var list<FieldError>
	 */
	public readonly array $fieldErrors;

	/** The error response's body as received (it may carry a development server's debug text). */
	public readonly string $rawBody;

	/**
	 * @param string              $message     The error's message, without the status/code/request suffix.
	 * @param array<string,mixed> $details
	 * @param list<FieldError>    $fieldErrors
	 */
	public function __construct(
		string $message = '',
		int $status = 0,
		string $apiCode = '',
		array $details = [],
		string $requestId = '',
		array $fieldErrors = [],
		string $rawBody = '',
		?Throwable $previous = null,
	) {
		$this->status = $status;
		$this->apiCode = $apiCode;
		$this->errorMessage = $message;
		$this->details = $details;
		$this->requestId = $requestId;
		$this->fieldErrors = $fieldErrors;
		$this->rawBody = $rawBody;

		parent::__construct(self::format($message, $status, $apiCode, $requestId, $fieldErrors, $previous), $status, $previous);
	}

	/** The HTTP status; `0` when no response was received. */
	public function getStatus(): int
	{
		return $this->status;
	}

	/** The API's error code (`Exception::getCode()` is the integer HTTP status). */
	public function getApiCode(): string
	{
		return $this->apiCode;
	}

	/** The error's message, without the status/code/request suffix. */
	public function getErrorMessage(): string
	{
		return $this->errorMessage;
	}

	/** @return array<string,mixed> */
	public function getDetails(): array
	{
		return $this->details;
	}

	public function getRequestId(): string
	{
		return $this->requestId;
	}

	/** @return list<FieldError> */
	public function getFieldErrors(): array
	{
		return $this->fieldErrors;
	}

	public function getRawBody(): string
	{
		return $this->rawBody;
	}

	/**
	 * Whether this is a failure the retry policy treats as transient: a network error or
	 * timeout, a 5xx, a 429, or a 409 `idempotency_key_in_use`. It does not say whether the
	 * method may be retried (only reads and the idempotent creates are).
	 */
	public function isRetryable(): bool
	{
		return match (true) {
			$this instanceof NetworkException, $this instanceof RateLimitException => true,
			$this instanceof ServerException => $this->status >= 500,
			$this instanceof ConflictException => $this->apiCode === 'idempotency_key_in_use',
			default => false,
		};
	}

	/**
	 * @param list<FieldError> $fieldErrors
	 */
	private static function format(
		string $message,
		int $status,
		string $apiCode,
		string $requestId,
		array $fieldErrors,
		?Throwable $previous,
	): string {
		$out = $message !== '' ? $message : 'qbitflow error';

		$meta = [];
		if ($status !== 0) {
			$meta[] = 'status ' . $status;
		}
		if ($apiCode !== '') {
			$meta[] = 'code ' . $apiCode;
		}
		if ($requestId !== '') {
			$meta[] = 'request ' . $requestId;
		}
		if ($meta !== []) {
			$out .= ' (' . implode(', ', $meta) . ')';
		}

		foreach ($fieldErrors as $fieldError) {
			$out .= '; ' . $fieldError->field . ': ' . $fieldError->message;
		}

		if ($previous !== null && $previous->getMessage() !== '') {
			$out .= ': ' . $previous->getMessage();
		}

		return $out;
	}
}
