<?php

declare(strict_types=1);

namespace QBitFlow\Laravel\Events;

use QBitFlow\Dto\Session\SessionWebhookResponse;

/**
 * Fired when a checkout you created was completed by the customer.
 *
 * Either a one-time payment was paid, or a subscription checkout was completed — in
 * which case the first billing has already been taken.
 *
 * ```php
 * // app/Providers/EventServiceProvider.php
 * protected $listen = [
 *     TransactionWebhookReceived::class => [MarkOrderPaid::class],
 * ];
 * ```
 */
final class TransactionWebhookReceived
{
	public function __construct(
		/** The verified webhook payload. */
		public readonly SessionWebhookResponse $payload,
	) {
	}

	/**
	 * Your own reference for the transaction, when you set one on the session.
	 *
	 * This is usually the fastest way back to your own order or invoice.
	 */
	public function reference(): ?string
	{
		return $this->payload->session->reference;
	}

	/** Whether this event announces a newly created subscription. */
	public function isSubscription(): bool
	{
		return $this->payload->isSubscription();
	}
}
