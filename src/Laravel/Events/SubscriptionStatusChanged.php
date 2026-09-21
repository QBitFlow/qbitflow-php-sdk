<?php

declare(strict_types=1);

namespace QBitFlow\Laravel\Events;

use QBitFlow\Dto\SubscriptionStatusTransition;

/**
 * Fired when an existing subscription changes status — cancelled, past due, low on
 * funds, and so on.
 *
 * Listening for this is the recommended alternative to polling subscription status on a
 * schedule.
 */
final class SubscriptionStatusChanged
{
	public function __construct(
		/** The status transition that occurred. */
		public readonly SubscriptionStatusTransition $transition,
	) {
	}

	/** Your own reference for the subscription, when you set one at creation. */
	public function reference(): ?string
	{
		return $this->transition->subscriptionReference;
	}
}
