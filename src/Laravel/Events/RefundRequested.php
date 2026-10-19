<?php

declare(strict_types=1);

namespace QBitFlow\Laravel\Events;

use QBitFlow\Events\RefundRequestedEvent;
use QBitFlow\Models;

/**
 * Dispatched for a verified `refund.requested` webhook: A customer requested a refund (pending;
 * always a customer's request: a merchant's own refund is announced only by `refund.approved`).
 *
 * Deliveries are at least once: deduplicate on `$event->id`. Do the work in a queued
 * listener; the webhook is answered 200 as soon as the events are dispatched.
 */
final class RefundRequested
{
	/** The event's data. */
	public readonly Models\Refund $data;

	public function __construct(
		/** The verified webhook event. */
		public readonly RefundRequestedEvent $event,
	) {
		$this->data = $event->data;
	}
}
