<?php

declare(strict_types=1);

namespace QBitFlow\Dto;

use DateTimeImmutable;
use QBitFlow\Enums\UserRole;
use QBitFlow\Support\Cast;
use QBitFlow\Support\Dto;

/**
 * A member of your organization who can receive payments to their own wallet.
 */
final class User extends Dto
{
	public function __construct(
		/** Unique identifier for the user. */
		public readonly int $id,
		/** First name. */
		public readonly string $name,
		/** Last name. */
		public readonly string $lastName,
		/** Email address. */
		public readonly string $email,
		/** When the user was created. */
		public readonly DateTimeImmutable $createdAt,
		/** When the user was last updated. */
		public readonly DateTimeImmutable $updatedAt,
		/** Organization the user belongs to. */
		public readonly int $organizationId,
		/** Role within the organization. */
		public readonly UserRole $role,
		/** Organization fee applied to this user's transactions, in basis points (100 = 1%). */
		public readonly int $organizationFeeBps,
		/** Set once an invited user has claimed their account. */
		public readonly ?DateTimeImmutable $claimedAt = null,
	) {
	}

	/**
	 * @param array<string,mixed> $data
	 */
	public static function fromArray(array $data): self
	{
		// The API has shipped both `updatedAt` and `updateAt` for this field.
		$updatedAt = Cast::nullableDate($data, 'updatedAt')
			?? Cast::date($data, 'updateAt');

		return new self(
			Cast::int($data, 'id'),
			Cast::string($data, 'name'),
			Cast::string($data, 'lastName'),
			Cast::string($data, 'email'),
			Cast::date($data, 'createdAt'),
			$updatedAt,
			Cast::int($data, 'organizationId'),
			Cast::enum($data, 'role', UserRole::class, UserRole::USER),
			Cast::int($data, 'organizationFeeBps'),
			Cast::nullableDate($data, 'claimedAt'),
		);
	}

	/** Whether this user has claimed their account and connected a wallet. */
	public function hasClaimedAccount(): bool
	{
		return $this->claimedAt !== null;
	}
}
