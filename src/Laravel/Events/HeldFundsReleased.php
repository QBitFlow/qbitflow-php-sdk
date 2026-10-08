<?php

declare(strict_types=1);

namespace QBitFlow\Laravel\Events;

use QBitFlow\Events\HeldFundsReleasedEvent;
use QBitFlow\Models;

/**
 * Dispatched for a verified `heldFunds.released` webhook: The funds held for a member were paid out to them.
 *
 * Deliveries are at least once: deduplicate on `$event->id`. Do the work in a queued
 * listener; the webhook is answered 200 as soon as the events are dispatched.
 */
final class HeldFundsReleased
{
	/** The event's data. */
	public readonly Models\HeldFundsReleased $data;

	public function __construct(
		/** The verified webhook event. */
		public readonly HeldFundsReleasedEvent $event,
	) {
		$this->data = $event->data;
	}
}
