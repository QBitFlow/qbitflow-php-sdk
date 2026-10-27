<?php

declare(strict_types=1);

namespace QBitFlow\Params;

use QBitFlow\Support\Validator;

/**
 * Opens a one-time payment checkout session (`checkoutSessions->createPayment()`).
 *
 * Name the product with exactly one of `productUuid`, `productReference`, or an inline product
 * (`productName` + `priceUsd`, `description` optional). `successUrl` and `cancelUrl` may carry
 * {@see \QBitFlow\Placeholders::UUID} (the session's id) and
 * {@see \QBitFlow\Placeholders::TRANSACTION_TYPE} (`payment` or `createSubscription`), filled in
 * on redirect. `fees` adds lines to the price (a tax, shipping, QBitFlow's processing fee): the
 * customer pays the price plus the fees ({@see CheckoutFees}).
 */
final readonly class CreatePaymentSessionParams
{
	/**
	 * Pass the arguments by name; an optional one left null is not sent. Nothing is checked
	 * here: the service method checks the values before sending (a
	 * {@see \QBitFlow\Exceptions\ValidationException} naming each bad one, nothing sent).
	 *
	 * @param array<string,string>|null $metadata
	 */
	public function __construct(
		/** One of the space's products (unknown: 404). */
		public ?string $productUuid = null,
		/** Names the product by its reference instead (unknown: 404). */
		public ?string $productReference = null,
		/**
		 * An inline product's name (2 to 100 characters, one line): the payment keeps it as a
		 * snapshot, without `productUuid`.
		 */
		public ?string $productName = null,
		/** An inline product's description (2 to 500 characters), optional. */
		public ?string $description = null,
		/**
		 * An inline product's price in USD, a decimal string (`"12.50"`): above 0, at most 2
		 * decimals, at most 1,000,000. The API caps it at `"5"` in test mode (`details.max` `"5"`).
		 */
		public ?string $priceUsd = null,
		/**
		 * Your reference for the payment (an order or invoice id), kept on it: unique per space
		 * (open sessions included), 1 to 100 of `A-Z a-z 0-9 . _ : @ -`, never `.` or `..` alone.
		 */
		public ?string $reference = null,
		/**
		 * Where the customer goes after paying, through QBitFlow's page for the payment (the
		 * placeholders filled in). Default: the space's `checkout.defaults` setting. https only in
		 * live mode. Reaching it is no proof of payment: fulfil on `payment.completed`.
		 * A member's own key may send it (and `cancelUrl`) while the organization's
		 * `members.checkoutRedirects` policy allows it (on by default). With the policy off, it is a
		 * 403 `policy_disabled` with `details['policy']` (`members.checkoutRedirects`) and
		 * `details['field']` naming it (`successUrl` first when both are sent), nothing created: the
		 * member's checkouts then follow the organization's `checkout.defaults`. An organization key
		 * (acting On-Behalf-Of a member or not) may always send both.
		 */
		public ?string $successUrl = null,
		/**
		 * Where the customer goes after a cancelled or failed payment (same placeholders). A
		 * member's own key may send it while `members.checkoutRedirects` allows it, as `successUrl`.
		 */
		public ?string $cancelUrl = null,
		/** One of the space's customers (unknown: 404); the checkout then asks the customer nothing. */
		public ?string $customerUuid = null,
		/**
		 * Your own reference of the customer, kept on the payment: the space's customer with this
		 * reference is linked if there is one (none is created). The checkout then asks the
		 * customer nothing.
		 */
		public ?string $customerReference = null,
		/** How long the checkout can be paid, 10 to 1440 minutes (null or 0: the default lifetime; never longer than it). */
		public ?int $expiresInMinutes = null,
		/**
		 * Amounts added to the price, shown to the customer line by line and paid with it: your
		 * lines and QBitFlow's processing fee. The session's and the payment's `amountUsd` is the
		 * price plus every line (the network fee comes on top), and QBitFlow's fee is taken on it.
		 * The test-mode cap (`"5"`) applies to that amount, fees included (400 `validation_failed`
		 * on `fees`). Null: none, unless the space's `checkout.customerPaysProcessingFee` setting
		 * adds the processing fee.
		 */
		public ?CheckoutFees $fees = null,
		/**
		 * Your own data on the checkout (an order id, a CRM key): at most 50 keys of 1 to 40
		 * characters among `A-Z a-z 0-9 _ - . :`, values of at most 500 characters without control
		 * or bidirectional characters (`''` values are dropped). Copied onto the payment and into
		 * its webhooks (`payment.completed`, `checkout.expired`); never shown to the customer. Never
		 * put secrets or personal data in it.
		 */
		public ?array $metadata = null,
	) {
	}

	/** @throws \QBitFlow\Exceptions\ValidationException */
	public function validate(): void
	{
		$v = new Validator();
		self::check($v, $this->productUuid, $this->productReference, $this->productName, $this->description, $this->priceUsd,
			$this->reference, $this->successUrl, $this->cancelUrl, $this->customerUuid, $this->customerReference,
			$this->expiresInMinutes, $this->metadata);
		$this->fees?->check($v);
		$v->throwIfAny();
	}

	/** @return array<string,mixed> */
	public function toArray(): array
	{
		$out = self::body($this->productUuid, $this->productReference, $this->productName, $this->description, $this->priceUsd,
			$this->reference, $this->successUrl, $this->cancelUrl, $this->customerUuid, $this->customerReference,
			$this->expiresInMinutes, $this->metadata);
		if ($this->fees !== null) {
			$out['fees'] = $this->fees->toObject();
		}

		return $out;
	}

	/**
	 * The session checks both session types share: the product choice, names, texts,
	 * references, URLs, the price, the lifetime and the metadata (the fees are checked last).
	 *
	 * @internal
	 *
	 * @param array<array-key,mixed>|null $metadata
	 */
	public static function check(
		Validator $v,
		?string $productUuid,
		?string $productReference,
		?string $productName,
		?string $description,
		?string $priceUsd,
		?string $reference,
		?string $successUrl,
		?string $cancelUrl,
		?string $customerUuid,
		?string $customerReference,
		?int $expiresInMinutes,
		?array $metadata,
	): void {
		$v->reference('reference', $reference);
		$v->uuid('productUuid', $productUuid);
		$v->reference('productReference', $productReference);
		$v->name('productName', $productName, 2, 100);
		$v->text('description', $description, 2, 500);
		$v->url('successUrl', $successUrl);
		$v->url('cancelUrl', $cancelUrl);
		$v->uuid('customerUuid', $customerUuid);
		$v->reference('customerReference', $customerReference);
		if ($expiresInMinutes !== null && $expiresInMinutes !== 0) {
			$v->intRange('expiresInMinutes', $expiresInMinutes, 10, 1440);
		}
		$v->metadata('metadata', $metadata);

		// Exactly one of: productUuid, productReference, an inline product. Any inline field next
		// to productUuid or productReference counts as mixing.
		$set = static fn (?string $s): bool => $s !== null && $s !== '';
		$inline = $set($productName) || $set($priceUsd) || $set($description);
		$chosen = (int) $set($productUuid) + (int) $set($productReference) + (int) $inline;

		if ($chosen !== 1) {
			$v->add('productUuid', 'or productReference, or an inline product (productName and priceUsd), is required: exactly one of them');
		} elseif ($inline) {
			if (! $set($productName)) {
				$v->add('productName', 'is required for an inline product');
			}
			if ($priceUsd === null || $priceUsd === '') {
				$v->add('priceUsd', 'is required for an inline product');
			} else {
				$v->usd('priceUsd', $priceUsd);
			}
		}
	}

	/**
	 * The fields both session types send, in the API's order.
	 *
	 * @internal
	 *
	 * @param array<array-key,mixed>|null $metadata
	 *
	 * @return array<string,mixed>
	 */
	public static function body(
		?string $productUuid,
		?string $productReference,
		?string $productName,
		?string $description,
		?string $priceUsd,
		?string $reference,
		?string $successUrl,
		?string $cancelUrl,
		?string $customerUuid,
		?string $customerReference,
		?int $expiresInMinutes,
		?array $metadata,
	): array {
		$out = array_filter([
			'reference' => $reference,
			'productUuid' => $productUuid,
			'productReference' => $productReference,
			'productName' => $productName,
			'description' => $description,
			'priceUsd' => $priceUsd,
			'successUrl' => $successUrl,
			'cancelUrl' => $cancelUrl,
			'customerUuid' => $customerUuid,
			'customerReference' => $customerReference,
		], static fn (?string $s): bool => $s !== null && $s !== '');
		if ($expiresInMinutes !== null && $expiresInMinutes !== 0) {
			$out['expiresInMinutes'] = $expiresInMinutes;
		}
		if ($metadata !== null && $metadata !== []) {
			$out['metadata'] = (object) $metadata;
		}

		return $out;
	}
}
