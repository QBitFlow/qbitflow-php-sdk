<?php

declare(strict_types=1);

namespace QBitFlow\Dto;

use QBitFlow\Enums\UserRole;
use QBitFlow\Exceptions\ValidationException;
use QBitFlow\Support\Dto;
use QBitFlow\Support\Validate;

/**
 * Payload for creating a user. Requires an admin or owner API key.
 *
 * No password is set here: provisioned users set their own password and connect a
 * wallet through the claim flow — see {@see \QBitFlow\Requests\ClaimRequests::createRequest()}.
 *
 * Only `UserRole::ADMIN` and `UserRole::USER` can be assigned (the API binds the field
 * `oneof=admin user`); `OWNER` and `HANDLE` are read-only and rejected here.
 */
final class CreateUserDto extends Dto
{
	/**
	 * @throws ValidationException When a field breaks the API's rules
	 */
	public function __construct(
		/** First name. Required, 2–100 characters, letters/digits/spaces/`- _ ' .` only. */
		public readonly string $name,
		/** Last name. Required, same rules as `name`. */
		public readonly string $lastName,
		/** Email address. Required, unique within the organization. */
		public readonly string $email,
		/** Role within the organization: `ADMIN` or `USER`. */
		public readonly UserRole $role = UserRole::USER,
		/** Organization fee in basis points, 0–5000 (0%–50%). */
		public readonly int $organizationFeeBps = 0,
	) {
		Validate::required('name', $name);
		Validate::required('lastName', $lastName);
		Validate::required('email', $email);
		Validate::alphanumSpace('name', $name, 2, 100);
		Validate::alphanumSpace('lastName', $lastName, 2, 100);
		Validate::email('email', $email);

		Validate::intRange('organizationFeeBps', $organizationFeeBps, 0, 5000);

		if (! $role->isAssignableOnCreate()) {
			throw new ValidationException("role must be 'admin' or 'user' when creating a user");
		}
	}

	/**
	 * @param array<array-key,mixed> $data
	 *
	 * @throws ValidationException When `role` is not a known role
	 */
	public static function fromArray(array $data): self
	{
		$role = $data['role'] ?? null;

		if (is_string($role)) {
			$role = UserRole::tryFrom($role) ?? throw new ValidationException(sprintf(
				'Invalid role "%s". Expected one of: %s',
				$role,
				implode(', ', array_column(UserRole::cases(), 'value')),
			));
		}

		return new self(
			(string) ($data['name'] ?? ''),
			(string) ($data['lastName'] ?? ''),
			(string) ($data['email'] ?? ''),
			$role instanceof UserRole ? $role : UserRole::USER,
			(int) ($data['organizationFeeBps'] ?? 0),
		);
	}
}
