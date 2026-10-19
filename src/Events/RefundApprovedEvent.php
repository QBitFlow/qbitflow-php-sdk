<?php

declare(strict_types=1);

namespace QBitFlow\Events;

use QBitFlow\Models\Refund;
use QBitFlow\Support\Cast;

/**
 * `refund.approved`: A refund was approved, its transfer confirmed (was `refund.completed`):
 * `data->refundPercent` and `data->amount` are the share actually paid back. Reverse the order, in
 * full or in part.
 */
final readonly class RefundApprovedEvent extends Event
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
