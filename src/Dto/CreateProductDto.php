<?php

declare(strict_types=1);

namespace QBitFlow\Dto;

use QBitFlow\Exceptions\ValidationException;
use QBitFlow\Support\Dto;

/**
 * Payload for creating a product.
 *
 * The API requires `name` to be 2–100 characters, `description` 2–500 characters,
 * and `price` to be a non-negative USD amount.
 */
final class CreateProductDto extends Dto
{
	public function __construct(
		/** Product name. Required, 2–100 characters. */
		public readonly string $name,
		/** Product description. Required, 2–500 characters. */
		public readonly string $description,
		/** Price in USD. Required, must be >= 0. */
		public readonly float $price,
		/** Your own reference. One is generated for you when omitted. */
		public readonly ?string $reference = null,
	) {
		if ($price < 0) {
			throw new ValidationException('Price must be a non-negative value');
		}
	}

	/**
	 * @param array<string,mixed> $data
	 */
	public static function fromArray(array $data): self
	{
		return new self(
			(string) ($data['name'] ?? ''),
			(string) ($data['description'] ?? ''),
			(float) ($data['price'] ?? 0),
			isset($data['reference']) ? (string) $data['reference'] : null,
		);
	}
}
