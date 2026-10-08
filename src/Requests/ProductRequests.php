<?php

declare(strict_types=1);

namespace QBitFlow\Requests;

use QBitFlow\Dto\CreateProductDto;
use QBitFlow\Dto\Product;
use QBitFlow\Dto\SuccessResponse;
use QBitFlow\Dto\UpdateProductDto;
use QBitFlow\Support\Cast;

/**
 * Create and manage the products customers buy or subscribe to.
 */
final class ProductRequests extends Request
{
	private const BASE_ROUTE = '/product';

	/**
	 * Create a product.
	 *
	 * ```php
	 * $product = $client->products->create(new CreateProductDto(
	 *     name: 'Premium Plan',
	 *     description: 'Access to all premium features',
	 *     price: 29.99,
	 *     reference: 'PROD-PREMIUM',
	 * ));
	 * ```
	 *
	 * @param CreateProductDto|array<string,mixed> $product
	 */
	public function create(CreateProductDto|array $product): Product
	{
		$dto = is_array($product) ? CreateProductDto::fromArray($product) : $product;

		return Product::fromArray($this->transport->post(self::BASE_ROUTE . '/', $dto->toArray()));
	}

	/**
	 * Get a product by ID.
	 */
	public function get(int $productId): Product
	{
		$this->requirePositive($productId, 'Product ID');

		return Product::fromArray($this->transport->get(self::BASE_ROUTE . '/id/' . $productId));
	}

	/**
	 * Get every product available to the current key.
	 *
	 * @return list<Product>
	 */
	public function getAll(): array
	{
		return Cast::listOf($this->transport->get(self::BASE_ROUTE . '/'), Product::fromArray(...));
	}

	/**
	 * Get a product by the reference you assigned when creating it.
	 */
	public function getByReference(string $reference): Product
	{
		$this->requireNonEmpty($reference, 'Product reference');

		return Product::fromArray(
			$this->transport->get(self::BASE_ROUTE . '/reference/' . $this->encode($reference)),
		);
	}

	/**
	 * Update a product. All three fields replace the current values.
	 *
	 * @param UpdateProductDto|array<string,mixed> $product
	 */
	public function update(int $productId, UpdateProductDto|array $product): Product
	{
		$this->requirePositive($productId, 'Product ID');

		$dto = is_array($product) ? UpdateProductDto::fromArray($product) : $product;

		return Product::fromArray(
			$this->transport->put(self::BASE_ROUTE . '/' . $productId, $dto->toArray()),
		);
	}

	/**
	 * Delete a product.
	 */
	public function delete(int $productId): SuccessResponse
	{
		$this->requirePositive($productId, 'Product ID');

		return SuccessResponse::fromArray($this->transport->delete(self::BASE_ROUTE . '/' . $productId));
	}
}
