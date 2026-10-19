<?php

declare(strict_types=1);

namespace QBitFlow\Params;

use QBitFlow\Support\Validator;

/**
 * Updates a customer (`customers->update()`): fields left `null` are unchanged. `firstName` and
 * `email` cannot be cleared; `lastName`, `phoneNumber` and `address` are cleared with `''`. The
 * reference cannot change.
 */
final readonly class UpdateCustomerParams
{
	/**
	 * @param array<string,string>|null $metadata
	 */
	public function __construct(
		/** The new first name, 2 to 100 characters (null or `''`: unchanged). */
		public ?string $firstName = null,
		/** The new last name (1 to 100 characters); `''` clears it, null leaves it unchanged. */
		public ?string $lastName = null,
		/** The new email (null or `''`: unchanged). */
		public ?string $email = null,
		/** The new phone number; `''` clears it, null leaves it unchanged. */
		public ?string $phoneNumber = null,
		/** The new address; `''` clears it, null leaves it unchanged. */
		public ?string $address = null,
		/**
		 * Merged into the customer's: a key set to a value sets it, a key set to `''` deletes it,
		 * keys not named are kept (null: unchanged). At most 50 keys once merged.
		 */
		public ?array $metadata = null,
	) {
	}

	/** @throws \QBitFlow\Exceptions\ValidationException */
	public function validate(): void
	{
		$v = new Validator();
		$v->name('firstName', $this->firstName, 2, 100);
		$v->name('lastName', $this->lastName, 1, 100);
		$v->email('email', $this->email);
		$v->phone('phoneNumber', $this->phoneNumber);
		$v->text('address', $this->address, 0, 500);
		$v->metadata('metadata', $this->metadata);
		$v->throwIfAny();
	}

	/** @return array<string,mixed> */
	public function toArray(): array
	{
		$out = [];
		foreach (['firstName' => $this->firstName, 'email' => $this->email] as $key => $value) {
			if ($value !== null && $value !== '') {
				$out[$key] = $value;
			}
		}
		// The clearable fields: '' is sent, and clears them.
		foreach (['lastName' => $this->lastName, 'phoneNumber' => $this->phoneNumber, 'address' => $this->address] as $key => $value) {
			if ($value !== null) {
				$out[$key] = $value;
			}
		}
		if ($this->metadata !== null && $this->metadata !== []) {
			$out['metadata'] = (object) $this->metadata;
		}

		return $out;
	}
}
