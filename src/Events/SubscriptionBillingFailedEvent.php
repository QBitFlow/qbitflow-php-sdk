<?php

declare(strict_types=1);

namespace QBitFlow\Events;

use QBitFlow\Models\SubscriptionBillingFailed;
use QBitFlow\Support\Cast;

/**
 * `subscription.billingFailed`: A bill attempt failed (`data->reason`, `data->remainingAttempts`).
 */
final readonly class SubscriptionBillingFailedEvent extends Event
{
	/** The event's data. */
	public SubscriptionBillingFailed $data;

	/**
	 * @param array<string,mixed> $data
	 */
	protected function __construct(array $data)
	{
		parent::__construct($data);
		$this->data = Cast::object($data, 'data', SubscriptionBillingFailed::fromArray(...));
	}
}
