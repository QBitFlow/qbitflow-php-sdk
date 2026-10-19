<?php

declare(strict_types=1);

namespace QBitFlow\Models;

use DateTimeImmutable;
use QBitFlow\Support\Cast;

/**
 * One row of the accounting export: a payment, a bill, a refund, or a fee.
 *
 * The properties are in the CSV's column order, laid out to be read left to right: what happened
 * and when, the USD amounts (gross, the fees, net), the same amounts in the currency paid, the
 * network fees, the rates, the on-chain proof, then the ids. Read CSV columns by header name, not
 * position. Amounts are in the currency's units (7.5 USDC, not 7500000), decimal strings, with the
 * currency's decimals beside them. A referralFee row only has `type`, `txTimeUtc`, `chain`,
 * `blockNumberOrSlot`, `currency`, `currencyDecimals`, `currencyPriceUsd`, `tokenContractOrMint`
 * and the referral fee: every other property is empty.
 */
final readonly class AccountingEvent extends Model
{
	/**
	 * When the transaction's block was mined (UTC): an event is in the export of that day.
	 */
	public DateTimeImmutable $txTimeUtc;

	/**
	 * `payment`, `subscriptionHistory`, `refund`, `organizationFee` or `referralFee` ({@see \QBitFlow\Enums\AccountingEventType}).
	 */
	public string $type;

	/**
	 * The payment's reference, if any.
	 */
	public ?string $paymentReference;

	/**
	 * For a refund of a payment, the payment's reference (a bill has none).
	 */
	public ?string $relatedPaymentReference;

	/**
	 * The product's name.
	 */
	public ?string $productName;

	/**
	 * The merchant's reference of the customer.
	 */
	public ?string $customerReference;

	/**
	 * The gross amount in USD: what was charged (a refund: what was sent back, negative).
	 */
	public ?float $grossAmountUsd;

	/**
	 * QBitFlow's fee in USD, the referrer's share included.
	 */
	public ?float $platformFeeUsd;

	/**
	 * The referral fee in USD.
	 */
	public float $referralFeeUsd;

	/**
	 * The organization's fee in USD.
	 */
	public ?float $organizationFeeUsd;

	/**
	 * What the merchant received (a payment or a bill: after the fees) or sent back (a refund: the
	 * refunded amount, negative), in USD. Network fees are never part of it.
	 */
	public ?float $netAmountUsd;

	/**
	 * The chain.
	 */
	public string $chain;

	/**
	 * What was paid, as markets name it: the token (`USDC`) or the chain's native currency (`ETH`,
	 * `SOL`; Base's is `ETH`). Always set.
	 */
	public string $currency;

	/**
	 * The currency's decimals: an amount × 10^currencyDecimals is its min units on-chain.
	 */
	public int $currencyDecimals;

	/**
	 * The currency's USD price the amounts were converted at (`grossAmountUsd / grossAmount`; a
	 * refund: its refunded transaction's). Null without a gross amount.
	 */
	public ?float $currencyPriceUsd;

	/**
	 * The gross amount in the currency's units (a decimal string).
	 */
	public ?string $grossAmount;

	/**
	 * QBitFlow's fee in the currency's units, the referrer's share included (a decimal string).
	 */
	public ?string $platformFee;

	/**
	 * The referrer's share, paid out of the platform fee, in the currency's units (a decimal
	 * string): what a referralFee event earned. `"0"` when no referral applied.
	 */
	public string $referralFee;

	/**
	 * The organization's fee in the currency's units (a decimal string; `"0"` when none).
	 */
	public ?string $organizationFee;

	/**
	 * The net amount in the currency's units (a decimal string; a refund: the refunded amount,
	 * negative). `grossAmount = platformFee + organizationFee + netAmount` exactly.
	 */
	public ?string $netAmount;

	/**
	 * Whose wallet paid the transaction's network fees, never part of the net: `payer` (a payment
	 * or a bill: on top of the amount), `merchant` (a refund the space sent: book it apart) or
	 * `organization` (a refund of a payment the organization held for its member), see
	 * {@see \QBitFlow\Enums\NetworkFeesPayer}; null on referralFee rows. An unknown value is kept.
	 */
	public ?string $networkFeesPaidBy;

	/**
	 * The network fees in `networkFeesCurrency`'s units (a decimal string); null when unknown (a
	 * refund recorded before they were).
	 */
	public ?string $networkFees;

	/**
	 * What paid them: the event's currency on a payment or a bill (the payer's gas refund, taken
	 * in it), the chain's native currency (`ETH`, `SOL`) on a refund.
	 */
	public ?string $networkFeesCurrency;

	/**
	 * `networkFeesCurrency`'s decimals.
	 */
	public ?int $networkFeesCurrencyDecimals;

	/**
	 * `networkFeesCurrency`'s USD price `networkFeesUsd` was converted at; null when unknown (a
	 * refund recorded before it was).
	 */
	public ?float $networkFeesCurrencyPriceUsd;

	/**
	 * The network fees in USD; null when unknown.
	 */
	public ?float $networkFeesUsd;

	/**
	 * QBitFlow's fee rate on the event, in percent (0.99 = 0.99 %).
	 */
	public ?float $platformFeePercent;

	/**
	 * The referrer's share of the platform fee, in percent (20 = 20 % of it).
	 */
	public float $referralFeePercent;

	/**
	 * The organization's fee rate on the event, in percent.
	 */
	public ?float $organizationFeePercent;

	/**
	 * The sender; null on referralFee rows.
	 */
	public ?string $fromAddress;

	/**
	 * The receiver; null on referralFee rows.
	 */
	public ?string $toAddress;

	/**
	 * The transaction's hash; null on referralFee rows.
	 */
	public ?string $txHash;

	/**
	 * The block (or slot).
	 */
	public string $blockNumberOrSlot;

	/**
	 * The token's contract (EVM) or mint (Solana); `''` for a native currency.
	 */
	public string $tokenContractOrMint;

	/**
	 * The transaction on the chain's explorer; null on referralFee rows.
	 */
	public ?string $explorerUrl;

	/**
	 * The transaction's page for its customer; null on referralFee rows.
	 */
	public ?string $receiptUrl;

	/**
	 * The event's transaction, prefixed by its type: `pay@` (a payment, an organization fee's),
	 * `sub-hist@` (a bill), `refund@` (a refund); null on referralFee rows.
	 */
	public ?string $paymentUuid;

	/**
	 * For a refund, the refunded payment's (`pay@…`) or bill's (`sub-hist@…`) id.
	 */
	public ?string $relatedPaymentUuid;

	/**
	 * The product; null when none.
	 */
	public ?string $productUuid;

	/**
	 * The product's reference.
	 */
	public ?string $productReference;

	/**
	 * The product's description.
	 */
	public ?string $productDescription;

	/**
	 * The customer.
	 */
	public ?string $customerUuid;

	/**
	 * The member whose space the event is in; null for the organization's own.
	 */
	public ?string $userUuid;

	/**
	 * @param array<string,mixed> $data
	 */
	protected function __construct(array $data)
	{
		$this->txTimeUtc = Cast::date($data, 'txTimeUtc');
		$this->type = Cast::string($data, 'type');
		$this->paymentReference = Cast::nullableString($data, 'paymentReference');
		$this->relatedPaymentReference = Cast::nullableString($data, 'relatedPaymentReference');
		$this->productName = Cast::nullableString($data, 'productName');
		$this->customerReference = Cast::nullableString($data, 'customerReference');
		$this->grossAmountUsd = Cast::nullableFloat($data, 'grossAmountUsd');
		$this->platformFeeUsd = Cast::nullableFloat($data, 'platformFeeUsd');
		$this->referralFeeUsd = Cast::float($data, 'referralFeeUsd');
		$this->organizationFeeUsd = Cast::nullableFloat($data, 'organizationFeeUsd');
		$this->netAmountUsd = Cast::nullableFloat($data, 'netAmountUsd');
		$this->chain = Cast::string($data, 'chain');
		$this->currency = Cast::string($data, 'currency');
		$this->currencyDecimals = Cast::uint($data, 'currencyDecimals', 255);
		$this->currencyPriceUsd = Cast::nullableFloat($data, 'currencyPriceUsd');
		$this->grossAmount = Cast::nullableString($data, 'grossAmount');
		$this->platformFee = Cast::nullableString($data, 'platformFee');
		$this->referralFee = Cast::string($data, 'referralFee');
		$this->organizationFee = Cast::nullableString($data, 'organizationFee');
		$this->netAmount = Cast::nullableString($data, 'netAmount');
		$this->networkFeesPaidBy = Cast::nullableString($data, 'networkFeesPaidBy');
		$this->networkFees = Cast::nullableString($data, 'networkFees');
		$this->networkFeesCurrency = Cast::nullableString($data, 'networkFeesCurrency');
		$this->networkFeesCurrencyDecimals = Cast::nullableUint($data, 'networkFeesCurrencyDecimals', 255);
		$this->networkFeesCurrencyPriceUsd = Cast::nullableFloat($data, 'networkFeesCurrencyPriceUsd');
		$this->networkFeesUsd = Cast::nullableFloat($data, 'networkFeesUsd');
		$this->platformFeePercent = Cast::nullableFloat($data, 'platformFeePercent');
		$this->referralFeePercent = Cast::float($data, 'referralFeePercent');
		$this->organizationFeePercent = Cast::nullableFloat($data, 'organizationFeePercent');
		$this->fromAddress = Cast::nullableString($data, 'fromAddress');
		$this->toAddress = Cast::nullableString($data, 'toAddress');
		$this->txHash = Cast::nullableString($data, 'txHash');
		$this->blockNumberOrSlot = Cast::string($data, 'blockNumberOrSlot');
		$this->tokenContractOrMint = Cast::string($data, 'tokenContractOrMint');
		$this->explorerUrl = Cast::nullableString($data, 'explorerUrl');
		$this->receiptUrl = Cast::nullableString($data, 'receiptUrl');
		$this->paymentUuid = Cast::nullableString($data, 'paymentUuid');
		$this->relatedPaymentUuid = Cast::nullableString($data, 'relatedPaymentUuid');
		$this->productUuid = Cast::nullableString($data, 'productUuid');
		$this->productReference = Cast::nullableString($data, 'productReference');
		$this->productDescription = Cast::nullableString($data, 'productDescription');
		$this->customerUuid = Cast::nullableString($data, 'customerUuid');
		$this->userUuid = Cast::nullableString($data, 'userUuid');
	}
}
