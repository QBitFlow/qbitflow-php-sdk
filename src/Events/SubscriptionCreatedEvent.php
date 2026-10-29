<?php

declare(strict_types=1);

namespace QBitFlow\Events;

use QBitFlow\Models\Subscription;
use QBitFlow\Support\Cast;

/**
 * `subscription.created`: A subscription started (status `active`, or `trial`): grant access until
 * `data->accessUntil` (`data->hasAccess()`). The data is the subscription as `subscriptions->get()` returns it, its
 * `managementPageLink` included.
 */
final readonly class SubscriptionCreatedEvent extends Event
{
	/** The event's data. */
	public Subscription $data;

	/**
	 * @param array<string,mixed> $data
	 */
	protected function __construct(array $data)
	{
		parent::__construct($data);
		$this->data = Cast::object($data, 'data', Subscription::fromArray(...));
	}
}
