<?php

declare(strict_types=1);

namespace QBitFlow\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Base exception for every error raised by the QBitFlow SDK.
 *
 * Catching this type catches all SDK errors:
 *
 * ```php
 * try {
 *     $payment = $client->oneTimePayments->get('payment-uuid');
 * } catch (QBitFlowException $e) {
 *     report($e->getMessage(), $e->getStatusCode());
 * }
 * ```
 */
class QBitFlowException extends RuntimeException
{
	/**
	 * @param string             $message    Human-readable error message.
	 * @param int|null           $statusCode HTTP status code, when the error came from a response.
	 * @param array<string,mixed>|null $response Decoded response body, when available.
	 * @param list<FieldError>   $fields     Per-field validation failures, when the API reported them.
	 */
	public function __construct(
		string $message,
		private readonly ?int $statusCode = null,
		private readonly ?array $response = null,
		?Throwable $previous = null,
		private readonly array $fields = [],
	) {
		parent::__construct($message, $statusCode ?? 0, $previous);
	}

	/**
	 * Per-field validation failures, when the API reported them; empty otherwise.
	 *
	 * Prefer this over parsing the message when you need to map failures back onto form
	 * fields. The message lists every failure too, but as prose.
	 *
	 * @return list<FieldError>
	 */
	public function getFields(): array
	{
		return $this->fields;
	}

	/** HTTP status code that produced this error, or null for local/network failures. */
	public function getStatusCode(): ?int
	{
		return $this->statusCode;
	}

	/**
	 * Decoded response body that produced this error, when the API returned one.
	 *
	 * @return array<string,mixed>|null
	 */
	public function getResponse(): ?array
	{
		return $this->response;
	}

	public function __toString(): string
	{
		return $this->statusCode !== null
			? sprintf('[%d] %s', $this->statusCode, $this->getMessage())
			: $this->getMessage();
	}
}
