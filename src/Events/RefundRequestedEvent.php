<?php

declare(strict_types=1);

namespace QBitFlow\Events;

use QBitFlow\Models\Refund;
use QBitFlow\Support\Cast;

/**
 * `refund.requested`: A new pending refund, always a customer's request (`data->initiatedBy`
 * `customer`). A merchant's own refund is announced only by `refund.approved`.
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
