<?php

declare(strict_types=1);

namespace QBitFlow\Events;

use QBitFlow\Models\SubscriptionStatusChanged;
use QBitFlow\Support\Cast;

/**
 * `subscription.statusChanged`: A subscription's status changed (`data->previousStatus` → `data->status`).
 */
final readonly class SubscriptionStatusChangedEvent extends Event
{
	/** The event's data. */
	public SubscriptionStatusChanged $data;

	/**
	 * @param array<string,mixed> $data
	 */
	protected function __construct(array $data)
	{
		parent::__construct($data);
		$this->data = Cast::object($data, 'data', SubscriptionStatusChanged::fromArray(...));
	}
}
