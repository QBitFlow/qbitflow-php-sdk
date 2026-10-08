<?php

declare(strict_types=1);

namespace QBitFlow\Models;

use DateTimeImmutable;
use QBitFlow\Support\Cast;

/**
 * A payment checkout session, as `checkout.expired` sends it.
 */
readonly class PaymentSessionData extends Model
{
	/**
	 * The session's id (`pay@…`, `sub@…`).
	 */
	public string $uuid;

	/**
	 * The merchant's reference, set when creating it.
	 */
	public ?string $reference;

	/**
	 * The product, when it named one.
	 */
	public ?string $productUuid;

	/**
	 * The product's reference, when it named it so.
	 */
	public ?string $productReference;

	/**
	 * The product's name.
	 */
	public ?string $productName;

	/**
	 * The product's description.
	 */
	public ?string $description;

	/**
	 * The price in USD.
	 */
	public ?float $price;

	/**
	 * Where the customer goes after paying (placeholders filled).
	 */
	public ?string $successUrl;

	/**
	 * Where a customer who leaves the checkout goes.
	 */
	public ?string $cancelUrl;

	/**
	 * The merchant's success page, on QBitFlow's own success page's copy.
	 */
	public ?string $redirectUrl;

	/**
	 * The organization's name.
	 */
	public string $organizationName;

	/**
	 * The member who created it, for a member's session.
	 */
	public ?string $userName;

	/**
	 * True in test mode.
	 */
	public bool $test;

	/**
	 * `payment` or `createSubscription` ({@see \QBitFlow\Enums\TransactionType}).
	 */
	public string $txType;

	/**
	 * The currencies accepted for the payment.
	 *
	 * @var list<int>
	 */
	public array $availableCurrencyIds;

	/**
	 * When the session was opened.
	 */
	public ?DateTimeImmutable $createdAt;

	/**
	 * When it could no longer be paid.
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
		$this->price = Cast::nullableFloat($data, 'price');
		$this->successUrl = Cast::nullableString($data, 'successUrl');
		$this->cancelUrl = Cast::nullableString($data, 'cancelUrl');
		$this->redirectUrl = Cast::nullableString($data, 'redirectUrl');
		$this->organizationName = Cast::string($data, 'organizationName');
		$this->userName = Cast::nullableString($data, 'userName');
		$this->test = Cast::bool($data, 'test');
		$this->txType = Cast::string($data, 'txType');
		$this->availableCurrencyIds = Cast::uintList($data, 'availableCurrencyIds');
		$this->createdAt = Cast::nullableDate($data, 'createdAt');
		$this->expiresAt = Cast::nullableDate($data, 'expiresAt');
	}
}
