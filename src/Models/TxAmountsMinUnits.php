<?php

declare(strict_types=1);

namespace QBitFlow\Models;

use QBitFlow\Support\Cast;

/**
 * A payment's split in the token's min units (decimal strings).
 */
final readonly class TxAmountsMinUnits extends Model
{
	/**
	 * QBitFlow's fee (the referrer's share included).
	 */
	public string $platform;

	/**
	 * The organization's fee.
	 */
	public string $organization;

	/**
	 * The referrer's share.
	 */
	public string $referral;

	/**
	 * What the merchant received.
	 */
	public string $merchant;

	/**
	 * The network fee the payer paid on top; null when not recorded.
	 */
	public ?string $networkFee;

	/**
	 * @param array<string,mixed> $data
	 */
	protected function __construct(array $data)
	{
		$this->platform = Cast::string($data, 'platform');
		$this->organization = Cast::string($data, 'organization');
		$this->referral = Cast::string($data, 'referral');
		$this->merchant = Cast::string($data, 'merchant');
		$this->networkFee = Cast::nullableString($data, 'networkFee');
	}
}
