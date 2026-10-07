<?php

declare(strict_types=1);

namespace QBitFlow\Requests;

use QBitFlow\Dto\CreateProductDto;
use QBitFlow\Dto\Product;
use QBitFlow\Dto\SuccessResponse;
use QBitFlow\Dto\UpdateProductDto;

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

		return $this->transport->post(self::BASE_ROUTE . '/', $dto->toArray(), self::one(Product::fromArray(...)));
	}

	/**
	 * Get a product by ID.
	 */
	public function get(int $productId): Product
	{
		$this->requirePositive($productId, 'Product ID');

		return $this->transport->get(self::BASE_ROUTE . '/id/' . $productId, map: self::one(Product::fromArray(...)));
	}

	/**
	 * Get every product available to the current key.
	 *
	 * @return list<Product>
	 */
	public function getAll(): array
	{
		return $this->transport->get(self::BASE_ROUTE . '/', map: self::list(Product::fromArray(...)));
	}

	/**
	 * Get a product by the reference you assigned when creating it.
	 */
	public function getByReference(string $reference): Product
	{
		$this->requireNonEmpty($reference, 'Product reference');

		return $this->transport->get(
			self::BASE_ROUTE . '/reference/' . $this->encode($reference),
			map: self::one(Product::fromArray(...)),
		);
	}

	/**
	 * Update a product. Only the fields you set are sent; the others keep their value.
	 *
	 * @param UpdateProductDto|array<string,mixed> $product
	 */
	public function update(int $productId, UpdateProductDto|array $product): Product
	{
		$this->requirePositive($productId, 'Product ID');

		$dto = is_array($product) ? UpdateProductDto::fromArray($product) : $product;

		return $this->transport->put(
			self::BASE_ROUTE . '/' . $productId,
			$dto->toArray(),
			self::one(Product::fromArray(...)),
		);
	}

	/**
	 * Delete a product.
	 */
	public function delete(int $productId): SuccessResponse
	{
		$this->requirePositive($productId, 'Product ID');

		return $this->transport->delete(self::BASE_ROUTE . '/' . $productId, self::one(SuccessResponse::fromArray(...)));
	}
}
