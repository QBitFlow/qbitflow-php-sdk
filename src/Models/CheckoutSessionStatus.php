<?php

declare(strict_types=1);

namespace QBitFlow\Models;

use QBitFlow\Support\Cast;

/**
 * A checkout session's status.
 */
final readonly class CheckoutSessionStatus extends Model
{
	/**
	 * The session's id (`pay@…`, `sub@…`).
	 */
	public string $uuid;

	/**
	 * `created` (waiting for the customer; lastAttempt: their last attempt failed), `waitingConfirmation`, `completed` or `expired` ({@see \QBitFlow\Enums\CheckoutSessionStatusValue}).
	 */
	public string $status;

	/**
	 * The customer's transaction, once sent (not kept for completed subscriptions).
	 */
	public ?string $txHash;

	/**
	 * An optional detail, e.g. why the session expired.
	 */
	public ?string $message;

	/**
	 * The customer's last failed attempt: never final, don't cancel the order on it.
	 */
	public ?Attempt $lastAttempt;

	/**
	 * @param array<string,mixed> $data
	 */
	protected function __construct(array $data)
	{
		$this->uuid = Cast::string($data, 'uuid');
		$this->status = Cast::string($data, 'status');
		$this->txHash = Cast::nullableString($data, 'txHash');
		$this->message = Cast::nullableString($data, 'message');
		$this->lastAttempt = Cast::nullableObject($data, 'lastAttempt', Attempt::fromArray(...));
	}
}
