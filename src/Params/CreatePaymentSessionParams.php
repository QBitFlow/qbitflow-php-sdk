<?php

declare(strict_types=1);

namespace QBitFlow\Params;

use QBitFlow\Support\Validator;

/**
 * Opens a one-time payment checkout session (`checkoutSessions->createPayment()`).
 *
 * Name the product with exactly one of `productUuid`, `productReference`, or an inline product
 * (`productName` + `price`, `description` optional). `successUrl` and `cancelUrl` may carry
 * `{{UUID}}` (the session's id) and `{{TRANSACTION_TYPE}}` (`payment` or `createSubscription`).
 */
final readonly class CreatePaymentSessionParams
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
		/** An inline product's price in USD, above 0 (at most 5 in test mode). */
		public ?float $price = null,
		/** Your reference for the payment (an order id): unique per space, 1 to 100 of `A-Z a-z 0-9 . _ : @ -`. */
		public ?string $reference = null,
		/** Where the customer goes after paying. */
		public ?string $successUrl = null,
		/** Where the customer goes after a cancelled or failed payment. */
		public ?string $cancelUrl = null,
		/** One of the space's customers. */
		public ?string $customerUuid = null,
		/** Your reference of the customer, kept on the payment. */
		public ?string $customerReference = null,
		/** The session's lifetime, 10 to 1440 (null or 0: the default). */
		public ?int $expiresInMinutes = null,
	) {
	}

	/** @throws \QBitFlow\Exceptions\ValidationException */
	public function validate(): void
	{
		$v = new Validator();
		self::check($v, $this->productUuid, $this->productReference, $this->productName, $this->description, $this->price,
			$this->reference, $this->successUrl, $this->cancelUrl, $this->customerUuid, $this->customerReference, $this->expiresInMinutes);
		$v->throwIfAny();
	}

	/** @return array<string,mixed> */
	public function toArray(): array
	{
		return self::body($this->productUuid, $this->productReference, $this->productName, $this->description, $this->price,
			$this->reference, $this->successUrl, $this->cancelUrl, $this->customerUuid, $this->customerReference, $this->expiresInMinutes);
	}

	/**
	 * The session checks both session types share: the product choice, names, texts,
	 * references, URLs, the price and the lifetime.
	 *
	 * @internal
	 */
	public static function check(
		Validator $v,
		?string $productUuid,
		?string $productReference,
		?string $productName,
		?string $description,
		?float $price,
		?string $reference,
		?string $successUrl,
		?string $cancelUrl,
		?string $customerUuid,
		?string $customerReference,
		?int $expiresInMinutes,
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

		// Exactly one of: productUuid, productReference, an inline product.
		$set = static fn (?string $s): bool => $s !== null && $s !== '';
		$inline = $set($productName) || $price !== null || $set($description);
		$chosen = (int) $set($productUuid) + (int) $set($productReference) + (int) $inline;

		if ($chosen !== 1) {
			$v->add('productUuid', 'or productReference, or an inline product (productName and price), is required: exactly one of them');
		} elseif ($inline) {
			if (! $set($productName)) {
				$v->add('productName', 'is required for an inline product');
			}
			if ($price === null) {
				$v->add('price', 'is required for an inline product');
			} else {
				$v->price('price', $price);
			}
		}
	}

	/**
	 * @internal
	 *
	 * @return array<string,mixed>
	 */
	public static function body(
		?string $productUuid,
		?string $productReference,
		?string $productName,
		?string $description,
		?float $price,
		?string $reference,
		?string $successUrl,
		?string $cancelUrl,
		?string $customerUuid,
		?string $customerReference,
		?int $expiresInMinutes,
	): array {
		$out = array_filter([
			'reference' => $reference,
			'productUuid' => $productUuid,
			'productReference' => $productReference,
			'productName' => $productName,
			'description' => $description,
		], static fn (?string $s): bool => $s !== null && $s !== '');
		if ($price !== null) {
			$out['price'] = $price;
		}
		$out += array_filter([
			'successUrl' => $successUrl,
			'cancelUrl' => $cancelUrl,
			'customerUuid' => $customerUuid,
			'customerReference' => $customerReference,
		], static fn (?string $s): bool => $s !== null && $s !== '');
		if ($expiresInMinutes !== null && $expiresInMinutes !== 0) {
			$out['expiresInMinutes'] = $expiresInMinutes;
		}

		return $out;
	}
}
