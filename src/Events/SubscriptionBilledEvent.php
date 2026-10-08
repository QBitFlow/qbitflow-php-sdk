<?php

declare(strict_types=1);

namespace QBitFlow\Events;

use QBitFlow\Models\SubscriptionBilled;
use QBitFlow\Support\Cast;

/**
 * `subscription.billed`: A subscription's bill was paid (paid until `data->periodEnd`).
 */
final readonly class SubscriptionBilledEvent extends Event
{
	/** The event's data. */
	public SubscriptionBilled $data;

	/**
	 * @param array<string,mixed> $data
	 */
	protected function __construct(array $data)
	{
		parent::__construct($data);
		$this->data = Cast::object($data, 'data', SubscriptionBilled::fromArray(...));
	}
}
