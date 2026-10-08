<?php

declare(strict_types=1);

namespace QBitFlow\Dto;

use DateTimeImmutable;
use QBitFlow\Support\Cast;
use QBitFlow\Support\Dto;

/**
 * A QBitFlow organization.
 */
final class Organization extends Dto
{
	public function __construct(
		/** Organization ID. */
		public readonly int $id,
		/** Organization name. */
		public readonly string $name,
		/** Default platform fee percentage. */
		public readonly float $feePercentage,
		/** When the organization was created. */
		public readonly DateTimeImmutable $createdAt,
	) {
	}

	/**
	 * @param array<string,mixed> $data
	 */
	public static function fromArray(array $data): self
	{
		return new self(
			Cast::int($data, 'id'),
			Cast::string($data, 'name'),
			Cast::float($data, 'feePercentage'),
			Cast::date($data, 'createdAt'),
		);
	}
}
