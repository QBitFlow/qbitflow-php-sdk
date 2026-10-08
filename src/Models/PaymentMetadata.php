<?php

declare(strict_types=1);

namespace QBitFlow\Models;

use QBitFlow\Support\Cast;

/**
 * A payment's (or bill's) fees and split.
 */
final readonly class PaymentMetadata extends Model
{
	/**
	 * QBitFlow's fee on the payment, in percent (1.5 = 1.5 %).
	 */
	public float $feePercent;

	/**
	 * The organization's fee on its member's payment; null when none.
	 */
	public ?OrganizationFee $organizationFee;

	/**
	 * The referrer's share of QBitFlow's fee; null when none.
	 */
	public ?ReferralFee $referralFee;

	/**
	 * The transaction's network fees and block.
	 */
	public TxMetadata $txMetadata;

	/**
	 * How the payment was split (QBitFlow, referrer, organization, merchant).
	 */
	public TxAmounts $txAmounts;

	/**
	 * @param array<string,mixed> $data
	 */
	protected function __construct(array $data)
	{
		$this->feePercent = Cast::float($data, 'feePercent');
		$this->organizationFee = Cast::nullableObject($data, 'organizationFee', OrganizationFee::fromArray(...));
		$this->referralFee = Cast::nullableObject($data, 'referralFee', ReferralFee::fromArray(...));
		$this->txMetadata = Cast::object($data, 'txMetadata', TxMetadata::fromArray(...));
		$this->txAmounts = Cast::object($data, 'txAmounts', TxAmounts::fromArray(...));
	}
}
