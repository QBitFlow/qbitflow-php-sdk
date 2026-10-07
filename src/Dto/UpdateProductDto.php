<?php

declare(strict_types=1);

namespace QBitFlow\Dto;

use QBitFlow\Exceptions\ValidationException;
use QBitFlow\Support\Dto;
use QBitFlow\Support\Validate;

/**
 * Payload for updating a product.
 *
 * Every field is optional and the update is partial: `null` fields are left off the
 * request and keep their current value. An empty payload is a valid no-op. A field you do
 * set is validated like on create — an empty name or description is rejected, as the API
 * rejects it.
 *
 * `reference` is deliberately absent — it is an immutable identifier and the API ignores
 * it on update.
 *
 * ```php
 * $product = $client->products->update($id, new UpdateProductDto(price: 39.99));
 * ```
 */
final class UpdateProductDto extends Dto
{
	/**
	 * @throws ValidationException When a provided field breaks the API's rules
	 */
	public function __construct(
		/** Product name. 2–100 characters, no markup. */
		public readonly ?string $name = null,
		/** Product description. 2–500 characters, no markup. */
		public readonly ?string $description = null,
		/** Price in USD. Must be greater than 0 when supplied. */
		public readonly ?float $price = null,
	) {
		Validate::productText('name', $name, 2, 100);
		Validate::productText('description', $description, 2, 500);
		Validate::price('price', $price);
	}

	/**
	 * @param array<array-key,mixed> $data
	 *
	 * @throws ValidationException
	 */
	public static function fromArray(array $data): self
	{
		$price = $data['price'] ?? null;

		if ($price !== null && ! is_int($price) && ! is_float($price)) {
			throw new ValidationException('price must be a number');
		}

		return new self(
			isset($data['name']) ? (string) $data['name'] : null,
			isset($data['description']) ? (string) $data['description'] : null,
			$price === null ? null : (float) $price,
		);
	}
}
