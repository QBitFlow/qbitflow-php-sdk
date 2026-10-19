<?php

declare(strict_types=1);

namespace QBitFlow\Models;

use QBitFlow\Support\Cast;

/**
 * A customer as a transaction row names it (a payment, a bill, a subscription, a refund): who
 * paid, even once the merchant deleted the customer. Merchant API reads only: absent from
 * webhooks.
 */
final readonly class CustomerSummary extends Model
{
	/**
	 * The customer (`customers->get()`).
	 */
	public string $uuid;

	/**
	 * The first name.
	 */
	public string $firstName;

	/**
	 * The last name; null (absent) when not given.
	 */
	public ?string $lastName;

	/**
	 * The email, lowercase.
	 */
	public string $email;

	/**
	 * The merchant's reference of the customer; null (absent) without one.
	 */
	public ?string $reference;

	/**
	 * True when the merchant deleted the customer: it still names its rows, but
	 * `customers->get()` answers 404.
	 */
	public bool $deleted;

	/**
	 * @param array<string,mixed> $data
	 */
	protected function __construct(array $data)
	{
		$this->uuid = Cast::string($data, 'uuid');
		$this->firstName = Cast::string($data, 'firstName');
		$this->lastName = Cast::nullableString($data, 'lastName');
		$this->email = Cast::string($data, 'email');
		$this->reference = Cast::nullableString($data, 'reference');
		$this->deleted = Cast::bool($data, 'deleted');
	}
}
