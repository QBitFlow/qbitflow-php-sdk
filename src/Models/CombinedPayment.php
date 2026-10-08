<?php

declare(strict_types=1);

namespace QBitFlow\Models;

use DateTimeImmutable;
use QBitFlow\Support\Cast;

/**
 * A row of the combined feed: a one-time payment or a subscription's bill.
 */
final readonly class CombinedPayment extends Model
{
	/**
	 * `payment` or `subscriptionHistory` ({@see \QBitFlow\Enums\CombinedPaymentSource}).
	 */
	public string $source;

	/**
	 * The payment's (`pay@…`) or the bill's (`sub-hist@…`) id.
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
	 * What was paid for.
	 */
	public string $name;

	/**
	 * The same, for the description.
	 */
	public string $description;

	/**
	 * What the customer paid, in USD.
	 */
	public float $amount;

	/**
	 * The amount in the currency's min units (a decimal string).
	 */
	public string $amountMinUnits;

	/**
	 * The currency paid in.
	 */
	public int $currencyId;

	/**
	 * That currency.
	 */
	public ?Currency $currency;

	/**
	 * The product paid for; null for an inline product.
	 */
	public ?string $productUuid;

	/**
	 * The transaction's hash.
	 */
	public string $txHash;

	/**
	 * The customer, when QBitFlow has one.
	 */
	public ?string $customerUuid;

	/**
	 * Names the customer (API reads only).
	 */
	public ?CustomerSummary $customer;

	/**
	 * The merchant's reference of the customer.
	 */
	public ?string $customerReference;

	/**
	 * The bill's subscription (`sub@…`); null on a payment.
	 */
	public ?string $subscriptionUuid;

	/**
	 * The payment's own reference (payment rows).
	 */
	public ?string $reference;

	/**
	 * The reference of the bill's subscription (bill rows).
	 */
	public ?string $subscriptionReference;

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
	 * Its refund, if any.
	 */
	public ?RefundSummary $refund;

	/**
	 * Whether it can be refunded now.
	 */
	public ?bool $refundable;

	/**
	 * Why not.
	 */
	public ?string $notRefundableReason;

	/**
	 * Its fees and split; null only on payments recorded before fees were split (v1).
	 */
	public ?PaymentMetadata $metadata;

	/**
	 * @param array<string,mixed> $data
	 */
	protected function __construct(array $data)
	{
		$this->source = Cast::string($data, 'source');
		$this->uuid = Cast::string($data, 'uuid');
		$this->createdAt = Cast::date($data, 'createdAt');
		$this->from = Cast::string($data, 'from');
		$this->to = Cast::string($data, 'to');
		$this->name = Cast::string($data, 'name');
		$this->description = Cast::string($data, 'description');
		$this->amount = Cast::float($data, 'amount');
		$this->amountMinUnits = Cast::string($data, 'amountMinUnits');
		$this->currencyId = Cast::uint($data, 'currencyId');
		$this->currency = Cast::nullableObject($data, 'currency', Currency::fromArray(...));
		$this->productUuid = Cast::nullableString($data, 'productUuid');
		$this->txHash = Cast::string($data, 'txHash');
		$this->customerUuid = Cast::nullableString($data, 'customerUuid');
		$this->customer = Cast::nullableObject($data, 'customer', CustomerSummary::fromArray(...));
		$this->customerReference = Cast::nullableString($data, 'customerReference');
		$this->subscriptionUuid = Cast::nullableString($data, 'subscriptionUuid');
		$this->reference = Cast::nullableString($data, 'reference');
		$this->subscriptionReference = Cast::nullableString($data, 'subscriptionReference');
		$this->chain = Cast::nullableString($data, 'chain');
		$this->explorerUrl = Cast::nullableString($data, 'explorerUrl');
		$this->test = Cast::bool($data, 'test');
		$this->userUuid = Cast::nullableString($data, 'userUuid');
		$this->refund = Cast::nullableObject($data, 'refund', RefundSummary::fromArray(...));
		$this->refundable = Cast::nullableBool($data, 'refundable');
		$this->notRefundableReason = Cast::nullableString($data, 'notRefundableReason');
		$this->metadata = Cast::nullableObject($data, 'metadata', PaymentMetadata::fromArray(...));
	}
}
