<?php

declare(strict_types=1);

namespace QBitFlow\Dto\Metadata;

use QBitFlow\Support\Cast;
use QBitFlow\Support\Dto;

/**
 * On-chain network fees, in the smallest units of the native currency.
 */
final class NetworkFees extends Dto
{
	public function __construct(
		/** Fee amount as a decimal string, in native min units, to preserve precision. */
		public readonly string $amount,
		/** Native units consumed, e.g. gas used. */
		public readonly int $unitsConsumed,
	) {
	}

	/**
	 * @param array<string,mixed> $data
	 */
	public static function fromArray(array $data): self
	{
		return new self(
			Cast::string($data, 'amount', '0'),
			Cast::int($data, 'unitsConsumed'),
		);
	}
}
