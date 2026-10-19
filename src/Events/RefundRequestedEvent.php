<?php

declare(strict_types=1);

namespace QBitFlow\Events;

use QBitFlow\Models\Refund;
use QBitFlow\Support\Cast;

/**
 * `refund.requested`: A new pending refund: a customer's request (`data->initiatedBy` `customer`)
 * or a merchant's own (`refunds->create()`).
 */
final readonly class RefundRequestedEvent extends Event
{
	/** The event's data. */
	public Refund $data;

	/**
	 * @param array<string,mixed> $data
	 */
	protected function __construct(array $data)
	{
		parent::__construct($data);
		$this->data = Cast::object($data, 'data', Refund::fromArray(...));
	}
}
