<?php

declare(strict_types=1);

namespace QBitFlow\Models;

use DateTimeImmutable;
use QBitFlow\Support\Cast;

/**
 * A product: a one-time product, or a subscription product with its terms.
 */
final readonly class Product extends Model
{
	/**
	 * The product's id.
	 */
	public string $uuid;

	/**
	 * Its name.
	 */
	public string $name;

	/**
	 * Its description (`''` when none).
	 */
	public string $description;

	/**
	 * Its price in USD (per period for a subscription product).
	 */
	public float $price;

	/**
	 * When it was created.
	 */
	public DateTimeImmutable $createdAt;

	/**
	 * False for a hidden product (left out of `products->list()` unless includeHidden).
	 */
	public bool $isActive;

	/**
	 * The merchant's reference (defaults to the uuid).
	 */
	public string $reference;

	/**
	 * A subscription product's terms; null for a one-time product.
	 */
	public ?SubscriptionTerms $subscription;

	/**
	 * The product's payment link.
	 */
	public ?string $paymentLink;

	/**
	 * True in test mode.
	 */
	public bool $test;

	/**
	 * The member whose space it is in; null for the organization's own.
	 */
	public ?string $userUuid;

	/**
	 * @param array<string,mixed> $data
	 */
	protected function __construct(array $data)
	{
		$this->uuid = Cast::string($data, 'uuid');
		$this->name = Cast::string($data, 'name');
		$this->description = Cast::string($data, 'description');
		$this->price = Cast::float($data, 'price');
		$this->createdAt = Cast::date($data, 'createdAt');
		$this->isActive = Cast::bool($data, 'isActive');
		$this->reference = Cast::string($data, 'reference');
		$this->subscription = Cast::nullableObject($data, 'subscription', SubscriptionTerms::fromArray(...));
		$this->paymentLink = Cast::nullableString($data, 'paymentLink');
		$this->test = Cast::bool($data, 'test');
		$this->userUuid = Cast::nullableString($data, 'userUuid');
	}
}
