<?php

declare(strict_types=1);

namespace QBitFlow\Laravel\Events;

use QBitFlow\Events\SubscriptionUpcomingBillEvent;
use QBitFlow\Models;

/**
 * Dispatched for a verified `subscription.upcomingBill` webhook: A bill (or a trial's end) is near.
 *
 * Deliveries are at least once: deduplicate on `$event->id`. Do the work in a queued
 * listener; the webhook is answered 200 as soon as the events are dispatched.
 */
final class SubscriptionUpcomingBill
{
	/** The event's data. */
	public readonly Models\SubscriptionUpcomingBill $data;

	public function __construct(
		/** The verified webhook event. */
		public readonly SubscriptionUpcomingBillEvent $event,
	) {
		$this->data = $event->data;
	}
}
