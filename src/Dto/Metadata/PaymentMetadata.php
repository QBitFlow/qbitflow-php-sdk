<?php

declare(strict_types=1);

namespace QBitFlow\Dto\Metadata;

use QBitFlow\Support\Cast;
use QBitFlow\Support\Dto;

/**
 * Structured metadata attached to a payment or subscription-billing record: the fee
 * breakdown, the on-chain transaction metadata, and the computed per-party amounts.
 *
 * Returned only on authenticated reads.
 */
final class PaymentMetadata extends Dto
{
	public function __construct(
		/**
		 * QBitFlow platform fee in basis points (100 bps = 1%), deducted from the amount
		 * paid. The merchant receives amount - platform fee - organization fee.
		 */
		public readonly int $feeBps,
		/** On-chain metadata, populated once the transaction is confirmed. */
		public readonly TxMetadata $txMetadata,
		/** Computed fee and merchant amounts. */
		public readonly TxAmountsFull $txAmounts,
		/** Additional fee kept by the organization, when one applies. */
		public readonly ?OrganizationFee $organizationFee = null,
		/** Fee paid to a referrer, when one applies. */
		public readonly ?ReferralFee $referralFee = null,
	) {
	}

	/**
	 * @param array<string,mixed> $data
	 */
	public static function fromArray(array $data): self
	{
		return new self(
			Cast::int($data, 'feeBps'),
			Cast::nested($data, 'txMetadata', TxMetadata::fromArray(...))
				?? TxMetadata::fromArray([]),
			Cast::nested($data, 'txAmounts', TxAmountsFull::fromArray(...))
				?? TxAmountsFull::fromArray([]),
			Cast::nested($data, 'organizationFee', OrganizationFee::fromArray(...)),
			Cast::nested($data, 'referralFee', ReferralFee::fromArray(...)),
		);
	}

	/** Platform fee expressed as a percentage, e.g. 150 bps becomes 1.5. */
	public function feePercent(): float
	{
		return $this->feeBps / 100;
	}
}
