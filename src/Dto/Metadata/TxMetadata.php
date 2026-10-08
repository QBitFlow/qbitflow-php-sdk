<?php

declare(strict_types=1);

namespace QBitFlow\Dto\Metadata;

use QBitFlow\Support\Cast;
use QBitFlow\Support\Dto;

/**
 * On-chain metadata parsed from a settled transaction.
 */
final class TxMetadata extends Dto
{
	public function __construct(
		/** Network fees paid for the transaction. */
		public readonly NetworkFees $networkFees,
		/** Block the transaction was included in. */
		public readonly BlockData $blockData,
		/**
		 * Native-currency USD price at transaction time. Used for accounting on refunds,
		 * and when the merchant pays the network fees.
		 */
		public readonly ?float $mainCurrencyPriceUSD = null,
	) {
	}

	/**
	 * @param array<string,mixed> $data
	 */
	public static function fromArray(array $data): self
	{
		return new self(
			Cast::nested($data, 'networkFees', NetworkFees::fromArray(...))
				?? new NetworkFees('0', 0),
			Cast::nested($data, 'blockData', BlockData::fromArray(...))
				?? new BlockData('0', 0),
			Cast::nullableFloat($data, 'mainCurrencyPriceUSD'),
		);
	}
}
