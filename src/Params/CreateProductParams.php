<?php

declare(strict_types=1);

namespace QBitFlow\Params;

use QBitFlow\Support\Validator;

/**
 * Creates a product (`products->create()`).
 */
final readonly class CreateProductParams
{
	/**
	 * Pass the arguments by name; an optional one left null is not sent. Nothing is checked
	 * here: the service method checks the values before sending (a
	 * {@see \QBitFlow\Exceptions\ValidationException} naming each bad one, nothing sent).
	 *
	 * @param array<string,string>|null $metadata
	 */
	public function __construct(
		/** Required: 2 to 100 characters, one line. */
		public string $name,
		/**
		 * Required: the price in USD, a decimal string (`"12.50"`): above 0, at most 2 decimals, at
		 * most 1,000,000. The API also caps it at `"5"` in test mode (400 with `details.max` `"5"`)
		 * and requires more than `"0.25"` for a live subscription product (`details.min`).
		 */
		public string $priceUsd,
		/** Optional: 2 to 500 characters. */
		public ?string $description = null,
		/**
		 * Your reference, unique per space and mode (1 to 100 of `A-Z a-z 0-9 . _ : @ -`, never `.` or `..` alone); the
		 * product's uuid when not set.
		 */
		public ?string $reference = null,
		/** Makes it a subscription product (`frequency` required); null: a one-time product. */
		public ?SubscriptionTermsParams $subscription = null,
		/**
		 * Your own data on the product (an id in your catalog): at most 50 keys of 1 to 40
		 * characters among `A-Z a-z 0-9 _ - . :`, values of at most 500 characters without control
		 * or bidirectional characters. A key set to `''` is not stored. Never put secrets or
		 * personal data in it.
		 */
		public ?array $metadata = null,
	) {
	}

	/** @throws \QBitFlow\Exceptions\ValidationException */
	public function validate(): void
	{
		$v = new Validator();
		if ($v->required('name', $this->name)) {
			$v->name('name', $this->name, 2, 100);
		}
		$v->text('description', $this->description, 2, 500);
		if ($v->required('priceUsd', $this->priceUsd)) {
			$v->usd('priceUsd', $this->priceUsd);
		}
		$v->reference('reference', $this->reference);
		$this->subscription?->check($v, 'subscription.', true);
		$v->metadata('metadata', $this->metadata);
		$v->throwIfAny();
	}

	/** @return array<string,mixed> */
	public function toArray(): array
	{
		$out = ['name' => $this->name];
		if ($this->description !== null && $this->description !== '') {
			$out['description'] = $this->description;
		}
		$out['priceUsd'] = $this->priceUsd;
		if ($this->reference !== null && $this->reference !== '') {
			$out['reference'] = $this->reference;
		}
		if ($this->subscription !== null) {
			$out['subscription'] = $this->subscription->toObject();
		}
		if ($this->metadata !== null && $this->metadata !== []) {
			$out['metadata'] = (object) $this->metadata;
		}

		return $out;
	}
}
