<?php

declare(strict_types=1);

namespace QBitFlow\Services;

use QBitFlow\Http\Requester;
use QBitFlow\Models\Product;
use QBitFlow\Params\CreateProductParams;
use QBitFlow\Params\ProductListParams;
use QBitFlow\Params\UpdateProductParams;
use QBitFlow\RequestOptions;
use QBitFlow\Support\Validator;

/**
 * Manages the products (`/product…`).
 */
final class ProductsService extends Service
{
	/**
	 * The space's products (`GET /product`), cheapest first; not paginated. Hidden products
	 * (`isActive` false) are left out unless `includeHidden`.
	 *
	 * @return list<Product>
	 */
	public function list(?ProductListParams $params = null, ?RequestOptions $options = null): array
	{
		$params?->validate();

		return $this->requester->call('GET', '/product', Requester::list(Product::fromArray(...)), $params?->toQuery() ?? [], options: $options);
	}

	/**
	 * Creates a product (`POST /product`, 201). Sends an Idempotency-Key and is retried on
	 * transient failures. A subscription product needs `subscription->frequency`.
	 *
	 * Errors: 400 validation_failed (e.g. a price above 5 USD in test mode, `details.max`); 409
	 * `unique_violation` on reference; a member's key needs the `members.products` policy (403).
	 */
	public function create(CreateProductParams $params, ?RequestOptions $options = null): Product
	{
		$params->validate();

		return $this->requester->call('POST', '/product', Requester::one(Product::fromArray(...)), body: $params->toArray(), idempotent: true, options: $options);
	}

	/** A product by its UUID (`GET /product/uuid/:uuid`), hidden ones included. */
	public function get(string $uuid, ?RequestOptions $options = null): Product
	{
		Validator::pathUuid('uuid', $uuid);

		return $this->requester->call('GET', Requester::path('/product/uuid/%s', $uuid), Requester::one(Product::fromArray(...)), options: $options);
	}

	/** A product by your reference (`GET /product/reference/:reference`; sent escaped). */
	public function getByReference(string $reference, ?RequestOptions $options = null): Product
	{
		Validator::pathRequired('reference', $reference);

		return $this->requester->call('GET', Requester::path('/product/reference/%s', $reference), Requester::one(Product::fromArray(...)), options: $options);
	}

	/**
	 * Changes a product in place (`PUT /product/:uuid`): only the fields set change. A new price
	 * applies to new checkouts and new subscribers only. Not retried.
	 */
	public function update(string $uuid, UpdateProductParams $params, ?RequestOptions $options = null): Product
	{
		Validator::pathUuid('uuid', $uuid);
		$params->validate();

		return $this->requester->call('PUT', Requester::path('/product/%s', $uuid), Requester::one(Product::fromArray(...)), body: $params->toArray(), options: $options);
	}

	/**
	 * Deletes a product (`DELETE /product/:uuid`, a soft delete). It does not stop its
	 * subscriptions: cancel them with `subscriptions->cancel()`. Not retried.
	 */
	public function delete(string $uuid, ?RequestOptions $options = null): void
	{
		Validator::pathUuid('uuid', $uuid);
		$this->requester->void('DELETE', Requester::path('/product/%s', $uuid), options: $options);
	}
}
