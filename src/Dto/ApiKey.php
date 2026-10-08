<?php

declare(strict_types=1);

namespace QBitFlow\Dto;

use DateTimeImmutable;
use QBitFlow\Enums\UserRole;
use QBitFlow\Support\Cast;
use QBitFlow\Support\Dto;

/**
 * An API key belonging to a user in your organization.
 *
 * Keys are created and deleted from the dashboard only — those operations require a
 * JWT session and cannot be performed with an API key.
 */
final class ApiKey extends Dto
{
	public function __construct(
		/** Unique identifier for the key. */
		public readonly int $id,
		/** Descriptive name for the key. */
		public readonly string $name,
		/** Organization the key belongs to. */
		public readonly int $organizationId,
		/** User the key belongs to. */
		public readonly int $userId,
		/** When the key was created. */
		public readonly DateTimeImmutable $createdAt,
		/** Role the key operates with. */
		public readonly UserRole $role,
		/** Whether this is a test-mode key. */
		public readonly bool $test,
		/** When the key expires; null when it never expires. */
		public readonly ?DateTimeImmutable $expiresAt = null,
	) {
	}

	/**
	 * @param array<string,mixed> $data
	 */
	public static function fromArray(array $data): self
	{
		return new self(
			Cast::int($data, 'id'),
			Cast::string($data, 'name'),
			Cast::int($data, 'organizationId'),
			Cast::int($data, 'userId'),
			Cast::date($data, 'createdAt'),
			Cast::enum($data, 'role', UserRole::class, UserRole::USER),
			Cast::bool($data, 'test'),
			Cast::nullableDate($data, 'expiresAt'),
		);
	}

	/** Whether the key has an expiry date that has already passed. */
	public function isExpired(): bool
	{
		return $this->expiresAt !== null && $this->expiresAt < new DateTimeImmutable();
	}
}
