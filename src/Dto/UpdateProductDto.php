<?php

declare(strict_types=1);

namespace QBitFlow\Dto;

use QBitFlow\Exceptions\ValidationException;
use QBitFlow\Support\Dto;

/**
 * Payload for updating a product.
 *
 * Every field is optional and the update is partial: omitted fields are left untouched,
 * since `null` values are never serialized. An empty payload is a valid no-op.
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
	public function __construct(
		/** Product name. Optional, 2–100 characters. */
		public readonly ?string $name = null,
		/** Product description. Optional, 2–500 characters. */
		public readonly ?string $description = null,
		/** Price in USD. Optional, must be greater than 0 when supplied. */
		public readonly ?float $price = null,
	) {
		if ($price !== null && $price <= 0) {
			throw new ValidationException('Price must be greater than 0');
		}

		if ($name !== null && (mb_strlen($name) < 2 || mb_strlen($name) > 100)) {
			throw new ValidationException('Product name must be between 2 and 100 characters');
		}

		if ($description !== null && (mb_strlen($description) < 2 || mb_strlen($description) > 500)) {
			throw new ValidationException('Product description must be between 2 and 500 characters');
		}
	}

	/**
	 * @param array<string,mixed> $data
	 */
	public static function fromArray(array $data): self
	{
		return new self(
			isset($data['name']) ? (string) $data['name'] : null,
			isset($data['description']) ? (string) $data['description'] : null,
			isset($data['price']) ? (float) $data['price'] : null,
		);
	}
}
