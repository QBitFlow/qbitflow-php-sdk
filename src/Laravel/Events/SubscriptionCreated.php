<?php

declare(strict_types=1);

namespace QBitFlow\Laravel\Events;

use QBitFlow\Events\SubscriptionCreatedEvent;
use QBitFlow\Models;

/**
 * Dispatched for a verified `subscription.created` webhook: A subscription started (active, or in its trial).
 *
 * Deliveries are at least once: deduplicate on `$event->id`. Do the work in a queued
 * listener; the webhook is answered 200 as soon as the events are dispatched.
 */
final class SubscriptionCreated
{
	/** The event's data. */
	public readonly Models\SubscriptionCreated $data;

	public function __construct(
		/** The verified webhook event. */
		public readonly SubscriptionCreatedEvent $event,
	) {
		$this->data = $event->data;
	}
}
