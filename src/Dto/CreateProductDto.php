<?php

declare(strict_types=1);

namespace QBitFlow\Dto;

use QBitFlow\Exceptions\ValidationException;
use QBitFlow\Support\Dto;
use QBitFlow\Support\Validate;

/**
 * Payload for creating a product.
 *
 * `name` must be 2–100 and `description` 2–500 characters, both free of markup (the API's
 * `producttext` rule), and `price` must be a finite number strictly greater than 0 USD.
 * These are checked here, before any request is sent.
 */
final class CreateProductDto extends Dto
{
	/** Product name. Required, 2–100 characters, no markup. */
	public readonly string $name;

	/** Product description. Required, 2–500 characters, no markup. */
	public readonly string $description;

	/** Price in USD. Required, must be greater than 0. */
	public readonly float $price;

	/** Your own reference. One is generated for you when omitted (`''` counts as omitted). Immutable. */
	public readonly ?string $reference;

	/**
	 * @throws ValidationException When a field breaks the API's rules
	 */
	public function __construct(string $name, string $description, float $price, ?string $reference = null)
	{
		Validate::required('name', $name);
		Validate::required('description', $description);
		Validate::productText('name', $name, 2, 100);
		Validate::productText('description', $description, 2, 500);
		Validate::price('price', $price);

		$this->name = $name;
		$this->description = $description;
		$this->price = $price;
		$this->reference = self::optional($reference);
	}

	/**
	 * @param array<array-key,mixed> $data
	 *
	 * @throws ValidationException
	 */
	public static function fromArray(array $data): self
	{
		$price = $data['price'] ?? null;

		if (! is_int($price) && ! is_float($price)) {
			throw new ValidationException('price is required and must be a number');
		}

		return new self(
			(string) ($data['name'] ?? ''),
			(string) ($data['description'] ?? ''),
			(float) $price,
			isset($data['reference']) ? (string) $data['reference'] : null,
		);
	}
}
