<?php

declare(strict_types=1);

namespace QBitFlow\Models;

use QBitFlow\Support\Cast;

/**
 * The organization's fee on its member's payment or bill (a marketplace's commission).
 */
final readonly class OrganizationFee extends Model
{
	/**
	 * The organization's wallet that receives the fee.
	 */
	public string $address;

	/**
	 * The fee, a percent string, taken from what remains after QBitFlow's fee.
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
