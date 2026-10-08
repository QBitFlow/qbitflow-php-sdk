<?php

declare(strict_types=1);

namespace QBitFlow\Laravel\Events;

use QBitFlow\Events\CheckoutExpiredEvent;
use QBitFlow\Models;

/**
 * Dispatched for a verified `checkout.expired` webhook: A checkout session expired unpaid: release what the order holds.
 *
 * Deliveries are at least once: deduplicate on `$event->id`. Do the work in a queued
 * listener; the webhook is answered 200 as soon as the events are dispatched.
 */
final class CheckoutExpired
{
	/** The event's data. */
	public readonly Models\PaymentSessionData|Models\SubscriptionSessionData $data;

	public function __construct(
		/** The verified webhook event. */
		public readonly CheckoutExpiredEvent $event,
	) {
		$this->data = $event->data;
	}
}
