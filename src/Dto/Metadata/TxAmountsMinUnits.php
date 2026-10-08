<?php

declare(strict_types=1);

namespace QBitFlow\Dto\Metadata;

use QBitFlow\Support\Cast;
use QBitFlow\Support\Dto;

/**
 * Per-party transaction amounts in the smallest currency units.
 *
 * Values are decimal strings so that no precision is lost to floating point.
 */
final class TxAmountsMinUnits extends Dto
{
	public function __construct(
		/** QBitFlow platform fee share. */
		public readonly string $platform,
		/** Organization fee share. */
		public readonly string $organization,
		/** Referral fee share. */
		public readonly string $referral,
		/** Amount received by the merchant. */
		public readonly string $merchant,
	) {
	}

	/**
	 * @param array<string,mixed> $data
	 */
	public static function fromArray(array $data): self
	{
		return new self(
			Cast::string($data, 'platform', '0'),
			Cast::string($data, 'organization', '0'),
			Cast::string($data, 'referral', '0'),
			Cast::string($data, 'merchant', '0'),
		);
	}
}
