<?php

declare(strict_types=1);

namespace QBitFlow\Dto;

use DateTimeImmutable;
use QBitFlow\Enums\AccountingEventType;
use QBitFlow\Support\Cast;
use QBitFlow\Support\Dto;

/**
 * One row of an accounting export: a one-time payment, a subscription billing, a refund
 * or a fee entry.
 *
 * Token-denominated amounts are decimal strings so that no precision is lost; the
 * matching `…Usd` fields are floats.
 */
final class AccountingEvent extends Dto
{
	public function __construct(
		/** Unique payment identifier. */
		public readonly string $paymentId,
		/** Your own reference for the payment, when one was set. */
		public readonly string $paymentReference,
		/** What kind of row this is. */
		public readonly AccountingEventType $type,
		/** Transaction timestamp, in UTC. */
		public readonly DateTimeImmutable $txTimeUtc,
		/** URL of the payment receipt. */
		public readonly string $receiptUrl,
		/** For refunds, the payment ID of the original transaction. */
		public readonly string $relatedPaymentId,
		/** For refunds, your own reference for the original payment. */
		public readonly string $relatedPaymentReference,
		/** Product ID. */
		public readonly int $productId,
		/** Your own product reference, when one was set. */
		public readonly string $productReference,
		/** Product name at time of payment. */
		public readonly string $productName,
		/** Product description at time of payment. */
		public readonly string $productDescription,
		/** Customer UUID. */
		public readonly string $customerUUID,
		/** Your own customer reference, when one was set. */
		public readonly string $customerReference,
		/** Blockchain name, e.g. `bitcoin`, `solana`, `ethereum`. */
		public readonly string $chain,
		/** Block number or slot the transaction was confirmed in. */
		public readonly string $blockNumberOrSlot,
		/** On-chain transaction hash. */
		public readonly string $txHash,
		/** Sender address. */
		public readonly string $fromAddress,
		/** Receiver address. */
		public readonly string $toAddress,
		/** Token symbol, e.g. `USDC` or `BTC`. */
		public readonly string $tokenSymbol,
		/** Decimal places of the token. */
		public readonly int $currencyDecimals,
		/** Token contract address (EVM) or mint address (Solana). */
		public readonly string $tokenContractOrMint,
		/** Link to the transaction on a block explorer. */
		public readonly string $explorerUrl,
		/** Gross amount received, in token units. */
		public readonly string $grossAmount,
		/** Gross amount received, in USD. */
		public readonly float $grossAmountUsd,
		/** Platform fee percentage applied. */
		public readonly float $platformFeePercent,
		/** Platform fee, in USD. */
		public readonly float $platformFeeUsd,
		/** Platform fee, in token units. */
		public readonly string $platformFee,
		/** Organization fee percentage applied. */
		public readonly float $organizationFeePercent,
		/** Organization fee, in USD. */
		public readonly float $organizationFeeUsd,
		/** Organization fee, in token units. */
		public readonly string $organizationFee,
		/** Net amount received, in USD, after all fees. */
		public readonly float $netAmountUsd,
		/** Net amount received, in token units. */
		public readonly string $netAmount,
		/** Network fees in USD, present when QBitFlow paid the network fee. */
		public readonly ?float $networkFeesUsd = null,
		/** Network fees in token units. */
		public readonly ?string $networkFees = null,
	) {
	}

	/**
	 * @param array<string,mixed> $data
	 */
	public static function fromArray(array $data): self
	{
		return new self(
			Cast::string($data, 'paymentId'),
			Cast::string($data, 'paymentReference'),
			Cast::enum($data, 'type', AccountingEventType::class, AccountingEventType::PAYMENT),
			Cast::date($data, 'txTimeUtc'),
			Cast::string($data, 'receiptUrl'),
			Cast::string($data, 'relatedPaymentId'),
			Cast::string($data, 'relatedPaymentReference'),
			Cast::int($data, 'productId'),
			Cast::string($data, 'productReference'),
			Cast::string($data, 'productName'),
			Cast::string($data, 'productDescription'),
			Cast::string($data, 'customerUUID'),
			Cast::string($data, 'customerReference'),
			Cast::string($data, 'chain'),
			Cast::string($data, 'blockNumberOrSlot'),
			Cast::string($data, 'txHash'),
			Cast::string($data, 'fromAddress'),
			Cast::string($data, 'toAddress'),
			Cast::string($data, 'tokenSymbol'),
			Cast::int($data, 'currencyDecimals'),
			Cast::string($data, 'tokenContractOrMint'),
			Cast::string($data, 'explorerUrl'),
			Cast::string($data, 'grossAmount', '0'),
			Cast::float($data, 'grossAmountUsd'),
			Cast::float($data, 'platformFeePercent'),
			Cast::float($data, 'platformFeeUsd'),
			Cast::string($data, 'platformFee', '0'),
			Cast::float($data, 'organizationFeePercent'),
			Cast::float($data, 'organizationFeeUsd'),
			Cast::string($data, 'organizationFee', '0'),
			Cast::float($data, 'netAmountUsd'),
			Cast::string($data, 'netAmount', '0'),
			Cast::nullableFloat($data, 'networkFeesUsd'),
			Cast::nullableString($data, 'networkFees'),
		);
	}
}
