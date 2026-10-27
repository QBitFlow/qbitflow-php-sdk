<?php

declare(strict_types=1);

namespace QBitFlow\Laravel\Events;

use QBitFlow\Events\RefundApprovedEvent;
use QBitFlow\Models;

/**
 * Dispatched for a verified `refund.approved` webhook: A refund was approved, its transfer
 * confirmed (was `refund.completed`): reverse the order, in full or in part
 * (`data->refundPercent`). For a merchant's own refund it is the first and only event.
 *
 * Deliveries are at least once: deduplicate on `$event->id`. Do the work in a queued
 * listener; the webhook is answered 200 as soon as the events are dispatched.
 */
final class RefundApproved
{
	/** The event's data. */
	public readonly Models\Refund $data;

	/** Wraps the verified event; its typed data is also on `$data`. */
	public function __construct(
		/** The verified webhook event. */
		public readonly RefundApprovedEvent $event,
	) {
		$this->data = $event->data;
	}
}
