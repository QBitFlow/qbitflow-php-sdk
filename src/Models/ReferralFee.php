<?php

declare(strict_types=1);

namespace QBitFlow\Models;

use DateTimeImmutable;
use QBitFlow\Support\Cast;

/**
 * The referrer's share of QBitFlow's fee on a payment or bill.
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
	 * Until when the referrer earns its share.
	 */
	public DateTimeImmutable $deadline;

	/**
	 * @param array<string,mixed> $data
	 */
	protected function __construct(array $data)
	{
		$this->address = Cast::string($data, 'address');
		$this->feePercent = Cast::string($data, 'feePercent');
		$this->deadline = Cast::date($data, 'deadline');
	}
}
