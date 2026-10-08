<?php

declare(strict_types=1);

namespace QBitFlow\Dto;

use QBitFlow\Exceptions\ValidationException;
use QBitFlow\Support\Dto;

/**
 * Payload for updating a user.
 *
 * Every field is optional and the update is partial: omitted fields are left untouched,
 * since `null` values are never serialized. An empty payload is a valid no-op.
 *
 * The password is deliberately absent. Changing a password is a JWT-only, self-service
 * operation on the API — it cannot be done with an API key, which is the only credential
 * this SDK uses. A password sent with an API key is silently ignored by the API (the
 * request still returns 200), so exposing it here would be misleading. Change passwords
 * from the QBitFlow dashboard instead.
 *
 * ```php
 * $user = $client->users->update($id, new UpdateUserDto(name: 'Alicia'));
 * ```
 */
final class UpdateUserDto extends Dto
{
	public function __construct(
		/** First name. Optional, 2–100 characters. */
		public readonly ?string $name = null,
		/** Last name. Optional, 2–100 characters. */
		public readonly ?string $lastName = null,
		/** Email address. Optional, unique within the organization. */
		public readonly ?string $email = null,
		/**
		 * Organization fee in basis points, 0–5000 (0%–50%).
		 *
		 * Requires admin authority — an admin/owner key, or an organization-level key
		 * acting via `onBehalfOf()`. A non-admin caller that sends this field is rejected
		 * with 403. Leave it null to omit it entirely.
		 */
		public readonly ?int $organizationFeeBps = null,
	) {
		if ($name !== null && (mb_strlen($name) < 2 || mb_strlen($name) > 100)) {
			throw new ValidationException('Name must be between 2 and 100 characters');
		}

		if ($lastName !== null && (mb_strlen($lastName) < 2 || mb_strlen($lastName) > 100)) {
			throw new ValidationException('Last name must be between 2 and 100 characters');
		}

		if ($organizationFeeBps !== null && ($organizationFeeBps < 0 || $organizationFeeBps > 5000)) {
			throw new ValidationException('organizationFeeBps must be between 0 and 5000');
		}
	}

	/**
	 * @param array<string,mixed> $data
	 */
	public static function fromArray(array $data): self
	{
		return new self(
			isset($data['name']) ? (string) $data['name'] : null,
			isset($data['lastName']) ? (string) $data['lastName'] : null,
			isset($data['email']) ? (string) $data['email'] : null,
			isset($data['organizationFeeBps']) ? (int) $data['organizationFeeBps'] : null,
		);
	}
}
