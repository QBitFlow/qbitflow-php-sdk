<?php

declare(strict_types=1);

namespace QBitFlow\Models;

use DateTimeImmutable;
use QBitFlow\Support\Cast;

/**
 * A customer of a space.
 */
final readonly class Customer extends Model
{
	/**
	 * The customer's id.
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
	 * The email address, lowercase. Several customers of a space may share one.
	 */
	public string $email;

	/**
	 * True for a customer the merchant created, false for one created from the email a payer typed at checkout.
	 */
	public bool $verified;

	/**
	 * The phone number, when given.
	 */
	public ?string $phoneNumber;

	/**
	 * The physical address, when given.
	 */
	public ?string $address;

	/**
	 * The merchant's reference, when given.
	 */
	public ?string $reference;

	/**
	 * When it was created.
	 */
	public DateTimeImmutable $createdAt;

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
		$this->lastName = Cast::nullableString($data, 'lastName');
		$this->email = Cast::string($data, 'email');
		$this->verified = Cast::bool($data, 'verified');
		$this->phoneNumber = Cast::nullableString($data, 'phoneNumber');
		$this->address = Cast::nullableString($data, 'address');
		$this->reference = Cast::nullableString($data, 'reference');
		$this->createdAt = Cast::date($data, 'createdAt');
		$this->test = Cast::bool($data, 'test');
		$this->userUuid = Cast::nullableString($data, 'userUuid');
	}
}
