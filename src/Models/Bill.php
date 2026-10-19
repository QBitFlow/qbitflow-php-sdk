<?php

declare(strict_types=1);

namespace QBitFlow\Models;

use DateTimeImmutable;
use QBitFlow\Support\Cast;

/**
 * One paid bill of a subscription (`sub-hist@…`): what its customer paid for one period
 * (`periodStart` → `periodEnd`), in the subscription's token, and how it settled. A failed
 * attempt is no bill (`failures->list()`, `subscription.billingFailed`). Bills carry no merchant
 * metadata: read the subscription's.
 */
final readonly class Bill extends Model
{
	/**
	 * The bill's id (`sub-hist@…`).
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
	 * What the customer paid, in the currency's units, exact (a decimal string).
	 */
	public string $amount;

	/**
	 * The same in the currency's smallest unit, a string of digits.
	 */
	public string $amountMinUnits;

	/**
	 * What the customer paid in USD, a decimal string: the subscription's `priceUsd` plus its
	 * `fees` lines.
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
	 * The chain it was paid on.
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
	 * The subscription's product name when the bill was paid (later product edits don't change it).
	 */
	public string $productName;

	/**
	 * The same, for the product's description; null without one.
	 */
	public ?string $productDescription;

	/**
	 * The subscription's product.
	 */
	public ?string $productUuid;

	/**
	 * The subscription it bills (`sub@…`).
	 */
	public string $subscriptionUuid;

	/**
	 * The customer, when QBitFlow has one.
	 */
	public ?string $customerUuid;

	/**
	 * Your reference of the customer, as the checkout was given it.
	 */
	public ?string $customerReference;

	/**
	 * Names the customer, even once deleted (API reads only).
	 */
	public ?CustomerSummary $customer;

	/**
	 * How it settled: its fees, its split and its transaction.
	 */
	public Settlement $settlement;

	/**
	 * The start of the period the bill paid: its due date (the subscription's start for its first
	 * bill); null on bills recorded before it was.
	 */
	public ?DateTimeImmutable $periodStart;

	/**
	 * The end of the period the bill paid (the subscription's next bill): paid until then; null
	 * on bills recorded before it was.
	 */
	public ?DateTimeImmutable $periodEnd;

	/**
	 * When the chain confirmed it (its block's time).
	 */
	public ?DateTimeImmutable $confirmedAt;

	/**
	 * Everything the customer's wallet sent, in the currency's units (`amount` plus the network
	 * fee): a refund's base.
	 */
	public ?string $paid;

	/**
	 * The same in the currency's smallest unit.
	 */
	public ?string $paidMinUnits;

	/**
	 * The same in USD.
	 */
	public ?string $paidUsd;

	/**
	 * Its refund, if any (API reads only).
	 */
	public ?RefundSummary $refund;

	/**
	 * Whether it can be refunded now (API reads only).
	 */
	public ?bool $refundable;

	/**
	 * Why not ({@see \QBitFlow\Enums\NotRefundableReason}); null when refundable.
	 */
	public ?string $notRefundableReason;

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
		$this->productName = Cast::string($data, 'productName');
		$this->productDescription = Cast::nullableString($data, 'productDescription');
		$this->productUuid = Cast::nullableString($data, 'productUuid');
		$this->subscriptionUuid = Cast::string($data, 'subscriptionUuid');
		$this->customerUuid = Cast::nullableString($data, 'customerUuid');
		$this->customerReference = Cast::nullableString($data, 'customerReference');
		$this->customer = Cast::nullableObject($data, 'customer', CustomerSummary::fromArray(...));
		$this->settlement = Cast::object($data, 'settlement', Settlement::fromArray(...));
		$this->periodStart = Cast::nullableDate($data, 'periodStart');
		$this->periodEnd = Cast::nullableDate($data, 'periodEnd');
		$this->confirmedAt = Cast::nullableDate($data, 'confirmedAt');
		$this->paid = Cast::nullableString($data, 'paid');
		$this->paidMinUnits = Cast::nullableString($data, 'paidMinUnits');
		$this->paidUsd = Cast::nullableString($data, 'paidUsd');
		$this->refund = Cast::nullableObject($data, 'refund', RefundSummary::fromArray(...));
		$this->refundable = Cast::nullableBool($data, 'refundable');
		$this->notRefundableReason = Cast::nullableString($data, 'notRefundableReason');
	}
}
