<?php

declare(strict_types=1);

namespace QBitFlow\Models;

use QBitFlow\Support\Cast;

/**
 * A transaction's network fees and block.
 */
final readonly class TxMetadata extends Model
{
	/**
	 * The fees the transaction's sender paid, in the native coin's min units.
	 */
	public NetworkFees $networkFees;

	/**
	 * The block that included the transaction.
	 */
	public BlockData $blockData;

	/**
	 * The native coin's USD price at the transaction's time, when recorded.
	 */
	public ?float $mainCurrencyPriceUsd;

	/**
	 * @param array<string,mixed> $data
	 */
	protected function __construct(array $data)
	{
		$this->networkFees = Cast::object($data, 'networkFees', NetworkFees::fromArray(...));
		$this->blockData = Cast::object($data, 'blockData', BlockData::fromArray(...));
		$this->mainCurrencyPriceUsd = Cast::nullableFloat($data, 'mainCurrencyPriceUsd');
	}
}
