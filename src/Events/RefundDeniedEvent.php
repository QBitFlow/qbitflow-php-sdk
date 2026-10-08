<?php

declare(strict_types=1);

namespace QBitFlow\Events;

use QBitFlow\Models\Refund;
use QBitFlow\Support\Cast;

/**
 * `refund.denied`: A refund was denied (a rejected refund).
 */
final readonly class RefundDeniedEvent extends Event
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
