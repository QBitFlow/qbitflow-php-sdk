<?php

declare(strict_types=1);

namespace QBitFlow\Params;

use QBitFlow\Support\Validator;

/**
 * Creates a product (`products->create()`).
 */
final readonly class CreateProductParams
{
	public function __construct(
		/** Required: 2 to 100 characters, one line. */
		public string $name,
		/** Required: in USD, above 0 (at most 5 in test mode). */
		public float $price,
		/** Optional: 2 to 500 characters. */
		public ?string $description = null,
		/** Your reference, unique per space; generated when not set. */
		public ?string $reference = null,
		/** Makes it a subscription product (`frequency` required). */
		public ?SubscriptionTermsParams $subscription = null,
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
		$v->price('price', $this->price);
		$v->reference('reference', $this->reference);
		$this->subscription?->check($v, 'subscription.', true);
		$v->throwIfAny();
	}

	/** @return array<string,mixed> */
	public function toArray(): array
	{
		$out = ['name' => $this->name];
		if ($this->description !== null && $this->description !== '') {
			$out['description'] = $this->description;
		}
		$out['price'] = $this->price;
		if ($this->reference !== null && $this->reference !== '') {
			$out['reference'] = $this->reference;
		}
		if ($this->subscription !== null) {
			$out['subscription'] = $this->subscription->toObject();
		}

		return $out;
	}
}
