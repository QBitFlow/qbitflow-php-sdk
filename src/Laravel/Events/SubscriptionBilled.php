<?php

declare(strict_types=1);

namespace QBitFlow\Laravel\Events;

use QBitFlow\Dto\SubscriptionHistory;

/**
 * Fired when an active subscription renewed for a new period and was billed successfully.
 *
 * Record the billing against your own records; the payload carries the full history entry.
 */
final class SubscriptionBilled
{
	public function __construct(
		/** The billing record for this cycle. */
		public readonly SubscriptionHistory $billing,
		/** Your own reference for the subscription, when you set one at creation. */
		public readonly ?string $subscriptionReference = null,
	) {
	}

	/** Your own reference for the subscription, when you set one at creation. */
	public function reference(): ?string
	{
		return $this->subscriptionReference;
	}
}
