<?php

declare(strict_types=1);

namespace QBitFlow\Params;

use QBitFlow\Support\Validator;

/**
 * Creates a customer (`customers->create()`).
 */
final readonly class CreateCustomerParams
{
	public function __construct(
		/** Required: the first name, 2 to 100 characters, one line. */
		public string $name,
		/** Required (stored lowercase), at most 254 characters. */
		public string $email,
		/** Optional: 1 to 100 characters, one line. */
		public ?string $lastName = null,
		/** Optional: at most 32 characters, digits and `. - ( )` space, an optional `+`. */
		public ?string $phoneNumber = null,
		/** Optional: at most 500 characters. */
		public ?string $address = null,
		/** Your reference (e.g. your own customer id), unique per space. */
		public ?string $reference = null,
	) {
	}

	/** @throws \QBitFlow\Exceptions\ValidationException */
	public function validate(): void
	{
		$v = new Validator();
		if ($v->required('name', $this->name)) {
			$v->name('name', $this->name, 2, 100);
		}
		$v->name('lastName', $this->lastName, 1, 100);
		if ($v->required('email', $this->email)) {
			$v->email('email', $this->email);
		}
		$v->phone('phoneNumber', $this->phoneNumber);
		$v->text('address', $this->address, 0, 500);
		$v->reference('reference', $this->reference);
		$v->throwIfAny();
	}

	/** @return array<string,mixed> */
	public function toArray(): array
	{
		return array_filter([
			'name' => $this->name,
			'lastName' => $this->lastName,
			'email' => $this->email,
			'phoneNumber' => $this->phoneNumber,
			'address' => $this->address,
			'reference' => $this->reference,
		], static fn (?string $value): bool => $value !== null && $value !== '');
	}
}
