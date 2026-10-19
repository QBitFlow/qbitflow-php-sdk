<?php

declare(strict_types=1);

namespace QBitFlow\Models;

use QBitFlow\Support\Cast;

/**
 * QBitFlow's record of how a payment or a bill settled, made once at confirmation: its fee
 * terms, how the contracts split what the payer paid, and the transaction that paid it. Every fee
 * record, export, held-funds total and refund reads it. Merchant only (API reads and webhooks).
 */
final readonly class Settlement extends Model
{
	/**
	 * QBitFlow's fee on the payment, a percent string (`"0.99"` = 0.99 %), deducted from the
	 * amount paid.
	 */
	public string $feePercent;

	/**
	 * The organization's fee on its member's payment (a marketplace's commission), taken from
	 * what remains after QBitFlow's fee; null (absent) when none.
	 */
	public ?OrganizationFee $organizationFee;

	/**
	 * The referrer's share of QBitFlow's fee; null (absent) when none.
	 */
	public ?ReferralFee $referralFee;

	/**
	 * How the contracts split what the payer paid; null (absent) on records made before the
	 * split was recorded. For a payment the organization held for its member, it is the split a
	 * direct payment would have had (the organization's fee withheld, not paid on-chain).
	 */
	public ?Split $split;

	/**
	 * The transaction that paid it; null (absent) before its confirmation.
	 */
	public ?SettlementTransaction $transaction;

	/**
	 * @param array<string,mixed> $data
	 */
	protected function __construct(array $data)
	{
		$this->feePercent = Cast::string($data, 'feePercent');
		$this->organizationFee = Cast::nullableObject($data, 'organizationFee', OrganizationFee::fromArray(...));
		$this->referralFee = Cast::nullableObject($data, 'referralFee', ReferralFee::fromArray(...));
		$this->split = Cast::nullableObject($data, 'split', Split::fromArray(...));
		$this->transaction = Cast::nullableObject($data, 'transaction', SettlementTransaction::fromArray(...));
	}
}
