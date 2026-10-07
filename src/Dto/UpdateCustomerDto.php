<?php

declare(strict_types=1);

namespace QBitFlow\Dto;

use QBitFlow\Exceptions\ValidationException;
use QBitFlow\Support\Dto;
use QBitFlow\Support\Validate;

/**
 * Payload for updating a customer. Every field is optional and the update is partial:
 * omitted fields are left untouched, since `null` values are never serialized. An empty
 * string also means "not provided" and leaves the field unchanged.
 *
 * `reference` is deliberately absent — a customer reference is immutable and the API
 * ignores it on update.
 *
 * ```php
 * $customer = $client->customers->update($uuid, new UpdateCustomerDto(
 *     email: 'new@example.com',
 *     phoneNumber: '+9876543210',
 * ));
 * ```
 */
final class UpdateCustomerDto extends Dto
{
	/** First name. 2–100 characters, letters/digits/spaces/`- _ ' .` only. */
	public readonly ?string $name;

	/** Last name. Same rules as `name`. */
	public readonly ?string $lastName;

	/** Email address. Must stay unique per (organization, user). */
	public readonly ?string $email;

	public readonly ?string $phoneNumber;

	public readonly ?string $address;

	/**
	 * @throws ValidationException When a provided field breaks the API's rules
	 */
	public function __construct(
		?string $name = null,
		?string $lastName = null,
		?string $email = null,
		?string $phoneNumber = null,
		?string $address = null,
	) {
		$this->name = self::optional($name);
		$this->lastName = self::optional($lastName);
		$this->email = self::optional($email);
		$this->phoneNumber = self::optional($phoneNumber);
		$this->address = self::optional($address);

		Validate::alphanumSpace('name', $this->name, 2, 100);
		Validate::alphanumSpace('lastName', $this->lastName, 2, 100);
		Validate::email('email', $this->email);
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
			isset($data['phoneNumber']) ? (string) $data['phoneNumber'] : null,
			isset($data['address']) ? (string) $data['address'] : null,
		);
	}
}
