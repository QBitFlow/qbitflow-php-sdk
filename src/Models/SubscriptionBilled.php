<?php

declare(strict_types=1);

namespace QBitFlow\Models;

use QBitFlow\Support\Cast;

/**
 * `subscription.billed`'s data: the bill paid, and its subscription as the bill leaves it.
 * Access is paid until `bill->periodEnd`, unless `subscription->status` is `cancelled` (a charge
 * that landed just after a cancellation: record it, grant nothing).
 */
final readonly class SubscriptionBilled extends Model
{
	/**
	 * The bill paid (as `subscriptions->getBill()` returns it): its currency, settlement, and the
	 * period it pays, `periodStart` → `periodEnd`.
	 */
	public Bill $bill;

	/**
	 * The subscription once the bill is paid: active (stopped, paused or cancelled if that
	 * happened while the charge was on its way), its next bill, its allowance, its reference and
	 * its metadata.
	 */
	public Subscription $subscription;

	/**
	 * @param array<string,mixed> $data
	 */
	protected function __construct(array $data)
	{
		$this->bill = Cast::object($data, 'bill', Bill::fromArray(...));
		$this->subscription = Cast::object($data, 'subscription', Subscription::fromArray(...));
	}
}
