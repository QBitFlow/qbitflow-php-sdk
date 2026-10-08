<?php

declare(strict_types=1);

namespace QBitFlow\Events;

use QBitFlow\Models\PaymentCompleted;
use QBitFlow\Support\Cast;

/**
 * `payment.completed`: A one-time payment was confirmed: fulfil the order (`data->reference`).
 */
final readonly class PaymentCompletedEvent extends Event
{
	/** The event's data. */
	public PaymentCompleted $data;

	/**
	 * @param array<string,mixed> $data
	 */
	protected function __construct(array $data)
	{
		parent::__construct($data);
		$this->data = Cast::object($data, 'data', PaymentCompleted::fromArray(...));
	}
}
