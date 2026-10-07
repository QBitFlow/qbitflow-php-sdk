<?php

declare(strict_types=1);

namespace QBitFlow\Dto\Metadata;

use QBitFlow\Support\Cast;
use QBitFlow\Support\Dto;

/**
 * An additional fee kept by the organization, on top of the QBitFlow platform fee.
 */
final class OrganizationFee extends Dto
{
	public function __construct(
		/** Fee in basis points (100 bps = 1%). */
		public readonly int $feeBps,
		/** ID of the organization receiving the fee. */
		public readonly int $organizationId,
		/** On-chain address receiving the fee. */
		public readonly string $organization,
	) {
	}

	/**
	 * @param array<array-key,mixed> $data
	 */
	public static function fromArray(array $data): self
	{
		return new self(
			Cast::int($data, 'feeBps'),
			Cast::int($data, 'organizationId'),
			Cast::string($data, 'organization'),
		);
	}
}
