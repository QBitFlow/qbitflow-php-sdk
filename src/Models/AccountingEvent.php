<?php

declare(strict_types=1);

namespace QBitFlow\Models;

use DateTimeImmutable;
use QBitFlow\Support\Cast;

/**
 * One row of the accounting export: a payment, a bill, a refund, or a fee.
 */
final readonly class AccountingEvent extends Model
{
	/**
	 * The event's transaction (prefixed); null on referralFee rows.
	 */
	public ?string $paymentUuid;

	/**
	 * The payment's reference, if any.
	 */
	public ?string $paymentReference;

	/**
	 * `payment`, `subscriptionHistory`, `refund`, `organizationFee` or `referralFee` ({@see \QBitFlow\Enums\AccountingEventType}).
	 */
	public string $type;

	/**
	 * The transaction's time (the API may send a local offset).
	 */
	public DateTimeImmutable $txTimeUtc;

	/**
	 * The transaction's page for its customer.
	 */
	public ?string $receiptUrl;

	/**
	 * For a refund, the refunded payment.
	 */
	public ?string $relatedPaymentUuid;

	/**
	 * For a refund, the refunded payment's reference.
	 */
	public ?string $relatedPaymentReference;

	/**
	 * The member whose space the event is in; null for the organization's own.
	 */
	public ?string $userUuid;

	/**
	 * The product; null when none.
	 */
	public ?string $productUuid;

	/**
	 * The product's reference.
	 */
	public ?string $productReference;

	/**
	 * The product's name.
	 */
	public ?string $productName;

	/**
	 * The product's description.
	 */
	public ?string $productDescription;

	/**
	 * The customer.
	 */
	public ?string $customerUuid;

	/**
	 * The customer's reference.
	 */
	public ?string $customerReference;

	/**
	 * The chain.
	 */
	public string $chain;

	/**
	 * The block (or slot).
	 */
	public string $blockNumberOrSlot;

	/**
	 * The transaction's hash; null on referralFee rows.
	 */
	public ?string $txHash;

	/**
	 * The sender; null on referralFee rows.
	 */
	public ?string $fromAddress;

	/**
	 * The receiver; null on referralFee rows.
	 */
	public ?string $toAddress;

	/**
	 * The token's symbol.
	 */
	public string $tokenSymbol;

	/**
	 * The token's decimals.
	 */
	public int $currencyDecimals;

	/**
	 * The token's contract (or mint).
	 */
	public string $tokenContractOrMint;

	/**
	 * The transaction on the chain's explorer; null on referralFee rows.
	 */
	public ?string $explorerUrl;

	/**
	 * The gross amount in min units (a decimal string).
	 */
	public ?string $grossAmount;

	/**
	 * The gross amount in USD.
	 */
	public ?float $grossAmountUsd;

	/**
	 * QBitFlow's fee rate, in percent (1.5 = 1.5 %).
	 */
	public ?float $platformFeePercent;

	/**
	 * QBitFlow's fee in USD.
	 */
	public ?float $platformFeeUsd;

	/**
	 * QBitFlow's fee in min units.
	 */
	public ?string $platformFee;

	/**
	 * The organization's fee rate, in percent.
	 */
	public ?float $organizationFeePercent;

	/**
	 * The organization's fee in USD.
	 */
	public ?float $organizationFeeUsd;

	/**
	 * The organization's fee in min units.
	 */
	public ?string $organizationFee;

	/**
	 * The referrer's share of the platform fee, in percent.
	 */
	public float $referralFeePercent;

	/**
	 * The referral fee in USD.
	 */
	public float $referralFeeUsd;

	/**
	 * The referral fee in min units.
	 */
	public string $referralFee;

	/**
	 * The network fees in USD.
	 */
	public ?float $networkFeesUsd;

	/**
	 * The network fees in min units.
	 */
	public ?string $networkFees;

	/**
	 * The net amount in USD.
	 */
	public ?float $netAmountUsd;

	/**
	 * The net amount in min units.
	 */
	public ?string $netAmount;

	/**
	 * The member's first name.
	 */
	public ?string $userName;

	/**
	 * The member's last name.
	 */
	public ?string $userLastName;

	/**
	 * The customer's first name.
	 */
	public ?string $customerName;

	/**
	 * The customer's last name.
	 */
	public ?string $customerLastName;

	/**
	 * @param array<string,mixed> $data
	 */
	protected function __construct(array $data)
	{
		$this->paymentUuid = Cast::nullableString($data, 'paymentUuid');
		$this->paymentReference = Cast::nullableString($data, 'paymentReference');
		$this->type = Cast::string($data, 'type');
		$this->txTimeUtc = Cast::date($data, 'txTimeUtc');
		$this->receiptUrl = Cast::nullableString($data, 'receiptUrl');
		$this->relatedPaymentUuid = Cast::nullableString($data, 'relatedPaymentUuid');
		$this->relatedPaymentReference = Cast::nullableString($data, 'relatedPaymentReference');
		$this->userUuid = Cast::nullableString($data, 'userUuid');
		$this->productUuid = Cast::nullableString($data, 'productUuid');
		$this->productReference = Cast::nullableString($data, 'productReference');
		$this->productName = Cast::nullableString($data, 'productName');
		$this->productDescription = Cast::nullableString($data, 'productDescription');
		$this->customerUuid = Cast::nullableString($data, 'customerUuid');
		$this->customerReference = Cast::nullableString($data, 'customerReference');
		$this->chain = Cast::string($data, 'chain');
		$this->blockNumberOrSlot = Cast::string($data, 'blockNumberOrSlot');
		$this->txHash = Cast::nullableString($data, 'txHash');
		$this->fromAddress = Cast::nullableString($data, 'fromAddress');
		$this->toAddress = Cast::nullableString($data, 'toAddress');
		$this->tokenSymbol = Cast::string($data, 'tokenSymbol');
		$this->currencyDecimals = Cast::uint($data, 'currencyDecimals', 255);
		$this->tokenContractOrMint = Cast::string($data, 'tokenContractOrMint');
		$this->explorerUrl = Cast::nullableString($data, 'explorerUrl');
		$this->grossAmount = Cast::nullableString($data, 'grossAmount');
		$this->grossAmountUsd = Cast::nullableFloat($data, 'grossAmountUsd');
		$this->platformFeePercent = Cast::nullableFloat($data, 'platformFeePercent');
		$this->platformFeeUsd = Cast::nullableFloat($data, 'platformFeeUsd');
		$this->platformFee = Cast::nullableString($data, 'platformFee');
		$this->organizationFeePercent = Cast::nullableFloat($data, 'organizationFeePercent');
		$this->organizationFeeUsd = Cast::nullableFloat($data, 'organizationFeeUsd');
		$this->organizationFee = Cast::nullableString($data, 'organizationFee');
		$this->referralFeePercent = Cast::float($data, 'referralFeePercent');
		$this->referralFeeUsd = Cast::float($data, 'referralFeeUsd');
		$this->referralFee = Cast::string($data, 'referralFee');
		$this->networkFeesUsd = Cast::nullableFloat($data, 'networkFeesUsd');
		$this->networkFees = Cast::nullableString($data, 'networkFees');
		$this->netAmountUsd = Cast::nullableFloat($data, 'netAmountUsd');
		$this->netAmount = Cast::nullableString($data, 'netAmount');
		$this->userName = Cast::nullableString($data, 'userName');
		$this->userLastName = Cast::nullableString($data, 'userLastName');
		$this->customerName = Cast::nullableString($data, 'customerName');
		$this->customerLastName = Cast::nullableString($data, 'customerLastName');
	}
}
