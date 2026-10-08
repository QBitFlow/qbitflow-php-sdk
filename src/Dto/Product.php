<?php

declare(strict_types=1);

namespace QBitFlow\Dto;

use DateTimeImmutable;
use QBitFlow\Support\Cast;
use QBitFlow\Support\Dto;

/**
 * A product: something customers can buy once or subscribe to.
 *
 * Prices are always in USD; QBitFlow converts to crypto at checkout using live rates.
 */
final class Product extends Dto
{
	public function __construct(
		/** Unique identifier for the product. */
		public readonly int $id,
		/** Product name. */
		public readonly string $name,
		/** Product description. */
		public readonly string $description,
		/** Price in USD. */
		public readonly float $price,
		/** When the product was created. */
		public readonly DateTimeImmutable $createdAt,
		/** Whether the product is currently active. */
		public readonly bool $isActive,
		/** Your own reference for this product. */
		public readonly ?string $reference = null,
		/** Whether this is a test-mode product (test and live sets are isolated). */
		public readonly bool $test = false,
		/** Organization that owns this product. */
		public readonly int $organizationId = 0,
		/** User that owns this product; 0 for organization-level products. */
		public readonly int $userId = 0,
	) {
	}

	/**
	 * @param array<string,mixed> $data
	 */
	public static function fromArray(array $data): self
	{
		return new self(
			Cast::int($data, 'id'),
			Cast::string($data, 'name'),
			Cast::string($data, 'description'),
			Cast::float($data, 'price'),
			Cast::date($data, 'createdAt'),
			Cast::bool($data, 'isActive'),
			Cast::nullableString($data, 'reference'),
			Cast::bool($data, 'test'),
			Cast::int($data, 'organizationId'),
			Cast::int($data, 'userId'),
		);
	}
}
