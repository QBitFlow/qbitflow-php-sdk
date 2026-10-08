<?php

declare(strict_types=1);

namespace QBitFlow\Params;

use QBitFlow\Models\Duration;
use QBitFlow\Support\Validator;

/**
 * Opens a subscription checkout session (`checkoutSessions->createSubscription()`): the payment
 * session's fields, plus the terms, each optional over a subscription product's.
 */
final readonly class CreateSubscriptionSessionParams
{
	public function __construct(
		/** One of the space's products. */
		public ?string $productUuid = null,
		/** Names the product by its reference instead. */
		public ?string $productReference = null,
		/** An inline product's name (2 to 100 characters). */
		public ?string $productName = null,
		/** An inline product's description (2 to 500 characters), optional. */
		public ?string $description = null,
		/** An inline product's price per period in USD, above 0. */
		public ?float $price = null,
		/** Your reference for the subscription: unique per space. */
		public ?string $reference = null,
		/** Where the customer goes after subscribing. */
		public ?string $successUrl = null,
		/** Where the customer goes after a cancelled or failed checkout. */
		public ?string $cancelUrl = null,
		/** One of the space's customers. */
		public ?string $customerUuid = null,
		/** Your reference of the customer. */
		public ?string $customerReference = null,
		/** The session's lifetime, 10 to 1440 (null or 0: the default). */
		public ?int $expiresInMinutes = null,
		/** How often it bills: at least 1 unit, at most 1 year (the API also requires at least 1 hour live, 5 minutes test). */
		public ?Duration $frequency = null,
		/** A free trial before the first bill (`new Duration()`: none, removing a product's). */
		public ?Duration $trialPeriod = null,
		/** The periods the customer commits to before cancelling, at most 1000 (0: none). */
		public ?int $minPeriods = null,
	) {
	}

	/** @throws \QBitFlow\Exceptions\ValidationException */
	public function validate(): void
	{
		$v = new Validator();
		CreatePaymentSessionParams::check($v, $this->productUuid, $this->productReference, $this->productName, $this->description,
			$this->price, $this->reference, $this->successUrl, $this->cancelUrl, $this->customerUuid, $this->customerReference,
			$this->expiresInMinutes);
		$this->terms()->check($v, '', false);
		$v->throwIfAny();
	}

	/** @return array<string,mixed> */
	public function toArray(): array
	{
		return CreatePaymentSessionParams::body($this->productUuid, $this->productReference, $this->productName,
			$this->description, $this->price, $this->reference, $this->successUrl, $this->cancelUrl, $this->customerUuid,
			$this->customerReference, $this->expiresInMinutes) + $this->terms()->toArray();
	}

	private function terms(): SubscriptionTermsParams
	{
		return new SubscriptionTermsParams($this->frequency, $this->trialPeriod, $this->minPeriods);
	}
}
