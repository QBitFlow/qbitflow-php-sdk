<?php

declare(strict_types=1);

namespace QBitFlow\Laravel\Events;

use QBitFlow\Events\WebhookTestEvent;
use QBitFlow\Models;

/**
 * Dispatched for a verified `webhook.test` webhook: The dashboard's test delivery reached your endpoint.
 *
 * Deliveries are at least once: deduplicate on `$event->id`. Do the work in a queued
 * listener; the webhook is answered 200 as soon as the events are dispatched.
 */
final class WebhookTestReceived
{
	/** The event's data. */
	public readonly Models\WebhookTest $data;

	public function __construct(
		/** The verified webhook event. */
		public readonly WebhookTestEvent $event,
	) {
		$this->data = $event->data;
	}
}
