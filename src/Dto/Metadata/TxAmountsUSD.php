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
		/** Organization fee share, absent when there is no organization fee. */
		public readonly ?float $organization = null,
		/** Referral fee share, absent when there is no referral fee. */
		public readonly ?float $referral = null,
	) {
	}

	/**
	 * @param array<string,mixed> $data
	 */
	public static function fromArray(array $data): self
	{
		return new self(
			Cast::float($data, 'platform'),
			Cast::float($data, 'merchant'),
			Cast::nullableFloat($data, 'organization'),
			Cast::nullableFloat($data, 'referral'),
		);
	}
}
