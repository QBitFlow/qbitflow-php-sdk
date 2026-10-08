<?php

declare(strict_types=1);

namespace QBitFlow\Dto\Session;

use QBitFlow\Exceptions\ValidationException;
use QBitFlow\Support\Dto;
use QBitFlow\Support\Validate;

/**
 * Payload for creating a one-time payment session.
 *
 * Identify the product in one of three ways: by `productId`, by your own
 * `productReference`, or inline with `productName` + `description` + `price`.
 *
 * ```php
 * // From a stored product
 * $link = $client->oneTimePayments->createSession(new CreatePaymentSessionDto(
 *     productId: 1,
 *     customerUUID: 'customer-uuid',
 *     successUrl: 'https://example.com/success',
 * ));
 *
 * // Entirely inline, keyed by your own identifiers
 * $link = $client->oneTimePayments->createSession(new CreatePaymentSessionDto(
 *     reference: 'order-1234',
 *     productName: 'Custom Product',
 *     description: 'One-off charge',
 *     price: 99.99,
 *     customerReference: 'user-42',
 * ));
 * ```
 */
class CreatePaymentSessionDto extends Dto
{
	public function __construct(
		/**
		 * Your own reference for the transaction, such as an order or invoice ID. Echoed
		 * back on the resulting payment and in webhooks.
		 */
		public readonly ?string $reference = null,
		/** Use an existing product by ID. */
		public readonly ?int $productId = null,
		/** Use an existing product by your own reference. Alternative to `productId`. */
		public readonly ?string $productReference = null,
		/** Inline product name, when not using a stored product. */
		public readonly ?string $productName = null,
		/** Inline product description, when not using a stored product. */
		public readonly ?string $description = null,
		/** Price in USD. Required when not using a stored product. */
		public readonly ?float $price = null,
		/** Where to send the customer after a successful payment. */
		public readonly ?string $successUrl = null,
		/** Where to send the customer after a cancelled payment. */
		public readonly ?string $cancelUrl = null,
		/** Pre-fill the customer by UUID. The customer is prompted when omitted. */
		public readonly ?string $customerUUID = null,
		/**
		 * Pre-fill the customer by your own reference. Alternative to `customerUUID`;
		 * a new customer is created during checkout when nothing matches.
		 */
		public readonly ?string $customerReference = null,
	) {
	}

	/**
	 * @param array<string,mixed> $data
	 */
	public static function fromArray(array $data): static
	{
		return new static(
			isset($data['reference']) ? (string) $data['reference'] : null,
			isset($data['productId']) ? (int) $data['productId'] : null,
			isset($data['productReference']) ? (string) $data['productReference'] : null,
			isset($data['productName']) ? (string) $data['productName'] : null,
			isset($data['description']) ? (string) $data['description'] : null,
			isset($data['price']) ? (float) $data['price'] : null,
			isset($data['successUrl']) ? (string) $data['successUrl'] : null,
			isset($data['cancelUrl']) ? (string) $data['cancelUrl'] : null,
			isset($data['customerUUID']) ? (string) $data['customerUUID'] : null,
			isset($data['customerReference']) ? (string) $data['customerReference'] : null,
		);
	}

	/**
	 * Check the payload before it is sent, so obvious mistakes surface as a clear error
	 * rather than an opaque 400 from the API.
	 *
	 * @throws ValidationException
	 */
	public function validate(): void
	{
		$hasStoredProduct = $this->productId !== null || $this->productReference !== null;
		$hasInlineProduct = $this->productName !== null
			&& $this->description !== null
			&& $this->price !== null;

		if (! $hasStoredProduct && ! $hasInlineProduct) {
			throw new ValidationException(
				'Either productId, productReference, or all of productName, description and price must be provided',
			);
		}

		if ($this->price !== null && $this->price < 0) {
			throw new ValidationException('Price must be a non-negative value');
		}

		if ($this->productId !== null && $this->productId <= 0) {
			throw new ValidationException('Product ID must be positive');
		}

		// Mirror the API's binding rules for an inline ("ghost") product and the redirect
		// URLs. These run last so a structural problem (no product at all, negative price)
		// is still the error you see first.
		Validate::productText('productName', $this->productName, 2, 100);
		Validate::productText('description', $this->description, 2, 500);
		Validate::redirectUrl('successUrl', $this->successUrl);
		Validate::redirectUrl('cancelUrl', $this->cancelUrl);
	}
}
