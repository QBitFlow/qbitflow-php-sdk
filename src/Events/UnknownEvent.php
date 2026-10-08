<?php

declare(strict_types=1);

namespace QBitFlow\Events;

/**
 * An event of a type this SDK does not know yet (added to the API after this release). Its
 * `data` is kept raw: acknowledge it (answer 2xx), and update the SDK to handle it.
 */
final readonly class UnknownEvent extends Event
{
	/**
	 * The event's data, raw (the same as `rawData`).
	 *
	 * @var array<array-key,mixed>
	 */
	public array $data;

	/**
	 * @param array<string,mixed> $data
	 */
	protected function __construct(array $data)
	{
		parent::__construct($data);
		$this->data = $this->rawData;
	}
}
