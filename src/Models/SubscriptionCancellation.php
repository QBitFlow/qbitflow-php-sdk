<?php

declare(strict_types=1);

namespace QBitFlow\Models;

/**
 * `subscriptions->cancel()`'s answer: the subscription as the cancellation left it.
 */
final readonly class SubscriptionCancellation
{
	public function __construct(
		/** The subscription as the API answered it. */
		public Subscription $subscription,
		/**
		 * True when the API answered 202: the cancellation is still confirming on-chain and the
		 * subscription's status is not updated yet (`subscription.statusChanged` tells the end).
		 */
		public bool $pending,
	) {
	}
}
