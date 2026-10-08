<?php

declare(strict_types=1);

namespace QBitFlow\Params;

use QBitFlow\Support\Validator;

/**
 * Updates a customer (`customers->update()`): fields left `null` are unchanged. The reference
 * cannot be changed.
 */
final readonly class UpdateCustomerParams
{
	public function __construct(
		/** The new first name (null or `''`: unchanged). */
		public ?string $name = null,
		/** The new last name (null or `''`: unchanged). */
		public ?string $lastName = null,
		/** The new email (null or `''`: unchanged). */
		public ?string $email = null,
		/** The new phone number; `''` clears it, null leaves it unchanged. */
		public ?string $phoneNumber = null,
		/** The new address; `''` clears it, null leaves it unchanged. */
		public ?string $address = null,
	) {
	}

	/** @throws \QBitFlow\Exceptions\ValidationException */
	public function validate(): void
	{
		$v = new Validator();
		$v->name('name', $this->name, 2, 100);
		$v->name('lastName', $this->lastName, 1, 100);
		$v->email('email', $this->email);
		$v->phone('phoneNumber', $this->phoneNumber);
		if ($this->address !== null && $this->address !== '') {
			$v->text('address', $this->address, 0, 500);
		}
		$v->throwIfAny();
	}

	/** @return array<string,mixed> */
	public function toArray(): array
	{
		$out = [];
		foreach (['name' => $this->name, 'lastName' => $this->lastName, 'email' => $this->email] as $key => $value) {
			if ($value !== null && $value !== '') {
				$out[$key] = $value;
			}
		}
		// The clearable fields: '' is sent, and clears them.
		if ($this->phoneNumber !== null) {
			$out['phoneNumber'] = $this->phoneNumber;
		}
		if ($this->address !== null) {
			$out['address'] = $this->address;
		}

		return $out;
	}
}
