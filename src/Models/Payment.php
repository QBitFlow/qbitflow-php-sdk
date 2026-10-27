<?php

declare(strict_types=1);

namespace QBitFlow\Models;

use DateTimeImmutable;
use QBitFlow\Support\Cast;

/**
 * A confirmed one-time payment (`pay@…`).
 *
 * Money is a decimal string everywhere: `amount` is the token amount the customer paid (`"10"`
 * for 10 USDC), `amountUsd` its USD value (the price plus the fees' lines), `priceUsd` the
 * checkout's price.
 */
readonly class Payment extends Model
{
	/**
	 * The payment's id (`pay@…`), also its checkout session's.
	 */
	public string $uuid;

	/**
	 * When it was recorded, once confirmed on-chain.
	 */
	public DateTimeImmutable $createdAt;

	/**
	 * The customer's wallet.
	 */
	public string $from;

	/**
	 * The wallet that received it (the merchant's, or the organization's while it holds the
	 * member's funds).
	 */
	public string $to;

	/**
	 * What the customer paid, in the currency's units, exact (a decimal string: `"10"` for 10
	 * USDC): `amountMinUnits / 10^decimals`. It is what the contracts split; the network fee paid
	 * on top is not in it (`settlement->split->networkFee`).
	 */
	public string $amount;

	/**
	 * The same in the currency's smallest unit, a string of digits (`"10000000"` for 10 USDC).
	 */
	public string $amountMinUnits;

	/**
	 * What the customer paid in USD, a decimal string: `priceUsd` plus every line of `fees`.
	 */
	public string $amountUsd;

	/**
	 * The currency paid in.
	 */
	public Currency $currency;

	/**
	 * The transaction's hash.
	 */
	public string $txHash;

	/**
	 * The chain it was paid on (`ETH`, `BASE`, `SOL`; the testnet's in test mode).
	 */
	public ?string $chain;

	/**
	 * The transaction on the chain's block explorer.
	 */
	public ?string $explorerUrl;

	/**
	 * True in test mode.
	 */
	public bool $test;

	/**
	 * The member whose space it is in; null for the organization's own.
	 */
	public ?string $userUuid;

	/**
	 * Your reference for the payment (an order or invoice id), set when creating its checkout.
	 */
	public ?string $reference;

	/**
	 * The product's price in USD, as the checkout had it, a decimal string: `amountUsd` is the
	 * price plus the fees' lines.
	 */
	public string $priceUsd;

	/**
	 * What the checkout added to the price, line by line, as the customer saw them (a tax,
	 * shipping, the processing fee when the customer paid it); `[]` without any.
	 *
	 * @var list<FeeLine>
	 */
	public array $fees;

	/**
	 * What was paid for: the checkout's product name when the checkout was created (later
	 * product edits don't change it).
	 */
	public string $productName;

	/**
	 * The same, for the product's description; null without one.
	 */
	public ?string $productDescription;

	/**
	 * Your own data, copied (read-only) from the checkout session that created the payment; `[]`
	 * without any.
	 *
	 * @var array<string,string>
	 */
	public array $metadata;

	/**
	 * The product paid for; null for an inline product.
	 */
	public ?string $productUuid;

	/**
	 * The customer, when QBitFlow has one (named by the checkout, found by its
	 * `customerReference`, or recorded from the details the customer gave).
	 */
	public ?string $customerUuid;

	/**
	 * Your reference of the customer, as the checkout was given it, whether or not QBitFlow has a
	 * customer with it.
	 */
	public ?string $customerReference;

	/**
	 * Names the customer, even once deleted (API reads only, never in webhooks).
	 */
	public ?CustomerSummary $customer;

	/**
	 * How it settled: its fees, its split and its transaction.
	 */
	public Settlement $settlement;

	/**
	 * When the chain confirmed it (its block's time); null only on records made before it was
	 * recorded.
	 */
	public ?DateTimeImmutable $confirmedAt;

	/**
	 * Everything the customer's wallet sent, in the currency's units: `amount` plus the network
	 * fee paid on top. A refund's base. Null when the split wasn't recorded.
	 */
	public ?string $paid;

	/**
	 * The same in the currency's smallest unit.
	 */
	public ?string $paidMinUnits;

	/**
	 * The same in USD, at the payment's time.
	 */
	public ?string $paidUsd;

	/**
	 * Its refund (one per transaction), if any (API reads only). The merchant's own refund
	 * appears once its transfer confirmed (approved).
	 */
	public ?RefundSummary $refund;

	/**
	 * Whether it can be refunded now (API reads only).
	 */
	public ?bool $refundable;

	/**
	 * Why not (`refundExists`, `heldFundsReleased`; {@see \QBitFlow\Enums\NotRefundableReason});
	 * null when refundable.
	 */
	public ?string $notRefundableReason;

	/**
	 * When its checkout session was created.
	 */
	public ?DateTimeImmutable $checkoutOpenedAt;

	/**
	 * @param array<string,mixed> $data
	 */
	protected function __construct(array $data)
	{
		$this->uuid = Cast::string($data, 'uuid');
		$this->createdAt = Cast::date($data, 'createdAt');
		$this->from = Cast::string($data, 'from');
		$this->to = Cast::string($data, 'to');
		$this->amount = Cast::string($data, 'amount');
		$this->amountMinUnits = Cast::string($data, 'amountMinUnits');
		$this->amountUsd = Cast::string($data, 'amountUsd');
		$this->currency = Cast::object($data, 'currency', Currency::fromArray(...));
		$this->txHash = Cast::string($data, 'txHash');
		$this->chain = Cast::nullableString($data, 'chain');
		$this->explorerUrl = Cast::nullableString($data, 'explorerUrl');
		$this->test = Cast::bool($data, 'test');
		$this->userUuid = Cast::nullableString($data, 'userUuid');
		$this->reference = Cast::nullableString($data, 'reference');
		$this->priceUsd = Cast::string($data, 'priceUsd');
		$this->fees = Cast::listOf($data, 'fees', FeeLine::fromArray(...));
		$this->productName = Cast::string($data, 'productName');
		$this->productDescription = Cast::nullableString($data, 'productDescription');
		$this->metadata = Cast::stringMap($data, 'metadata');
		$this->productUuid = Cast::nullableString($data, 'productUuid');
		$this->customerUuid = Cast::nullableString($data, 'customerUuid');
		$this->customerReference = Cast::nullableString($data, 'customerReference');
		$this->customer = Cast::nullableObject($data, 'customer', CustomerSummary::fromArray(...));
		$this->settlement = Cast::object($data, 'settlement', Settlement::fromArray(...));
		$this->confirmedAt = Cast::nullableDate($data, 'confirmedAt');
		$this->paid = Cast::nullableString($data, 'paid');
		$this->paidMinUnits = Cast::nullableString($data, 'paidMinUnits');
		$this->paidUsd = Cast::nullableString($data, 'paidUsd');
		$this->refund = Cast::nullableObject($data, 'refund', RefundSummary::fromArray(...));
		$this->refundable = Cast::nullableBool($data, 'refundable');
		$this->notRefundableReason = Cast::nullableString($data, 'notRefundableReason');
		$this->checkoutOpenedAt = Cast::nullableDate($data, 'checkoutOpenedAt');
	}
}
