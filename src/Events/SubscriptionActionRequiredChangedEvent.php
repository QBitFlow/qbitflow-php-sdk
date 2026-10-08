<?php

declare(strict_types=1);

namespace QBitFlow\Events;

use QBitFlow\Models\SubscriptionActionRequiredChanged;
use QBitFlow\Support\Cast;

/**
 * `subscription.actionRequiredChanged`: What a subscription's customer must do changed (`data->actionRequired`).
 */
final readonly class SubscriptionActionRequiredChangedEvent extends Event
{
	/** The event's data. */
	public SubscriptionActionRequiredChanged $data;

	/**
	 * @param array<string,mixed> $data
	 */
	protected function __construct(array $data)
	{
		parent::__construct($data);
		$this->data = Cast::object($data, 'data', SubscriptionActionRequiredChanged::fromArray(...));
	}
}
