<?php

declare(strict_types=1);

namespace QBitFlow\Dto\Metadata;

use QBitFlow\Support\Cast;
use QBitFlow\Support\Dto;

/**
 * Per-party transaction amounts, in USD.
 */
final class TxAmountsUSD extends Dto
{
	public function __construct(
		/** QBitFlow platform fee share. */
		public readonly float $platform,
		/** Amount received by the merchant. */
		public readonly float $merchant,
		/** Organization fee share; `0.0` when there is no organization fee. */
		public readonly float $organization = 0.0,
		/** Referral fee share; `0.0` when there is no referral fee. */
		public readonly float $referral = 0.0,
	) {
	}

	/**
	 * @param array<array-key,mixed> $data
	 */
	public static function fromArray(array $data): self
	{
		return new self(
			Cast::float($data, 'platform'),
			Cast::float($data, 'merchant'),
			Cast::float($data, 'organization'),
			Cast::float($data, 'referral'),
		);
	}
}
