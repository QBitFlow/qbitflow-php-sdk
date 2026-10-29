<?php

declare(strict_types=1);

namespace QBitFlow\Models;

use DateTimeImmutable;
use QBitFlow\Support\Cast;

/**
 * Where a checkout session stands, for the merchant that created it
 * (`checkoutSessions->get()` and `expire()`).
 */
final readonly class CheckoutSessionStatus extends Model
{
	/**
	 * The session's id (`pay@…`, `sub@…`), also the id of the payment or subscription it creates.
	 */
	public string $uuid;

	/**
	 * `created` (waiting for the customer; with `lastAttempt`: their last attempt failed, they
	 * may try again until the session expires), `waitingConfirmation` (a transaction was sent),
	 * or the final `completed` and `expired` ({@see \QBitFlow\Enums\CheckoutSessionStatusValue}).
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
	 * The customer's last failed attempt (its code, reason and time): never final, don't cancel
	 * the order on it; wait for `completed` (`payment.completed`, `subscription.created`) or
	 * `expired` (`checkout.expired`).
	 */
	public ?Attempt $lastAttempt;

	/**
	 * When the checkout can no longer be paid, while it is open (on `expire()`'s first answer: when
	 * it was expired); null once completed, expired at its time, or on a repeated `expire()`.
	 */
	public ?DateTimeImmutable $expiresAt;

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
		$this->expiresAt = Cast::nullableDate($data, 'expiresAt');
	}
}
