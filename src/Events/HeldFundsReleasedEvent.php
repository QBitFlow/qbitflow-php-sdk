<?php

declare(strict_types=1);

namespace QBitFlow\Events;

use QBitFlow\Models\HeldFundsReleased;
use QBitFlow\Support\Cast;

/**
 * `heldFunds.released`: The funds held for a member were paid out to them.
 */
final readonly class HeldFundsReleasedEvent extends Event
{
	/** The event's data. */
	public HeldFundsReleased $data;

	/**
	 * @param array<string,mixed> $data
	 */
	protected function __construct(array $data)
	{
		parent::__construct($data);
		$this->data = Cast::object($data, 'data', HeldFundsReleased::fromArray(...));
	}
}
