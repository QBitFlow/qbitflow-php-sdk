<?php

declare(strict_types=1);

namespace QBitFlow\Params;

use QBitFlow\Support\Validator;

/**
 * Updates a product (`products->update()`): fields left `null` are unchanged.
 */
final readonly class UpdateProductParams
{
	public function __construct(
		/** The new name (null or `''`: unchanged). */
		public ?string $name = null,
		/** The new description; `''` clears it, null leaves it unchanged. */
		public ?string $description = null,
		/** The new price in USD, above 0: for new checkouts and subscribers only. */
		public ?float $price = null,
		/** False hides the product from `products->list()`, true lists it again. */
		public ?bool $isActive = null,
		/** Sets (or changes) the subscription terms; a one-time product needs `frequency`. */
		public ?SubscriptionTermsParams $subscription = null,
		/** Makes it a one-time product (not with `subscription`). */
		public bool $removeSubscription = false,
	) {
	}

	/** @throws \QBitFlow\Exceptions\ValidationException */
	public function validate(): void
	{
		$v = new Validator();
		$v->name('name', $this->name, 2, 100);
		$v->text('description', $this->description, 2, 500);
		if ($this->price !== null) {
			$v->price('price', $this->price);
		}
		$this->subscription?->check($v, 'subscription.', false);
		$v->exclusive('subscription', $this->subscription !== null, 'removeSubscription', $this->removeSubscription);
		$v->throwIfAny();
	}

	/** @return array<string,mixed> */
	public function toArray(): array
	{
		$out = [];
		if ($this->name !== null && $this->name !== '') {
			$out['name'] = $this->name;
		}
		if ($this->description !== null) {
			$out['description'] = $this->description;
		}
		if ($this->price !== null) {
			$out['price'] = $this->price;
		}
		if ($this->isActive !== null) {
			$out['isActive'] = $this->isActive;
		}
		if ($this->subscription !== null) {
			$out['subscription'] = $this->subscription->toObject();
		}
		if ($this->removeSubscription) {
			$out['removeSubscription'] = true;
		}

		return $out;
	}
}
