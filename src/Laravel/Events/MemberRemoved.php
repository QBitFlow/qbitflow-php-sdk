<?php

declare(strict_types=1);

namespace QBitFlow\Laravel\Events;

use QBitFlow\Events\MemberRemovedEvent;
use QBitFlow\Models;

/**
 * Dispatched for a verified `member.removed` webhook: A member was removed.
 *
 * Deliveries are at least once: deduplicate on `$event->id`. Do the work in a queued
 * listener; the webhook is answered 200 as soon as the events are dispatched.
 */
final class MemberRemoved
{
	/** The event's data. */
	public readonly Models\Member $data;

	public function __construct(
		/** The verified webhook event. */
		public readonly MemberRemovedEvent $event,
	) {
		$this->data = $event->data;
	}
}
