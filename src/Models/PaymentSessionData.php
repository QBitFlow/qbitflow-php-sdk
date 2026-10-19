<?php

declare(strict_types=1);

namespace QBitFlow\Models;

use DateTimeImmutable;
use QBitFlow\Support\Cast;

/**
 * A one-time payment's checkout session, as `checkout.expired` sends it (flat; a subscription's
 * is {@see SubscriptionSessionData}). No customer is named: match it on `uuid`, `reference` or
 * `metadata`.
 */
readonly class PaymentSessionData extends Model
{
	/**
	 * The session's id (`pay@…`, `sub@…`): the id the payment or subscription would have had.
	 */
	public string $uuid;

	/**
	 * The merchant's reference of the checkout (an order id), set when creating it.
	 */
	public ?string $reference;

	/**
	 * The product paid for; null for an inline product of a payment.
	 */
	public ?string $productUuid;

	/**
	 * The product's reference, when the checkout named it so.
	 */
	public ?string $productReference;

	/**
	 * What is paid for: the product's name as the checkout was opened (or the inline one).
	 */
	public ?string $productName;

	/**
	 * The product's description as the checkout was opened; null without one.
	 */
	public ?string $description;

	/**
	 * The product's price in USD, a decimal string.
	 */
	public string $priceUsd;

	/**
	 * What the checkout adds to the price, line by line, in the order the checkout shows them:
	 * the merchant's lines, then the processing fee (when the customer pays it). A subscription's
	 * are charged with every bill. `[]` without any.
	 *
	 * @var list<FeeLine>
	 */
	public array $fees;

	/**
	 * What the customer pays in USD before the network fee, a decimal string: `priceUsd` plus
	 * every line of `fees` (the payment's `amountUsd`, or each bill's of a subscription).
	 */
	public string $amountUsd;

	/**
	 * The merchant's page for the customer after paying (placeholders filled).
	 */
	public ?string $successUrl;

	/**
	 * The merchant's page for a customer who leaves the checkout (placeholders filled).
	 */
	public ?string $cancelUrl;

	/**
	 * The merchant's success page on the hosted page's copy only: absent from `checkout.expired`.
	 */
	public ?string $redirectUrl;

	/**
	 * The merchant's organization, as the checkout shows it.
	 */
	public string $organizationName;

	/**
	 * The member the checkout pays (a marketplace's seller), by name; null on the organization's
	 * own checkouts.
	 */
	public ?string $userName;

	/**
	 * True for a checkout paid on test networks.
	 */
	public bool $test;

	/**
	 * The session's kind: `payment` or `createSubscription` ({@see \QBitFlow\Enums\TransactionType}).
	 */
	public string $txType;

	/**
	 * The currencies the customer could pay with.
	 *
	 * @var list<Currency>
	 */
	public array $availableCurrencies;

	/**
	 * The merchant's own data on the checkout; `[]` without any.
	 *
	 * @var array<string,string>
	 */
	public array $metadata;

	/**
	 * When the session was opened.
	 */
	public ?DateTimeImmutable $createdAt;

	/**
	 * When it could no longer be paid (when the merchant expired it, if so).
	 */
	public ?DateTimeImmutable $expiresAt;

	/**
	 * @param array<string,mixed> $data
	 */
	protected function __construct(array $data)
	{
		$this->uuid = Cast::string($data, 'uuid');
		$this->reference = Cast::nullableString($data, 'reference');
		$this->productUuid = Cast::nullableString($data, 'productUuid');
		$this->productReference = Cast::nullableString($data, 'productReference');
		$this->productName = Cast::nullableString($data, 'productName');
		$this->description = Cast::nullableString($data, 'description');
		$this->priceUsd = Cast::string($data, 'priceUsd');
		$this->fees = Cast::listOf($data, 'fees', FeeLine::fromArray(...));
		$this->amountUsd = Cast::string($data, 'amountUsd');
		$this->successUrl = Cast::nullableString($data, 'successUrl');
		$this->cancelUrl = Cast::nullableString($data, 'cancelUrl');
		$this->redirectUrl = Cast::nullableString($data, 'redirectUrl');
		$this->organizationName = Cast::string($data, 'organizationName');
		$this->userName = Cast::nullableString($data, 'userName');
		$this->test = Cast::bool($data, 'test');
		$this->txType = Cast::string($data, 'txType');
		$this->availableCurrencies = Cast::listOf($data, 'availableCurrencies', Currency::fromArray(...));
		$this->metadata = Cast::stringMap($data, 'metadata');
		$this->createdAt = Cast::nullableDate($data, 'createdAt');
		$this->expiresAt = Cast::nullableDate($data, 'expiresAt');
	}
}
