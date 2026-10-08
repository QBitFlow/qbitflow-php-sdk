<?php

declare(strict_types=1);

namespace QBitFlow\Laravel\Events;

use QBitFlow\Events\PaymentCompletedEvent;
use QBitFlow\Models;

/**
 * Dispatched for a verified `payment.completed` webhook: A one-time payment was confirmed: fulfil the order.
 *
 * Deliveries are at least once: deduplicate on `$event->id`. Do the work in a queued
 * listener; the webhook is answered 200 as soon as the events are dispatched.
 */
final class PaymentCompleted
{
	/** The event's data. */
	public readonly Models\PaymentCompleted $data;

	public function __construct(
		/** The verified webhook event. */
		public readonly PaymentCompletedEvent $event,
	) {
		$this->data = $event->data;
	}
}
