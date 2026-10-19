<?php

declare(strict_types=1);

namespace QBitFlow\Models;

use DateTimeImmutable;
use QBitFlow\Support\Cast;

/**
 * One row of the accounting export (`accounting->exportJson()`): a payment, a bill or a refund
 * of the space, or a fee it earned on another space's payment or bill.
 *
 * The export is flat on purpose, for spreadsheets: one column per key, the currency named by its
 * symbol, chain and decimals (no nested currency object). Every amount is a decimal string, in
 * the currency's units (7.5 USDC, not 7500000) with its on-chain twin `…MinUnits` beside it; a
 * value the row doesn't have (a fee that doesn't apply, an amount not known) is null (absent),
 * never `"0"`. The properties are in the CSV's column order, laid out to be read left to right:
 * what happened and when, the USD amounts (gross, the fees, net), the same amounts in the
 * currency paid, the network fees, the rates, the on-chain proof, then the ids. Read CSV columns
 * by header name, not position. Rows are ordered by `txTimeUtc`, then `type`, then `txUuid`.
 *
 * `grossAmount = platformFee + organizationFee + netAmount` exactly (an absent fee counts 0); a
 * refund's net is its gross, both negative. A `referralFee` row only tells what the referrer
 * earned, when and on which chain: `type`, `txTimeUtc`, `chain`, `blockNumberOrSlot`,
 * `currency`, `currencyDecimals`, `currencyPriceUsd`, `tokenContractOrMint`,
 * `referralFeePercent`, `referralFeeUsd`, `referralFee` and `referralFeeMinUnits`; every other
 * property is null.
 */
final readonly class AccountingEvent extends Model
{
	/**
	 * When the transaction's block was mined (UTC): an event is in the export of that day.
	 */
	public DateTimeImmutable $txTimeUtc;

	/**
	 * `payment`, `bill`, `refund`, `organizationFee` or `referralFee` ({@see \QBitFlow\Enums\AccountingEventType}).
	 */
	public string $type;

	/**
	 * The payment's own reference (yours), when it has one.
	 */
	public ?string $reference;

	/**
	 * For a refund of a payment, the payment's reference (a bill has none).
	 */
	public ?string $relatedReference;

	/**
	 * The product's name.
	 */
	public ?string $productName;

	/**
	 * Your reference of the customer.
	 */
	public ?string $customerReference;

	/**
	 * What the customer paid in USD (a refund: what went back, negative).
	 */
	public ?string $grossAmountUsd;

	/**
	 * QBitFlow's fee in USD, the referrer's share included; null when none (a refund).
	 */
	public ?string $platformFeeUsd;

	/**
	 * The referrer's share of the platform fee in USD; null when no referral applied.
	 */
	public ?string $referralFeeUsd;

	/**
	 * The organization's fee on a member's payment in USD; null when none.
	 */
	public ?string $organizationFeeUsd;

	/**
	 * What the merchant received (a payment or a bill: after the fees) or sent back (a refund: the
	 * refunded amount, negative), in USD. Network fees are never part of it.
	 */
	public ?string $netAmountUsd;

	/**
	 * The chain ({@see \QBitFlow\Enums\Chain}).
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
	 * The currency's USD price the amounts were converted at: `grossAmountUsd / grossAmount`, to 8
	 * decimals (a refund: its refunded transaction's). Null without a gross amount.
	 */
	public ?string $currencyPriceUsd;

	/**
	 * The gross amount in the currency's units.
	 */
	public ?string $grossAmount;

	/**
	 * The same on-chain: an integer string.
	 */
	public ?string $grossAmountMinUnits;

	/**
	 * QBitFlow's fee in the currency's units, the referrer's share included.
	 */
	public ?string $platformFee;

	/**
	 * The same on-chain.
	 */
	public ?string $platformFeeMinUnits;

	/**
	 * The referrer's share, paid out of the platform fee, in the currency's units: what a
	 * `referralFee` event earned. Null when no referral applied (none, or past its deadline).
	 */
	public ?string $referralFee;

	/**
	 * The same on-chain.
	 */
	public ?string $referralFeeMinUnits;

	/**
	 * The organization's fee in the currency's units; null when none.
	 */
	public ?string $organizationFee;

	/**
	 * The same on-chain.
	 */
	public ?string $organizationFeeMinUnits;

	/**
	 * The net amount in the currency's units (a refund: the refunded amount, negative).
	 */
	public ?string $netAmount;

	/**
	 * The same on-chain.
	 */
	public ?string $netAmountMinUnits;

	/**
	 * Whose wallet paid the transaction's network fees, never part of the net: `payer` (a payment
	 * or a bill: on top of the amount), `merchant` (a refund the space sent: book it apart) or
	 * `organization` (a refund of a payment the organization held for its member); null on
	 * `referralFee` rows ({@see \QBitFlow\Enums\NetworkFeesPayer}).
	 */
	public ?string $networkFeesPaidBy;

	/**
	 * The network fees in `networkFeesCurrency`'s units; null when not recorded.
	 */
	public ?string $networkFees;

	/**
	 * The same in `networkFeesCurrency`'s min units.
	 */
	public ?string $networkFeesMinUnits;

	/**
	 * What paid them: the event's currency on a payment or a bill (the payer's gas refund, taken in
	 * it), the chain's native currency (`ETH`, `SOL`) on a refund.
	 */
	public ?string $networkFeesCurrency;

	/**
	 * `networkFeesCurrency`'s decimals; null when absent.
	 */
	public ?int $networkFeesCurrencyDecimals;

	/**
	 * `networkFeesCurrency`'s USD price `networkFeesUsd` was converted at; null when unknown.
	 */
	public ?string $networkFeesCurrencyPriceUsd;

	/**
	 * The network fees in USD; null when unknown.
	 */
	public ?string $networkFeesUsd;

	/**
	 * QBitFlow's fee rate on the event, a percent string (`"0.99"` = 0.99 %).
	 */
	public ?string $platformFeePercent;

	/**
	 * The referrer's share of the platform fee (`"20"` = 20 % of it), when a referral applied.
	 */
	public ?string $referralFeePercent;

	/**
	 * The organization's fee rate on the event, when it took one.
	 */
	public ?string $organizationFeePercent;

	/**
	 * The sender; null on `referralFee` rows.
	 */
	public ?string $fromAddress;

	/**
	 * The receiver; null on `referralFee` rows.
	 */
	public ?string $toAddress;

	/**
	 * The transaction's hash; null on `referralFee` rows.
	 */
	public ?string $txHash;

	/**
	 * The block (or slot).
	 */
	public ?string $blockNumberOrSlot;

	/**
	 * The token's contract (EVM) or mint (Solana); null for a native currency.
	 */
	public ?string $tokenContractOrMint;

	/**
	 * The transaction on the chain's explorer; null on `referralFee` rows.
	 */
	public ?string $explorerUrl;

	/**
	 * The transaction's public page for its customer; null on `referralFee` rows.
	 */
	public ?string $receiptUrl;

	/**
	 * The event's transaction, prefixed by its type: `pay@` (a payment), `sub-hist@` (a bill),
	 * `refund@` (a refund); null on `referralFee` rows.
	 */
	public ?string $txUuid;

	/**
	 * For a refund, the refunded payment's (`pay@…`) or bill's (`sub-hist@…`) id.
	 */
	public ?string $relatedTxUuid;

	/**
	 * The product; null when the event has none.
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
		$this->reference = Cast::nullableString($data, 'reference');
		$this->relatedReference = Cast::nullableString($data, 'relatedReference');
		$this->productName = Cast::nullableString($data, 'productName');
		$this->customerReference = Cast::nullableString($data, 'customerReference');
		$this->grossAmountUsd = Cast::nullableString($data, 'grossAmountUsd');
		$this->platformFeeUsd = Cast::nullableString($data, 'platformFeeUsd');
		$this->referralFeeUsd = Cast::nullableString($data, 'referralFeeUsd');
		$this->organizationFeeUsd = Cast::nullableString($data, 'organizationFeeUsd');
		$this->netAmountUsd = Cast::nullableString($data, 'netAmountUsd');
		$this->chain = Cast::string($data, 'chain');
		$this->currency = Cast::string($data, 'currency');
		$this->currencyDecimals = Cast::uint($data, 'currencyDecimals', 255);
		$this->currencyPriceUsd = Cast::nullableString($data, 'currencyPriceUsd');
		$this->grossAmount = Cast::nullableString($data, 'grossAmount');
		$this->grossAmountMinUnits = Cast::nullableString($data, 'grossAmountMinUnits');
		$this->platformFee = Cast::nullableString($data, 'platformFee');
		$this->platformFeeMinUnits = Cast::nullableString($data, 'platformFeeMinUnits');
		$this->referralFee = Cast::nullableString($data, 'referralFee');
		$this->referralFeeMinUnits = Cast::nullableString($data, 'referralFeeMinUnits');
		$this->organizationFee = Cast::nullableString($data, 'organizationFee');
		$this->organizationFeeMinUnits = Cast::nullableString($data, 'organizationFeeMinUnits');
		$this->netAmount = Cast::nullableString($data, 'netAmount');
		$this->netAmountMinUnits = Cast::nullableString($data, 'netAmountMinUnits');
		$this->networkFeesPaidBy = Cast::nullableString($data, 'networkFeesPaidBy');
		$this->networkFees = Cast::nullableString($data, 'networkFees');
		$this->networkFeesMinUnits = Cast::nullableString($data, 'networkFeesMinUnits');
		$this->networkFeesCurrency = Cast::nullableString($data, 'networkFeesCurrency');
		$this->networkFeesCurrencyDecimals = Cast::nullableUint($data, 'networkFeesCurrencyDecimals', 255);
		$this->networkFeesCurrencyPriceUsd = Cast::nullableString($data, 'networkFeesCurrencyPriceUsd');
		$this->networkFeesUsd = Cast::nullableString($data, 'networkFeesUsd');
		$this->platformFeePercent = Cast::nullableString($data, 'platformFeePercent');
		$this->referralFeePercent = Cast::nullableString($data, 'referralFeePercent');
		$this->organizationFeePercent = Cast::nullableString($data, 'organizationFeePercent');
		$this->fromAddress = Cast::nullableString($data, 'fromAddress');
		$this->toAddress = Cast::nullableString($data, 'toAddress');
		$this->txHash = Cast::nullableString($data, 'txHash');
		$this->blockNumberOrSlot = Cast::nullableString($data, 'blockNumberOrSlot');
		$this->tokenContractOrMint = Cast::nullableString($data, 'tokenContractOrMint');
		$this->explorerUrl = Cast::nullableString($data, 'explorerUrl');
		$this->receiptUrl = Cast::nullableString($data, 'receiptUrl');
		$this->txUuid = Cast::nullableString($data, 'txUuid');
		$this->relatedTxUuid = Cast::nullableString($data, 'relatedTxUuid');
		$this->productUuid = Cast::nullableString($data, 'productUuid');
		$this->productReference = Cast::nullableString($data, 'productReference');
		$this->productDescription = Cast::nullableString($data, 'productDescription');
		$this->customerUuid = Cast::nullableString($data, 'customerUuid');
		$this->userUuid = Cast::nullableString($data, 'userUuid');
	}
}
