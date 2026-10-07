<?php

declare(strict_types=1);

namespace QBitFlow\Dto;

use QBitFlow\Exceptions\ValidationException;
use QBitFlow\Support\Dto;
use QBitFlow\Support\Validate;

/**
 * Payload for updating a user.
 *
 * Every field is optional and the update is partial: omitted fields are left untouched,
 * since `null` values are never serialized. An empty string also means "not provided".
 * An empty payload is a valid no-op.
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
	/** First name. 2–100 characters, letters/digits/spaces/`- _ ' .` only. */
	public readonly ?string $name;

	/** Last name. Same rules as `name`. */
	public readonly ?string $lastName;

	/** Email address. Must stay unique within the organization. */
	public readonly ?string $email;

	/**
	 * Organization fee in basis points, 0–5000 (0%–50%).
	 *
	 * Requires admin authority — an admin/owner key, or an organization-level key
	 * acting via `onBehalfOf()`. A non-admin caller that sends this field is rejected
	 * with 403. Leave it null to omit it entirely.
	 */
	public readonly ?int $organizationFeeBps;

	/**
	 * @throws ValidationException When a provided field breaks the API's rules
	 */
	public function __construct(
		?string $name = null,
		?string $lastName = null,
		?string $email = null,
		?int $organizationFeeBps = null,
	) {
		$this->name = self::optional($name);
		$this->lastName = self::optional($lastName);
		$this->email = self::optional($email);
		$this->organizationFeeBps = $organizationFeeBps;

		Validate::alphanumSpace('name', $this->name, 2, 100);
		Validate::alphanumSpace('lastName', $this->lastName, 2, 100);
		Validate::email('email', $this->email);

		if ($organizationFeeBps !== null) {
			Validate::intRange('organizationFeeBps', $organizationFeeBps, 0, 5000);
		}
	}

	/**
	 * @param array<array-key,mixed> $data
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
