<?php

declare(strict_types=1);

namespace QBitFlow\Dto;

use DateTimeImmutable;
use QBitFlow\Support\Cast;
use QBitFlow\Support\Dto;

/**
 * A customer: an individual or entity that pays through your platform.
 */
final class Customer extends Dto
{
	public function __construct(
		/** Unique identifier for the customer. */
		public readonly string $uuid,
		/** First name. */
		public readonly string $name,
		/** Last name. */
		public readonly string $lastName,
		/** Email address. */
		public readonly string $email,
		/** When the customer was created. */
		public readonly DateTimeImmutable $createdAt,
		/** Phone number, when provided. */
		public readonly ?string $phoneNumber = null,
		/** Postal address, when provided. */
		public readonly ?string $address = null,
		/** Your own reference for this customer. */
		public readonly ?string $reference = null,
		/** Whether this is a test-mode customer (test and live sets are isolated). */
		public readonly bool $test = false,
		/** Owning organization ID. Returned only on authenticated reads. */
		public readonly ?int $organizationId = null,
		/** Owning user ID, `0` for organization-level customers. Authenticated reads only. */
		public readonly ?int $userId = null,
	) {
	}

	/**
	 * @param array<string,mixed> $data
	 */
	public static function fromArray(array $data): self
	{
		return new self(
			Cast::string($data, 'uuid'),
			Cast::string($data, 'name'),
			Cast::string($data, 'lastName'),
			Cast::string($data, 'email'),
			Cast::date($data, 'createdAt'),
			Cast::nullableString($data, 'phoneNumber'),
			Cast::nullableString($data, 'address'),
			Cast::nullableString($data, 'reference'),
			Cast::bool($data, 'test'),
			Cast::nullableInt($data, 'organizationId'),
			Cast::nullableInt($data, 'userId'),
		);
	}

	/** Convenience accessor for the customer's full name. */
	public function fullName(): string
	{
		return trim($this->name . ' ' . $this->lastName);
	}
}
