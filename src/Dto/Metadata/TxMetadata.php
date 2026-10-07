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
		 * Native-currency USD price at transaction time; `0.0` when not recorded. Set for
		 * accounting on refunds, and when the merchant pays the network fees.
		 */
		public readonly float $mainCurrencyPriceUSD = 0.0,
	) {
	}

	/**
	 * @param array<array-key,mixed> $data
	 */
	public static function fromArray(array $data): self
	{
		return new self(
			Cast::object($data, 'networkFees', NetworkFees::fromArray(...)),
			Cast::object($data, 'blockData', BlockData::fromArray(...)),
			Cast::float($data, 'mainCurrencyPriceUSD'),
		);
	}
}
