<?php

declare(strict_types=1);

namespace QBitFlow\Models;

use DateTimeImmutable;
use QBitFlow\Support\Cast;

/**
 * A referrer's share of QBitFlow's fee.
 */
final readonly class ReferralFee extends Model
{
	/**
	 * The address receiving the fee.
	 */
	public string $referrer;

	/**
	 * The referrer's share of the platform fee, in percent (20 = 20 % of it).
	 */
	public float $feePercent;

	/**
	 * When the referral stops being paid.
	 */
	public DateTimeImmutable $deadline;

	/**
	 * @param array<string,mixed> $data
	 */
	protected function __construct(array $data)
	{
		$this->referrer = Cast::string($data, 'referrer');
		$this->feePercent = Cast::float($data, 'feePercent');
		$this->deadline = Cast::date($data, 'deadline');
	}
}
