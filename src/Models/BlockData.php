<?php

declare(strict_types=1);

namespace QBitFlow\Models;

use QBitFlow\Support\Cast;

/**
 * The block that included a transaction.
 */
final readonly class BlockData extends Model
{
	/**
	 * The block number (or slot).
	 */
	public string $number;

	/**
	 * The block's time, in unix seconds.
	 */
	public int $timestamp;

	/**
	 * @param array<string,mixed> $data
	 */
	protected function __construct(array $data)
	{
		$this->number = Cast::string($data, 'number');
		$this->timestamp = Cast::uint($data, 'timestamp');
	}
}
