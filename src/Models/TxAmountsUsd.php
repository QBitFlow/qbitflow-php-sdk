<?php

declare(strict_types=1);

namespace QBitFlow\Models;

use QBitFlow\Support\Cast;

/**
 * A payment's split in USD.
 */
final readonly class TxAmountsUsd extends Model
{
	/**
	 * QBitFlow's fee.
	 */
	public float $platform;

	/**
	 * The organization's fee; null when none.
	 */
	public ?float $organization;

	/**
	 * The referrer's share; null when none.
	 */
	public ?float $referral;

	/**
	 * What the merchant received.
	 */
	public float $merchant;

	/**
	 * The network fee the payer paid on top; null when not recorded.
	 */
	public ?float $networkFee;

	/**
	 * @param array<string,mixed> $data
	 */
	protected function __construct(array $data)
	{
		$this->platform = Cast::float($data, 'platform');
		$this->organization = Cast::nullableFloat($data, 'organization');
		$this->referral = Cast::nullableFloat($data, 'referral');
		$this->merchant = Cast::float($data, 'merchant');
		$this->networkFee = Cast::nullableFloat($data, 'networkFee');
	}
}
