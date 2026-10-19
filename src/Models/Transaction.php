<?php

declare(strict_types=1);

namespace QBitFlow\Models;

use DateTimeImmutable;
use QBitFlow\Support\Cast;

/**
 * A row of the transactions feed (`transactions->list()`): a one-time payment or a
 * subscription's bill, with the fields of its resource (a {@see Payment} or a {@see Bill}). Open
 * the row's detail by its `type` and `uuid` (`payments->get()`, `subscriptions->getBill()`).
 */
final readonly class Transaction extends Model
{
	/**
	 * `payment` (`pay@…`) or `bill` (`sub-hist@…`); see {@see \QBitFlow\Enums\TransactionKind}.
	 */
	public string $type;

	/**
	 * The payment's (`pay@…`) or the bill's (`sub-hist@…`) id.
	 */
	public string $uuid;

	/**
	 * When it was recorded, once confirmed on-chain.
	 */
	public DateTimeImmutable $createdAt;

	/**
	 * The customer's wallet that paid.
	 */
	public string $from;

	/**
	 * The wallet that received it.
	 */
	public string $to;

	/**
	 * What was paid for (a payment: its checkout's product name; a bill: its subscription's
	 * product name when it was billed).
	 */
	public string $productName;

	/**
	 * The same, for the description; null without one.
	 */
	public ?string $productDescription;

	/**
	 * The price in USD, a decimal string: a payment's checkout price, a bill's subscription's
	 * locked price; null on a bill of a subscription without a locked price.
	 */
	public ?string $priceUsd;

	/**
	 * What was added to the price, line by line; `[]` without any.
	 *
	 * @var list<FeeLine>
	 */
	public array $fees;

	/**
	 * What the customer paid, in the currency's units, exact (a decimal string).
	 */
	public string $amount;

	/**
	 * The same in the currency's smallest unit, a string of digits.
	 */
	public string $amountMinUnits;

	/**
	 * What the customer paid in USD: the price and the fees' lines.
	 */
	public string $amountUsd;

	/**
	 * The currency paid in.
	 */
	public Currency $currency;

	/**
	 * The product paid for; null for an inline product.
	 */
	public ?string $productUuid;

	/**
	 * The transaction that paid it.
	 */
	public string $txHash;

	/**
	 * The customer, when QBitFlow has one.
	 */
	public ?string $customerUuid;

	/**
	 * Names the customer, even once deleted (API reads only).
	 */
	public ?CustomerSummary $customer;

	/**
	 * Your reference of the customer, as the checkout was given it.
	 */
	public ?string $customerReference;

	/**
	 * A bill's subscription (`sub@…`); null on a payment.
	 */
	public ?string $subscriptionUuid;

	/**
	 * A bill's period's start (its due date); null on a payment.
	 */
	public ?DateTimeImmutable $periodStart;

	/**
	 * A bill's period's end: paid until then; null on a payment.
	 */
	public ?DateTimeImmutable $periodEnd;

	/**
	 * A payment's own reference (a bill has none of its own).
	 */
	public ?string $reference;

	/**
	 * The reference of a bill's subscription.
	 */
	public ?string $subscriptionReference;

	/**
	 * A payment's merchant data (copied from its checkout session); `[]` for a bill (its
	 * subscription's is on the subscription).
	 *
	 * @var array<string,string>
	 */
	public array $metadata;

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
	 * The member whose space the row is in; null for the organization's own.
	 */
	public ?string $userUuid;

	/**
	 * Its fees, split and transaction, as on the payment or the bill.
	 */
	public Settlement $settlement;

	/**
	 * When the chain confirmed it.
	 */
	public ?DateTimeImmutable $confirmedAt;

	/**
	 * Everything the customer's wallet sent, in the currency's units.
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
	 * Its refund, if any.
	 */
	public ?RefundSummary $refund;

	/**
	 * Whether it can be refunded now.
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
		$this->type = Cast::string($data, 'type');
		$this->uuid = Cast::string($data, 'uuid');
		$this->createdAt = Cast::date($data, 'createdAt');
		$this->from = Cast::string($data, 'from');
		$this->to = Cast::string($data, 'to');
		$this->productName = Cast::string($data, 'productName');
		$this->productDescription = Cast::nullableString($data, 'productDescription');
		$this->priceUsd = Cast::nullableString($data, 'priceUsd');
		$this->fees = Cast::listOf($data, 'fees', FeeLine::fromArray(...));
		$this->amount = Cast::string($data, 'amount');
		$this->amountMinUnits = Cast::string($data, 'amountMinUnits');
		$this->amountUsd = Cast::string($data, 'amountUsd');
		$this->currency = Cast::object($data, 'currency', Currency::fromArray(...));
		$this->productUuid = Cast::nullableString($data, 'productUuid');
		$this->txHash = Cast::string($data, 'txHash');
		$this->customerUuid = Cast::nullableString($data, 'customerUuid');
		$this->customer = Cast::nullableObject($data, 'customer', CustomerSummary::fromArray(...));
		$this->customerReference = Cast::nullableString($data, 'customerReference');
		$this->subscriptionUuid = Cast::nullableString($data, 'subscriptionUuid');
		$this->periodStart = Cast::nullableDate($data, 'periodStart');
		$this->periodEnd = Cast::nullableDate($data, 'periodEnd');
		$this->reference = Cast::nullableString($data, 'reference');
		$this->subscriptionReference = Cast::nullableString($data, 'subscriptionReference');
		$this->metadata = Cast::stringMap($data, 'metadata');
		$this->chain = Cast::nullableString($data, 'chain');
		$this->explorerUrl = Cast::nullableString($data, 'explorerUrl');
		$this->test = Cast::bool($data, 'test');
		$this->userUuid = Cast::nullableString($data, 'userUuid');
		$this->settlement = Cast::object($data, 'settlement', Settlement::fromArray(...));
		$this->confirmedAt = Cast::nullableDate($data, 'confirmedAt');
		$this->paid = Cast::nullableString($data, 'paid');
		$this->paidMinUnits = Cast::nullableString($data, 'paidMinUnits');
		$this->paidUsd = Cast::nullableString($data, 'paidUsd');
		$this->refund = Cast::nullableObject($data, 'refund', RefundSummary::fromArray(...));
		$this->refundable = Cast::nullableBool($data, 'refundable');
		$this->notRefundableReason = Cast::nullableString($data, 'notRefundableReason');
	}
}
