<?php

declare(strict_types=1);

namespace QBitFlow\Models;

use DateTimeImmutable;
use QBitFlow\Support\Cast;

/**
 * A failed attempt to pay a checkout or a bill (the failures log).
 */
final readonly class Failure extends Model
{
	/**
	 * The failure's id.
	 */
	public string $uuid;

	/**
	 * `payment`, `subscriptionCheckout` or `bill` ({@see \QBitFlow\Enums\FailureKind}).
	 */
	public string $kind;

	/**
	 * What failed: the checkout (`pay@…`, `sub@…`) or the bill (`sub-hist@…`).
	 */
	public string $txUuid;

	/**
	 * Its number among its transaction's failed attempts, from 1.
	 */
	public int $attempt;

	/**
	 * A bill's subscription (`sub@…`).
	 */
	public ?string $subscriptionUuid;

	/**
	 * The error code (e.g. `insufficient_funds`).
	 */
	public string $code;

	/**
	 * What it means, from the code ({@see \QBitFlow\Enums\FailureCategory}).
	 */
	public string $category;

	/**
	 * What the customer was told.
	 */
	public ?string $message;

	/**
	 * What the customer tried to pay, in USD: never received.
	 */
	public float $attemptedUsd;

	/**
	 * The same in the currency's min units (a decimal string).
	 */
	public string $attemptedMinUnits;

	/**
	 * The currency it was tried in.
	 */
	public int $currencyId;

	/**
	 * The customer's wallet.
	 */
	public string $from;

	/**
	 * The transaction, when one was sent (a revert, a timeout).
	 */
	public ?string $txHash;

	/**
	 * The customer, when QBitFlow has one.
	 */
	public ?string $customerUuid;

	/**
	 * The product, when it had one.
	 */
	public ?string $productUuid;

	/**
	 * When it failed.
	 */
	public DateTimeImmutable $createdAt;

	/**
	 * Names the customer (API reads only).
	 */
	public ?CustomerSummary $customer;

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
		$this->kind = Cast::string($data, 'kind');
		$this->txUuid = Cast::string($data, 'txUuid');
		$this->attempt = Cast::int($data, 'attempt');
		$this->subscriptionUuid = Cast::nullableString($data, 'subscriptionUuid');
		$this->code = Cast::string($data, 'code');
		$this->category = Cast::string($data, 'category');
		$this->message = Cast::nullableString($data, 'message');
		$this->attemptedUsd = Cast::float($data, 'attemptedUsd');
		$this->attemptedMinUnits = Cast::string($data, 'attemptedMinUnits');
		$this->currencyId = Cast::uint($data, 'currencyId');
		$this->from = Cast::string($data, 'from');
		$this->txHash = Cast::nullableString($data, 'txHash');
		$this->customerUuid = Cast::nullableString($data, 'customerUuid');
		$this->productUuid = Cast::nullableString($data, 'productUuid');
		$this->createdAt = Cast::date($data, 'createdAt');
		$this->customer = Cast::nullableObject($data, 'customer', CustomerSummary::fromArray(...));
		$this->test = Cast::bool($data, 'test');
		$this->userUuid = Cast::nullableString($data, 'userUuid');
	}
}
