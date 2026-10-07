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
		/** Unique identifier for the customer (a bare UUID). */
		public readonly string $uuid,
		/** First name. */
		public readonly string $name,
		/** Last name. */
		public readonly string $lastName,
		/** Email address. */
		public readonly string $email,
		/** When the customer was created. */
		public readonly DateTimeImmutable $createdAt,
		/** Owning organization ID. */
		public readonly int $organizationId,
		/** Owning user ID; `0` for organization-level customers. */
		public readonly int $userId = 0,
		/** Phone number; `''` when none was given. */
		public readonly string $phoneNumber = '',
		/** Postal address; `''` when none was given. */
		public readonly string $address = '',
		/** Your own reference for this customer; `''` when none was given. */
		public readonly string $reference = '',
		/** Whether this is a test-mode customer (test and live sets are isolated). */
		public readonly bool $test = false,
	) {
	}

	/**
	 * @param array<array-key,mixed> $data
	 */
	public static function fromArray(array $data): self
	{
		return new self(
			Cast::string($data, 'uuid'),
			Cast::string($data, 'name'),
			Cast::string($data, 'lastName'),
			Cast::string($data, 'email'),
			Cast::date($data, 'createdAt'),
			Cast::int($data, 'organizationId'),
			Cast::int($data, 'userId'),
			Cast::string($data, 'phoneNumber'),
			Cast::string($data, 'address'),
			Cast::string($data, 'reference'),
			Cast::bool($data, 'test'),
		);
	}

	/** Convenience accessor for the customer's full name. */
	public function fullName(): string
	{
		return trim($this->name . ' ' . $this->lastName);
	}
}
