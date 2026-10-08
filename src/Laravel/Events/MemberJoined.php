<?php

declare(strict_types=1);

namespace QBitFlow\Laravel\Events;

use QBitFlow\Events\MemberJoinedEvent;
use QBitFlow\Models;

/**
 * Dispatched for a verified `member.joined` webhook: Someone accepted an invitation: store `data->userUuid` (match `data->invitationUuid`).
 *
 * Deliveries are at least once: deduplicate on `$event->id`. Do the work in a queued
 * listener; the webhook is answered 200 as soon as the events are dispatched.
 */
final class MemberJoined
{
	/** The event's data. */
	public readonly Models\MemberJoined $data;

	public function __construct(
		/** The verified webhook event. */
		public readonly MemberJoinedEvent $event,
	) {
		$this->data = $event->data;
	}
}
