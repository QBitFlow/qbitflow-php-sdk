<?php

declare(strict_types=1);

namespace QBitFlow\Dto;

use QBitFlow\Enums\UserRole;
use QBitFlow\Exceptions\ValidationException;
use QBitFlow\Support\Dto;

/**
 * Payload for creating a user. Requires an admin or owner API key.
 *
 * No password is set here: provisioned users set their own password and connect a
 * wallet through the claim flow — see {@see \QBitFlow\Requests\ClaimRequests::createRequest()}.
 */
final class CreateUserDto extends Dto
{
	public function __construct(
		/** First name. Required, 2–100 characters. */
		public readonly string $name,
		/** Last name. Required, 2–100 characters. */
		public readonly string $lastName,
		/** Email address. Required. */
		public readonly string $email,
		/** Role within the organization. */
		public readonly UserRole $role = UserRole::USER,
		/** Organization fee in basis points, 0–5000 (0%–50%). */
		public readonly int $organizationFeeBps = 0,
	) {
		if ($organizationFeeBps < 0 || $organizationFeeBps > 5000) {
			throw new ValidationException('organizationFeeBps must be between 0 and 5000');
		}
	}

	/**
	 * @param array<string,mixed> $data
	 */
	public static function fromArray(array $data): self
	{
		$role = $data['role'] ?? null;

		return new self(
			(string) ($data['name'] ?? ''),
			(string) ($data['lastName'] ?? ''),
			(string) ($data['email'] ?? ''),
			$role instanceof UserRole ? $role : (is_string($role) ? UserRole::from($role) : UserRole::USER),
			(int) ($data['organizationFeeBps'] ?? 0),
		);
	}
}
