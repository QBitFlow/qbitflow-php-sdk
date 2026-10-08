<?php

declare(strict_types=1);

namespace QBitFlow\Models;

use DateTimeImmutable;
use QBitFlow\Support\Cast;

/**
 * A failed attempt to pay (or to send a refund): never final.
 */
final readonly class Attempt extends Model
{
	/**
	 * `failed`.
	 */
	public string $status;

	/**
	 * The error code (e.g. `insufficient_funds`, `token_not_approved`): what to do about it.
	 */
	public ?string $code;

	/**
	 * The reason, for the customer.
	 */
	public ?string $message;

	/**
	 * The attempt's transaction, if it was sent.
	 */
	public ?string $txHash;

	/**
	 * When it failed.
	 */
	public DateTimeImmutable $at;

	/**
	 * @param array<string,mixed> $data
	 */
	protected function __construct(array $data)
	{
		$this->status = Cast::string($data, 'status');
		$this->code = Cast::nullableString($data, 'code');
		$this->message = Cast::nullableString($data, 'message');
		$this->txHash = Cast::nullableString($data, 'txHash');
		$this->at = Cast::date($data, 'at');
	}
}
