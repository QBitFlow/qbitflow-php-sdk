<?php

declare(strict_types=1);

namespace QBitFlow\Dto;

use QBitFlow\Exceptions\ValidationException;
use QBitFlow\Support\Dto;
use QBitFlow\Support\Validate;

/**
 * Payload for creating a customer.
 *
 * ```php
 * $customer = $client->customers->create(new CreateCustomerDto(
 *     name: 'John',
 *     lastName: 'Doe',
 *     email: 'john@example.com',
 *     reference: 'CRM-12345',
 * ));
 * ```
 *
 * `name` and `lastName` must be 2–100 characters of letters, digits, spaces and `- _ ' .`
 * (the API's `alphanumspace` rule); `email` must be a valid address. These are checked
 * here, before any request is sent.
 */
final class CreateCustomerDto extends Dto
{
	/** First name. Required, 2–100 characters, letters/digits/spaces/`- _ ' .` only. */
	public readonly string $name;

	/** Last name. Required, same rules as `name`. */
	public readonly string $lastName;

	/** Email address. Required, unique per (organization, user). */
	public readonly string $email;

	/** Phone number. Free-form; `''` counts as not provided. */
	public readonly ?string $phoneNumber;

	/** Postal address. Free-form; `''` counts as not provided. */
	public readonly ?string $address;

	/**
	 * Your own reference, so you can look the customer up without storing its UUID.
	 * Immutable; `''` counts as not provided.
	 */
	public readonly ?string $reference;

	/**
	 * @throws ValidationException When a field breaks the API's rules
	 */
	public function __construct(
		string $name,
		string $lastName,
		string $email,
		?string $phoneNumber = null,
		?string $address = null,
		?string $reference = null,
	) {
		Validate::required('name', $name);
		Validate::required('lastName', $lastName);
		Validate::required('email', $email);
		Validate::alphanumSpace('name', $name, 2, 100);
		Validate::alphanumSpace('lastName', $lastName, 2, 100);
		Validate::email('email', $email);

		$this->name = $name;
		$this->lastName = $lastName;
		$this->email = $email;
		$this->phoneNumber = self::optional($phoneNumber);
		$this->address = self::optional($address);
		$this->reference = self::optional($reference);
	}

	/**
	 * @param array<array-key,mixed> $data
	 */
	public static function fromArray(array $data): self
	{
		return new self(
			(string) ($data['name'] ?? ''),
			(string) ($data['lastName'] ?? ''),
			(string) ($data['email'] ?? ''),
			isset($data['phoneNumber']) ? (string) $data['phoneNumber'] : null,
			isset($data['address']) ? (string) $data['address'] : null,
			isset($data['reference']) ? (string) $data['reference'] : null,
		);
	}
}
