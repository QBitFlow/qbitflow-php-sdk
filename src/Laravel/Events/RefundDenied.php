<?php

declare(strict_types=1);

namespace QBitFlow\Laravel\Events;

use QBitFlow\Events\RefundDeniedEvent;
use QBitFlow\Models;

/**
 * Dispatched for a verified `refund.denied` webhook: A refund was denied.
 *
 * Deliveries are at least once: deduplicate on `$event->id`. Do the work in a queued
 * listener; the webhook is answered 200 as soon as the events are dispatched.
 */
final class RefundDenied
{
	/** The event's data. */
	public readonly Models\Refund $data;

	public function __construct(
		/** The verified webhook event. */
		public readonly RefundDeniedEvent $event,
	) {
		$this->data = $event->data;
	}
}
