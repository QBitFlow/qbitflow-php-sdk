<?php

declare(strict_types=1);

namespace QBitFlow\Params;

use QBitFlow\Support\Validator;

/**
 * Updates a product (`products->update()`): fields left `null` are unchanged. The reference
 * cannot change.
 */
final readonly class UpdateProductParams
{
	/**
	 * @param array<string,string>|null $metadata
	 */
	public function __construct(
		/** The new name, 2 to 100 characters (null or `''`: unchanged). */
		public ?string $name = null,
		/** The new description (2 to 500 characters); `''` clears it, null leaves it unchanged. */
		public ?string $description = null,
		/**
		 * The new price in USD, a decimal string as at creation (null or `''`: unchanged): for new
		 * checkouts and subscribers only (a subscription keeps the price it was subscribed at).
		 */
		public ?string $priceUsd = null,
		/** False hides the product from `products->list()`, true lists it again. */
		public ?bool $isActive = null,
		/**
		 * Sets (or changes) the subscription terms, field by field, for new subscribers only; a
		 * one-time product needs `frequency`.
		 */
		public ?SubscriptionTermsParams $subscription = null,
		/** Makes it a one-time product again (not with `subscription`). */
		public bool $removeSubscription = false,
		/**
		 * Merged into the product's: a key set to a value sets it, a key set to `''` deletes it,
		 * keys not named are kept (null: unchanged). At most 50 keys once merged.
		 */
		public ?array $metadata = null,
	) {
	}

	/** @throws \QBitFlow\Exceptions\ValidationException */
	public function validate(): void
	{
		$v = new Validator();
		$v->name('name', $this->name, 2, 100);
		$v->text('description', $this->description, 2, 500);
		if ($this->priceUsd !== null && $this->priceUsd !== '') {
			$v->usd('priceUsd', $this->priceUsd);
		}
		$this->subscription?->check($v, 'subscription.', false);
		$v->exclusive('subscription', $this->subscription !== null, 'removeSubscription', $this->removeSubscription);
		$v->metadata('metadata', $this->metadata);
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
			$out['description'] = $this->description; // '' is sent: it clears the description
		}
		if ($this->priceUsd !== null && $this->priceUsd !== '') {
			$out['priceUsd'] = $this->priceUsd;
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
		if ($this->metadata !== null && $this->metadata !== []) {
			$out['metadata'] = (object) $this->metadata;
		}

		return $out;
	}
}
