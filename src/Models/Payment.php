<?php

declare(strict_types=1);

namespace QBitFlow\Models;

use DateTimeImmutable;
use QBitFlow\Support\Cast;

/**
 * A confirmed one-time payment.
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
	 * The wallet that received it.
	 */
	public string $to;

	/**
	 * What the customer paid, in USD: the price plus the fees (`price + Σ fees[].amountUsd`),
	 * what the contracts split. The network fee the customer paid on top is not in it.
	 */
	public float $amount;

	/**
	 * The amount in the token's min units (a decimal string).
	 */
	public string $amountMinUnits;

	/**
	 * The currency paid in (`$client->currencies->get()`).
	 */
	public int $currencyId;

	/**
	 * That currency.
	 */
	public ?Currency $currency;

	/**
	 * The transaction's hash.
	 */
	public string $txHash;

	/**
	 * The chain it was paid on (`ETH`, `BASE`, `SOL`; the testnet's in test mode), see {@see \QBitFlow\Enums\Chain}.
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
	 * The merchant's reference for the payment, set when creating its checkout.
	 */
	public ?string $reference;

	/**
	 * The product's price in USD, as the checkout had it. On payments recorded before checkout
	 * fees existed, it is `amount`.
	 */
	public float $price;

	/**
	 * What the checkout added to the price, line by line, as the customer saw them (a tax,
	 * shipping, the processing fee when the customer paid it); `[]` without any.
	 *
	 * @var list<FeeLine>
	 */
	public array $fees;

	/**
	 * What was paid for: the checkout's product name when the checkout was created.
	 */
	public string $name;

	/**
	 * The same, for the product's description.
	 */
	public string $description;

	/**
	 * The product paid for; null for an inline product.
	 */
	public ?string $productUuid;

	/**
	 * The customer, when QBitFlow has one.
	 */
	public ?string $customerUuid;

	/**
	 * The merchant's reference of the customer, as the checkout was given it.
	 */
	public ?string $customerReference;

	/**
	 * A qbf.cash tipper's message to the handle's owner.
	 */
	public ?string $note;

	/**
	 * Names the customer (API reads only, never in webhooks).
	 */
	public ?CustomerSummary $customer;

	/**
	 * The payment's fees and split.
	 */
	public PaymentMetadata $metadata;

	/**
	 * When the chain confirmed it (its block's time), when known.
	 */
	public ?DateTimeImmutable $confirmedAt;

	/**
	 * Everything the customer's wallet sent (amount plus the network fee paid on top), in min units: a refund's base.
	 */
	public ?string $paidMinUnits;

	/**
	 * The same in USD.
	 */
	public ?float $paidUsd;

	/**
	 * Its refund, if any (API reads only).
	 */
	public ?RefundSummary $refund;

	/**
	 * Whether it can be refunded now (API reads only).
	 */
	public ?bool $refundable;

	/**
	 * Why not (`refundExists`, `heldFundsReleased`).
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
		$this->amount = Cast::float($data, 'amount');
		$this->amountMinUnits = Cast::string($data, 'amountMinUnits');
		$this->currencyId = Cast::uint($data, 'currencyId');
		$this->currency = Cast::nullableObject($data, 'currency', Currency::fromArray(...));
		$this->txHash = Cast::string($data, 'txHash');
		$this->chain = Cast::nullableString($data, 'chain');
		$this->explorerUrl = Cast::nullableString($data, 'explorerUrl');
		$this->test = Cast::bool($data, 'test');
		$this->userUuid = Cast::nullableString($data, 'userUuid');
		$this->reference = Cast::nullableString($data, 'reference');
		$this->price = Cast::float($data, 'price');
		$this->fees = Cast::listOf($data, 'fees', FeeLine::fromArray(...));
		$this->name = Cast::string($data, 'name');
		$this->description = Cast::string($data, 'description');
		$this->productUuid = Cast::nullableString($data, 'productUuid');
		$this->customerUuid = Cast::nullableString($data, 'customerUuid');
		$this->customerReference = Cast::nullableString($data, 'customerReference');
		$this->note = Cast::nullableString($data, 'note');
		$this->customer = Cast::nullableObject($data, 'customer', CustomerSummary::fromArray(...));
		$this->metadata = Cast::object($data, 'metadata', PaymentMetadata::fromArray(...));
		$this->confirmedAt = Cast::nullableDate($data, 'confirmedAt');
		$this->paidMinUnits = Cast::nullableString($data, 'paidMinUnits');
		$this->paidUsd = Cast::nullableFloat($data, 'paidUsd');
		$this->refund = Cast::nullableObject($data, 'refund', RefundSummary::fromArray(...));
		$this->refundable = Cast::nullableBool($data, 'refundable');
		$this->notRefundableReason = Cast::nullableString($data, 'notRefundableReason');
		$this->checkoutOpenedAt = Cast::nullableDate($data, 'checkoutOpenedAt');
	}
}
