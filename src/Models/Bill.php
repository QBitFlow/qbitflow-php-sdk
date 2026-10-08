<?php

declare(strict_types=1);

namespace QBitFlow\Models;

use DateTimeImmutable;
use QBitFlow\Support\Cast;

/**
 * A subscription's paid bill (a subscription history entry, `sub-hist@…`).
 *
 * On `subscriptions->getPublicHistory()` the fields the API only returns to the merchant
 * (customerUuid, customerReference, customer, metadata, userUuid, paidMinUnits, paidUsd, refund…)
 * are empty.
 */
readonly class Bill extends Model
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
	 * What the customer paid, in USD.
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
	 * The subscription's product name when the bill was paid.
	 */
	public string $name;

	/**
	 * The same, for the product's description.
	 */
	public string $description;

	/**
	 * The subscription's product.
	 */
	public ?string $productUuid;

	/**
	 * The bill's subscription (`sub@…`).
	 */
	public string $subscriptionUuid;

	/**
	 * The customer, when QBitFlow has one.
	 */
	public ?string $customerUuid;

	/**
	 * The merchant's reference of the customer.
	 */
	public ?string $customerReference;

	/**
	 * Names the customer (API reads only).
	 */
	public ?CustomerSummary $customer;

	/**
	 * The bill's fees and split.
	 */
	public PaymentMetadata $metadata;

	/**
	 * The start of the period the bill paid (its due date).
	 */
	public ?DateTimeImmutable $periodStart;

	/**
	 * The end of the period the bill paid: paid until then.
	 */
	public ?DateTimeImmutable $periodEnd;

	/**
	 * When the chain confirmed it, when known.
	 */
	public ?DateTimeImmutable $confirmedAt;

	/**
	 * Everything the customer's wallet sent, in min units.
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
	 * Why not.
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
		$this->amount = Cast::float($data, 'amount');
		$this->amountMinUnits = Cast::string($data, 'amountMinUnits');
		$this->currencyId = Cast::uint($data, 'currencyId');
		$this->currency = Cast::nullableObject($data, 'currency', Currency::fromArray(...));
		$this->txHash = Cast::string($data, 'txHash');
		$this->chain = Cast::nullableString($data, 'chain');
		$this->explorerUrl = Cast::nullableString($data, 'explorerUrl');
		$this->test = Cast::bool($data, 'test');
		$this->userUuid = Cast::nullableString($data, 'userUuid');
		$this->name = Cast::string($data, 'name');
		$this->description = Cast::string($data, 'description');
		$this->productUuid = Cast::nullableString($data, 'productUuid');
		$this->subscriptionUuid = Cast::string($data, 'subscriptionUuid');
		$this->customerUuid = Cast::nullableString($data, 'customerUuid');
		$this->customerReference = Cast::nullableString($data, 'customerReference');
		$this->customer = Cast::nullableObject($data, 'customer', CustomerSummary::fromArray(...));
		$this->metadata = Cast::object($data, 'metadata', PaymentMetadata::fromArray(...));
		$this->periodStart = Cast::nullableDate($data, 'periodStart');
		$this->periodEnd = Cast::nullableDate($data, 'periodEnd');
		$this->confirmedAt = Cast::nullableDate($data, 'confirmedAt');
		$this->paidMinUnits = Cast::nullableString($data, 'paidMinUnits');
		$this->paidUsd = Cast::nullableFloat($data, 'paidUsd');
		$this->refund = Cast::nullableObject($data, 'refund', RefundSummary::fromArray(...));
		$this->refundable = Cast::nullableBool($data, 'refundable');
		$this->notRefundableReason = Cast::nullableString($data, 'notRefundableReason');
	}
}
