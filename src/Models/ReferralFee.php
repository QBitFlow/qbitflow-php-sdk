<?php

declare(strict_types=1);

namespace QBitFlow\Models;

use QBitFlow\Support\Cast;

/**
 * The referrer's share of QBitFlow's fee on a payment or bill.
 *
 * How long the referral lasts is internal to QBitFlow: a `deadline` key, if one is sent, is ignored.
 */
final readonly class ReferralFee extends Model
{
	/**
	 * The referrer's wallet that receives the fee.
	 */
	public string $address;

	/**
	 * The referrer's share of the platform fee, a percent string (`"20"` = 20 % of it).
	 */
	public string $feePercent;

	/**
	 * @param array<string,mixed> $data
	 */
	protected function __construct(array $data)
	{
		$this->address = Cast::string($data, 'address');
		$this->feePercent = Cast::string($data, 'feePercent');
	}
}
