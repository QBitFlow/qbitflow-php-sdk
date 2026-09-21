<?php

declare(strict_types=1);

namespace QBitFlow\Dto;

use QBitFlow\Support\Dto;

/**
 * Payload for updating a customer. Every field is optional — omitted fields are
 * left untouched, since `null` values are never serialized.
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
	public function __construct(
		public readonly ?string $name = null,
		public readonly ?string $lastName = null,
		public readonly ?string $email = null,
		public readonly ?string $phoneNumber = null,
		public readonly ?string $address = null,
	) {
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
			isset($data['phoneNumber']) ? (string) $data['phoneNumber'] : null,
			isset($data['address']) ? (string) $data['address'] : null,
		);
	}
}
