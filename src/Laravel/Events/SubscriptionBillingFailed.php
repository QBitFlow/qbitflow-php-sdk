<?php

declare(strict_types=1);

namespace QBitFlow\Laravel\Events;

use QBitFlow\Events\SubscriptionBillingFailedEvent;
use QBitFlow\Models;

/**
 * Dispatched for a verified `subscription.billingFailed` webhook: A bill attempt failed: point the customer to `data->managementPageLink`.
 *
 * Deliveries are at least once: deduplicate on `$event->id`. Do the work in a queued
 * listener; the webhook is answered 200 as soon as the events are dispatched.
 */
final class SubscriptionBillingFailed
{
	/** The event's data. */
	public readonly Models\SubscriptionBillingFailed $data;

	public function __construct(
		/** The verified webhook event. */
		public readonly SubscriptionBillingFailedEvent $event,
	) {
		$this->data = $event->data;
	}
}
