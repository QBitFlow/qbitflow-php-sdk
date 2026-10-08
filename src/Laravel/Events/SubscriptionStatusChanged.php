<?php

declare(strict_types=1);

namespace QBitFlow\Laravel\Events;

use QBitFlow\Events\SubscriptionStatusChangedEvent;
use QBitFlow\Models;

/**
 * Dispatched for a verified `subscription.statusChanged` webhook: A subscription's status changed (grant access while now < `data->currentPeriodEnd`).
 *
 * Deliveries are at least once: deduplicate on `$event->id`. Do the work in a queued
 * listener; the webhook is answered 200 as soon as the events are dispatched.
 */
final class SubscriptionStatusChanged
{
	/** The event's data. */
	public readonly Models\SubscriptionStatusChanged $data;

	public function __construct(
		/** The verified webhook event. */
		public readonly SubscriptionStatusChangedEvent $event,
	) {
		$this->data = $event->data;
	}
}
