<?php

declare(strict_types=1);

namespace QBitFlow\Models;

use QBitFlow\Support\Cast;

/**
 * The organization's fee on a member's payment (a marketplace commission).
 */
final readonly class OrganizationFee extends Model
{
	/**
	 * The address receiving the fee.
	 */
	public string $organization;

	/**
	 * The fee, in percent, taken from what remains after QBitFlow's fee.
	 */
	public float $feePercent;

	/**
	 * @param array<string,mixed> $data
	 */
	protected function __construct(array $data)
	{
		$this->organization = Cast::string($data, 'organization');
		$this->feePercent = Cast::float($data, 'feePercent');
	}
}
