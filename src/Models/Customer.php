<?php

declare(strict_types=1);

namespace QBitFlow\Models;

use DateTimeImmutable;
use QBitFlow\Support\Cast;

/**
 * A merchant's customer: who pays, named on the payments and subscriptions it makes.
 */
final readonly class Customer extends Model
{
	/**
	 * The customer's id (a UUIDv7).
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
	 * The email address, lowercase (matched case-insensitively). Several customers of a space may
	 * share one: a checkout that named no customer creates one from the email its payer typed.
	 */
	public string $email;

	/**
	 * The phone number; null (absent) when not given.
	 */
	public ?string $phoneNumber;

	/**
	 * The physical address; null (absent) when not given.
	 */
	public ?string $address;

	/**
	 * The merchant's reference (its id in your system), unique per space; null (absent) without one.
	 */
	public ?string $reference;

	/**
	 * The merchant's own data on the customer (never copied onto its payments or subscriptions);
	 * `[]` without any.
	 *
	 * @var array<string,string>
	 */
	public array $metadata;

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
		$this->firstName = Cast::string($data, 'firstName');
		$this->lastName = Cast::nullableString($data, 'lastName');
		$this->email = Cast::string($data, 'email');
		$this->phoneNumber = Cast::nullableString($data, 'phoneNumber');
		$this->address = Cast::nullableString($data, 'address');
		$this->reference = Cast::nullableString($data, 'reference');
		$this->metadata = Cast::stringMap($data, 'metadata');
		$this->createdAt = Cast::date($data, 'createdAt');
		$this->test = Cast::bool($data, 'test');
		$this->userUuid = Cast::nullableString($data, 'userUuid');
	}
}
