<?php

declare(strict_types=1);

namespace QBitFlow\Dto;

use QBitFlow\Support\Dto;

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
 * The API requires `name` and `lastName` to be 2–100 characters and `email` to be valid.
 */
final class CreateCustomerDto extends Dto
{
	public function __construct(
		/** First name. Required, 2–100 characters. */
		public readonly string $name,
		/** Last name. Required, 2–100 characters. */
		public readonly string $lastName,
		/** Email address. Required. */
		public readonly string $email,
		/** Phone number. */
		public readonly ?string $phoneNumber = null,
		/** Postal address. */
		public readonly ?string $address = null,
		/** Your own reference, so you can look the customer up without storing its UUID. */
		public readonly ?string $reference = null,
	) {
	}

	/**
	 * @param array<string,mixed> $data
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
