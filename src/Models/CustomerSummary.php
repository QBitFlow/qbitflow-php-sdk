<?php

declare(strict_types=1);

namespace QBitFlow\Models;

use QBitFlow\Support\Cast;

/**
 * A customer as a payment, bill or refund names it (merchant API reads only; absent from webhooks).
 */
final readonly class CustomerSummary extends Model
{
	/**
	 * The customer (`$client->customers->get()`).
	 */
	public string $uuid;

	/**
	 * The first name.
	 */
	public string $name;

	/**
	 * The last name, when given.
	 */
	public ?string $lastName;

	/**
	 * The email, lowercase.
	 */
	public string $email;

	/**
	 * The merchant's reference of the customer, when it has one.
	 */
	public ?string $reference;

	/**
	 * True when the merchant deleted the customer (it still names its rows).
	 */
	public ?bool $deleted;

	/**
	 * @param array<string,mixed> $data
	 */
	protected function __construct(array $data)
	{
		$this->uuid = Cast::string($data, 'uuid');
		$this->name = Cast::string($data, 'name');
		$this->lastName = Cast::nullableString($data, 'lastName');
		$this->email = Cast::string($data, 'email');
		$this->reference = Cast::nullableString($data, 'reference');
		$this->deleted = Cast::nullableBool($data, 'deleted');
	}
}
