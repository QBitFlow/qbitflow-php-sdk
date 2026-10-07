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
 * Every optional string treats `''` as "not provided" and leaves it off the request.
 *
 * ```php
 * // From a stored product
 * $link = $client->oneTimePayments->createSession(new CreatePaymentSessionDto(
 *     productId: 1,
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
	/**
	 * Your own reference for the transaction, such as an order or invoice ID. Echoed back
	 * on the resulting payment and in webhooks.
	 */
	public readonly ?string $reference;

	/** Use an existing product by ID. */
	public readonly ?int $productId;

	/** Use an existing product by your own reference. Alternative to `productId`. */
	public readonly ?string $productReference;

	/** Inline product name (2–100 characters, no markup), when not using a stored product. */
	public readonly ?string $productName;

	/** Inline product description (2–500 characters, no markup), when not using a stored product. */
	public readonly ?string $description;

	/** Price in USD, greater than 0. Required when not using a stored product. */
	public readonly ?float $price;

	/** Where to send the customer after a successful payment (absolute http(s) URL). */
	public readonly ?string $successUrl;

	/** Where to send the customer after a cancelled payment (absolute http(s) URL). */
	public readonly ?string $cancelUrl;

	/** Pre-fill the customer by their bare UUID. The customer is prompted when omitted. */
	public readonly ?string $customerUUID;

	/**
	 * Pre-fill the customer by your own reference. Alternative to `customerUUID`; a new
	 * customer is created during checkout when nothing matches.
	 */
	public readonly ?string $customerReference;

	public function __construct(
		?string $reference = null,
		?int $productId = null,
		?string $productReference = null,
		?string $productName = null,
		?string $description = null,
		?float $price = null,
		?string $successUrl = null,
		?string $cancelUrl = null,
		?string $customerUUID = null,
		?string $customerReference = null,
	) {
		$this->reference = self::optional($reference);
		$this->productId = $productId;
		$this->productReference = self::optional($productReference);
		$this->productName = self::optional($productName);
		$this->description = self::optional($description);
		$this->price = $price;
		$this->successUrl = self::optional($successUrl);
		$this->cancelUrl = self::optional($cancelUrl);
		$this->customerUUID = self::optional($customerUUID);
		$this->customerReference = self::optional($customerReference);
	}

	/**
	 * @param array<array-key,mixed> $data
	 */
	public static function fromArray(array $data): static
	{
		return new static(...self::sessionArguments($data));
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

		if ($this->productId !== null && $this->productId <= 0) {
			throw new ValidationException('Product ID must be positive');
		}

		// Mirror the API's binding rules. These run after the structural check so a missing
		// product is still the error you see first.
		Validate::price('price', $this->price);
		Validate::productText('productName', $this->productName, 2, 100);
		Validate::productText('description', $this->description, 2, 500);
		Validate::redirectUrl('successUrl', $this->successUrl);
		Validate::redirectUrl('cancelUrl', $this->cancelUrl);
		Validate::uuid('customerUUID', $this->customerUUID);
	}

	/**
	 * Constructor arguments shared with {@see CreateSubscriptionSessionDto::fromArray()}.
	 *
	 * @param array<array-key,mixed> $data
	 *
	 * @return array<string,mixed>
	 *
	 * @throws ValidationException When a value has the wrong type.
	 */
	protected static function sessionArguments(array $data): array
	{
		return [
			'reference' => self::stringArg($data, 'reference'),
			'productId' => self::intArg($data, 'productId'),
			'productReference' => self::stringArg($data, 'productReference'),
			'productName' => self::stringArg($data, 'productName'),
			'description' => self::stringArg($data, 'description'),
			'price' => self::floatArg($data, 'price'),
			'successUrl' => self::stringArg($data, 'successUrl'),
			'cancelUrl' => self::stringArg($data, 'cancelUrl'),
			'customerUUID' => self::stringArg($data, 'customerUUID'),
			'customerReference' => self::stringArg($data, 'customerReference'),
		];
	}

	/**
	 * @param array<array-key,mixed> $data
	 *
	 * @throws ValidationException
	 */
	protected static function stringArg(array $data, string $key): ?string
	{
		$value = $data[$key] ?? null;

		if ($value === null || is_string($value)) {
			return $value;
		}

		if (is_int($value)) {
			return (string) $value;
		}

		throw new ValidationException("{$key} must be a string");
	}

	/**
	 * @param array<array-key,mixed> $data
	 *
	 * @throws ValidationException
	 */
	protected static function intArg(array $data, string $key): ?int
	{
		$value = $data[$key] ?? null;

		if ($value === null || is_int($value)) {
			return $value;
		}

		throw new ValidationException("{$key} must be an integer");
	}

	/**
	 * @param array<array-key,mixed> $data
	 *
	 * @throws ValidationException
	 */
	protected static function floatArg(array $data, string $key): ?float
	{
		$value = $data[$key] ?? null;

		if ($value === null || is_float($value) || is_int($value)) {
			return $value === null ? null : (float) $value;
		}

		throw new ValidationException("{$key} must be a number");
	}
}
