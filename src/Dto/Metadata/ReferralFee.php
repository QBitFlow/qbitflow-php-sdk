<?php

declare(strict_types=1);

namespace QBitFlow\Dto\Metadata;

use DateTimeImmutable;
use QBitFlow\Support\Cast;
use QBitFlow\Support\Dto;

/**
 * An optional fee paid to a referrer.
 */
final class ReferralFee extends Dto
{
	public function __construct(
		/** ID of the referral. */
		public readonly int $referralId,
		/** On-chain address of the referrer receiving the fee. */
		public readonly string $referrer,
		/** Fee in basis points (100 bps = 1%). */
		public readonly int $feeBps,
		/** Point after which the referral fee no longer applies. */
		public readonly ?DateTimeImmutable $deadline = null,
	) {
	}

	/**
	 * @param array<string,mixed> $data
	 */
	public static function fromArray(array $data): self
	{
		return new self(
			Cast::int($data, 'referralId'),
			Cast::string($data, 'referrer'),
			Cast::int($data, 'feeBps'),
			Cast::nullableDate($data, 'deadline'),
		);
	}
}
