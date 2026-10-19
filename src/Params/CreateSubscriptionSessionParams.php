<?php

declare(strict_types=1);

namespace QBitFlow\Params;

use QBitFlow\Models\Duration;
use QBitFlow\Support\Validator;

/**
 * Opens a subscription checkout session (`checkoutSessions->createSubscription()`): the payment
 * session's fields, plus the terms, each optional over a subscription product's. `fees` adds
 * lines to the price, as for a payment ({@see CheckoutFees}), locked with it: every bill charges
 * the price plus every line.
 */
final readonly class CreateSubscriptionSessionParams
{
	/**
	 * @param array<string,string>|null $metadata
	 */
	public function __construct(
		/** One of the space's products (a subscription product gives the default terms). */
		public ?string $productUuid = null,
		/** Names the product by its reference instead. */
		public ?string $productReference = null,
		/**
		 * An inline product's name (2 to 100 characters): the subscription uses a hidden product,
		 * shared by the space's inline products of the same name, description and price.
		 */
		public ?string $productName = null,
		/** An inline product's description (2 to 500 characters), optional. */
		public ?string $description = null,
		/**
		 * An inline product's price per period in USD, a decimal string: above 0, at most 2
		 * decimals, at most 1,000,000. The API caps it at `"5"` in test mode and requires more than
		 * `"0.25"` for a live subscription (inline or its product's; `details.min`).
		 */
		public ?string $priceUsd = null,
		/** Your reference for the subscription (an order or customer id), kept on it: unique per space. */
		public ?string $reference = null,
		/** Where the customer goes after subscribing (placeholders as for a payment). */
		public ?string $successUrl = null,
		/** Where the customer goes after a cancelled or failed checkout. */
		public ?string $cancelUrl = null,
		/** One of the space's customers. */
		public ?string $customerUuid = null,
		/** Your reference of the customer, kept on the subscription. */
		public ?string $customerReference = null,
		/** The session's lifetime, 10 to 1440 (null or 0: the default). */
		public ?int $expiresInMinutes = null,
		/** How often it bills: at least 1 unit, at most 1 year (the API also requires at least 1 hour live, 5 minutes test). */
		public ?Duration $frequency = null,
		/** A free trial before the first bill (`new Duration()`: none, removing a product's). */
		public ?Duration $trialPeriod = null,
		/** The periods the customer commits to before cancelling, at most 1000 (0: none). */
		public ?int $minPeriods = null,
		/**
		 * Amounts added to the price (a tax, shipping, QBitFlow's processing fee), shown to the
		 * customer line by line and locked with the price: every bill (the first one, at creation,
		 * and each next one) charges the price plus every line; null for none. The subscription
		 * keeps them as `Subscription::$fees`, its `priceUsd` staying the price. The maximum per
		 * period and the allowance the subscriber signs are sized on that amount, and QBitFlow's
		 * fee is taken on it. The test-mode cap (`"5"`) applies to that amount, fees included (400
		 * `validation_failed` on `fees`).
		 */
		public ?CheckoutFees $fees = null,
		/**
		 * Your own data on the checkout, copied onto the subscription (then changed with
		 * `subscriptions->update()`) and into its webhooks; the rules of
		 * {@see CreatePaymentSessionParams::$metadata}.
		 */
		public ?array $metadata = null,
	) {
	}

	/**
	 * Checks the params: the payment session's rules, the terms, then the fees.
	 *
	 * @throws \QBitFlow\Exceptions\ValidationException
	 */
	public function validate(): void
	{
		$v = new Validator();
		CreatePaymentSessionParams::check($v, $this->productUuid, $this->productReference, $this->productName, $this->description,
			$this->priceUsd, $this->reference, $this->successUrl, $this->cancelUrl, $this->customerUuid, $this->customerReference,
			$this->expiresInMinutes, $this->metadata);
		$this->terms()->check($v, '', false);
		$this->fees?->check($v);
		$v->throwIfAny();
	}

	/** @return array<string,mixed> */
	public function toArray(): array
	{
		$out = CreatePaymentSessionParams::body($this->productUuid, $this->productReference, $this->productName,
			$this->description, $this->priceUsd, $this->reference, $this->successUrl, $this->cancelUrl, $this->customerUuid,
			$this->customerReference, $this->expiresInMinutes, $this->metadata) + $this->terms()->toArray();
		if ($this->fees !== null) {
			$out['fees'] = $this->fees->toObject();
		}

		return $out;
	}

	private function terms(): SubscriptionTermsParams
	{
		return new SubscriptionTermsParams($this->frequency, $this->trialPeriod, $this->minPeriods);
	}
}
