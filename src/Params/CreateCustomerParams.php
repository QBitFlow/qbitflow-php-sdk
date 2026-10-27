<?php

declare(strict_types=1);

namespace QBitFlow\Params;

use QBitFlow\Support\Validator;

/**
 * Creates a customer (`customers->create()`).
 */
final readonly class CreateCustomerParams
{
	/**
	 * Pass the arguments by name; an optional one left null is not sent. Nothing is checked
	 * here: the service method checks the values before sending (a
	 * {@see \QBitFlow\Exceptions\ValidationException} naming each bad one, nothing sent).
	 *
	 * @param array<string,string>|null $metadata
	 */
	public function __construct(
		/** Required: the first name, 2 to 100 characters, one line. */
		public string $firstName,
		/**
		 * Required (stored lowercase), at most 254 characters. Not unique: several customers of a
		 * space may share one.
		 */
		public string $email,
		/** Optional: 1 to 100 characters, one line. */
		public ?string $lastName = null,
		/** Optional: at most 32 characters, digits and `. - ( )` space, an optional `+`. */
		public ?string $phoneNumber = null,
		/** Optional: at most 500 characters. */
		public ?string $address = null,
		/**
		 * Your reference (e.g. your own customer id), unique per space and mode: 1 to 100 of
		 * `A-Z a-z 0-9 . _ : @ -`.
		 */
		public ?string $reference = null,
		/**
		 * Your own data on the customer (a CRM key): at most 50 keys of 1 to 40 characters among
		 * `A-Z a-z 0-9 _ - . :`, values of at most 500 characters without control or
		 * bidirectional characters. A key set to `''` is not stored.
		 */
		public ?array $metadata = null,
	) {
	}

	/** @throws \QBitFlow\Exceptions\ValidationException */
	public function validate(): void
	{
		$v = new Validator();
		if ($v->required('firstName', $this->firstName)) {
			$v->name('firstName', $this->firstName, 2, 100);
		}
		$v->name('lastName', $this->lastName, 1, 100);
		if ($v->required('email', $this->email)) {
			$v->email('email', $this->email);
		}
		$v->phone('phoneNumber', $this->phoneNumber);
		$v->text('address', $this->address, 0, 500);
		$v->reference('reference', $this->reference);
		$v->metadata('metadata', $this->metadata);
		$v->throwIfAny();
	}

	/** @return array<string,mixed> */
	public function toArray(): array
	{
		$out = array_filter([
			'firstName' => $this->firstName,
			'lastName' => $this->lastName,
			'email' => $this->email,
			'phoneNumber' => $this->phoneNumber,
			'address' => $this->address,
			'reference' => $this->reference,
		], static fn (?string $value): bool => $value !== null && $value !== '');
		if ($this->metadata !== null && $this->metadata !== []) {
			$out['metadata'] = (object) $this->metadata;
		}

		return $out;
	}
}
