<?php

declare(strict_types=1);

namespace QBitFlow\Laravel\Events;

use QBitFlow\Events\SubscriptionActionRequiredChangedEvent;
use QBitFlow\Models;

/**
 * Dispatched for a verified `subscription.actionRequiredChanged` webhook: What a subscription's customer must do changed.
 *
 * Deliveries are at least once: deduplicate on `$event->id`. Do the work in a queued
 * listener; the webhook is answered 200 as soon as the events are dispatched.
 */
final class SubscriptionActionRequiredChanged
{
	/** The event's data. */
	public readonly Models\SubscriptionActionRequiredChanged $data;

	public function __construct(
		/** The verified webhook event. */
		public readonly SubscriptionActionRequiredChangedEvent $event,
	) {
		$this->data = $event->data;
	}
}
