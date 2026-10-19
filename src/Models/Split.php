<?php

declare(strict_types=1);

namespace QBitFlow\Models;

use QBitFlow\Support\Cast;

/**
 * How a payment or a bill was split, as the contracts paid it. Each part is an amount of the
 * payment's currency (USD values in proportion to the payment's, to 6 decimals):
 * `platform + organization + referral + merchant` = the payment's amount, exactly, in each unit.
 */
final readonly class Split extends Model
{
	/**
	 * QBitFlow's fee (the referrer's share not included).
	 */
	public TokenAmount $platform;

	/**
	 * The organization's fee; null (absent) without one.
	 */
	public ?TokenAmount $organization;

	/**
	 * The referrer's share of QBitFlow's fee; null (absent) without one.
	 */
	public ?TokenAmount $referral;

	/**
	 * What the merchant (the member, for a member's payment) received, net of every fee.
	 */
	public TokenAmount $merchant;

	/**
	 * The network fee the payer paid on top of the amount, in the payment's currency; null
	 * (absent) on records made before it was recorded (`"0"` once recorded as none).
	 */
	public ?TokenAmount $networkFee;

	/**
	 * @param array<string,mixed> $data
	 */
	protected function __construct(array $data)
	{
		$this->platform = Cast::object($data, 'platform', TokenAmount::fromArray(...));
		$this->organization = Cast::nullableObject($data, 'organization', TokenAmount::fromArray(...));
		$this->referral = Cast::nullableObject($data, 'referral', TokenAmount::fromArray(...));
		$this->merchant = Cast::object($data, 'merchant', TokenAmount::fromArray(...));
		$this->networkFee = Cast::nullableObject($data, 'networkFee', TokenAmount::fromArray(...));
	}
}
