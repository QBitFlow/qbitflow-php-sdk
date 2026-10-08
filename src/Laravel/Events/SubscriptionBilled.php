<?php

declare(strict_types=1);

namespace QBitFlow\Laravel\Events;

use QBitFlow\Events\SubscriptionBilledEvent;
use QBitFlow\Models;

/**
 * Dispatched for a verified `subscription.billed` webhook: A subscription's bill was paid: extend access to `data->periodEnd`.
 *
 * Deliveries are at least once: deduplicate on `$event->id`. Do the work in a queued
 * listener; the webhook is answered 200 as soon as the events are dispatched.
 */
final class SubscriptionBilled
{
	/** The event's data. */
	public readonly Models\SubscriptionBilled $data;

	public function __construct(
		/** The verified webhook event. */
		public readonly SubscriptionBilledEvent $event,
	) {
		$this->data = $event->data;
	}
}
