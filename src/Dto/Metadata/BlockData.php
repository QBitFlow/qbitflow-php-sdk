<?php

declare(strict_types=1);

namespace QBitFlow\Dto\Metadata;

use DateTimeImmutable;
use QBitFlow\Support\Cast;
use QBitFlow\Support\Dto;

/**
 * Identifies the block a transaction was included in.
 */
final class BlockData extends Dto
{
	public function __construct(
		/** Block number, or slot on Solana. A string, since it can exceed PHP's int range. */
		public readonly string $number,
		/** Block timestamp, in Unix seconds. */
		public readonly int $timestamp,
	) {
	}

	/**
	 * @param array<string,mixed> $data
	 */
	public static function fromArray(array $data): self
	{
		return new self(
			Cast::string($data, 'number', '0'),
			Cast::int($data, 'timestamp'),
		);
	}

	/** The block timestamp as a date object. */
	public function timestampAsDate(): DateTimeImmutable
	{
		return new DateTimeImmutable('@' . $this->timestamp);
	}
}
