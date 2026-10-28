<?php

declare(strict_types=1);

namespace QBitFlow\Models;

use DateTimeImmutable;
use QBitFlow\Support\Cast;

/**
 * Something a merchant sells: a one-time product, or a subscription product with its terms. Its
 * payment link opens a checkout of it.
 */
final readonly class Product extends Model
{
	/**
	 * The product's id (a UUIDv7).
	 */
	public string $uuid;

	/**
	 * Its name.
	 */
	public string $name;

	/**
	 * Its description; null (absent) without one.
	 */
	public ?string $description;

	/**
	 * Its price in USD, a decimal string (`"12.5"`): what a checkout of the product charges before
	 * its fees (per period for a subscription product).
	 */
	public string $priceUsd;

	/**
	 * When it was created.
	 */
	public DateTimeImmutable $createdAt;

	/**
	 * False for a hidden product: out of `products->list()` unless `includeHidden`, still
	 * readable and editable by uuid. Hidden are the inline products of subscription checkouts,
	 * those of a removed member or a closed organization, and those hidden with `update()`.
	 */
	public bool $isActive;

	/**
	 * The merchant's reference, unique per space and mode (the uuid when none was given).
	 */
	public string $reference;

	/**
	 * The merchant's own data on the product (never copied onto its checkouts, payments or
	 * subscriptions); `[]` without any.
	 *
	 * @var array<string,string>
	 */
	public array $metadata;

	/**
	 * A subscription product's terms (the defaults of its subscription checkouts); null for a
	 * one-time product.
	 */
	public ?SubscriptionTerms $subscription;

	/**
	 * The product's shareable link: opening it starts a checkout of the product.
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
		$this->description = Cast::nullableString($data, 'description');
		$this->priceUsd = Cast::string($data, 'priceUsd');
		$this->createdAt = Cast::date($data, 'createdAt');
		$this->isActive = Cast::bool($data, 'isActive');
		$this->reference = Cast::string($data, 'reference');
		$this->metadata = Cast::stringMap($data, 'metadata');
		$this->subscription = Cast::nullableObject($data, 'subscription', SubscriptionTerms::fromArray(...));
		$this->paymentLink = Cast::nullableString($data, 'paymentLink');
		$this->test = Cast::bool($data, 'test');
		$this->userUuid = Cast::nullableString($data, 'userUuid');
	}
}
