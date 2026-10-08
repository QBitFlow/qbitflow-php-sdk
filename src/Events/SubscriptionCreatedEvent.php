<?php

declare(strict_types=1);

namespace QBitFlow\Events;

use QBitFlow\Models\SubscriptionCreated;
use QBitFlow\Support\Cast;

/**
 * `subscription.created`: A subscription started (status `active`, or `trial`).
 */
final readonly class SubscriptionCreatedEvent extends Event
{
	/** The event's data. */
	public SubscriptionCreated $data;

	/**
	 * @param array<string,mixed> $data
	 */
	protected function __construct(array $data)
	{
		parent::__construct($data);
		$this->data = Cast::object($data, 'data', SubscriptionCreated::fromArray(...));
	}
}
