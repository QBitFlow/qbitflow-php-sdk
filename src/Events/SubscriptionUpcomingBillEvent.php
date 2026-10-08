<?php

declare(strict_types=1);

namespace QBitFlow\Events;

use QBitFlow\Models\SubscriptionUpcomingBill;
use QBitFlow\Support\Cast;

/**
 * `subscription.upcomingBill`: A bill (or a trial's end) is near (`data->billingDate`).
 */
final readonly class SubscriptionUpcomingBillEvent extends Event
{
	/** The event's data. */
	public SubscriptionUpcomingBill $data;

	/**
	 * @param array<string,mixed> $data
	 */
	protected function __construct(array $data)
	{
		parent::__construct($data);
		$this->data = Cast::object($data, 'data', SubscriptionUpcomingBill::fromArray(...));
	}
}
