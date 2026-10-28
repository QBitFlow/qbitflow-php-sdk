<?php

declare(strict_types=1);

namespace QBitFlow\Services;

use Generator;
use QBitFlow\Http\Requester;
use QBitFlow\Models\Product;
use QBitFlow\Page;
use QBitFlow\Params\CreateProductParams;
use QBitFlow\Params\ProductListParams;
use QBitFlow\Params\UpdateProductParams;
use QBitFlow\RequestOptions;
use QBitFlow\Support\Validator;

/**
 * Manages the products (`/products…`).
 */
final class ProductsService extends Service
{
	/**
	 * One page of the space's products (`GET /products`; newest first, page size 20 by default,
	 * at most 100). Hidden products (`isActive` false) are left out unless `includeHidden`;
	 * `subscription` keeps only the subscription (true) or one-time (false) products; `reference`
	 * the one with that reference. {@see iterate()} walks every page.
	 *
	 * @return Page<Product>
	 */
	public function list(?ProductListParams $params = null, ?RequestOptions $options = null): Page
	{
		$params?->validate();

		return $this->requester->call('GET', '/products', Requester::page(Product::fromArray(...)), $params?->toQuery() ?? [], options: $options);
	}

	/**
	 * Every product `list()` returns, the pages fetched lazily from `$params->cursor` (or the
	 * first page), filters and page size kept.
	 *
	 * @return Generator<int,Product>
	 */
	public function iterate(?ProductListParams $params = null, ?RequestOptions $options = null): Generator
	{
		$params ??= new ProductListParams();

		return Requester::walk($params->cursor, fn (?string $cursor): Page => $this->list($params->withCursor($cursor), $options));
	}

	/**
	 * Creates a product (`POST /products`, 201). Sends an Idempotency-Key and is retried on
	 * transient failures. A subscription product needs `subscription->frequency`.
	 *
	 * Errors: 400 validation_failed (e.g. a price above `"5"` in test mode, `details.max` `"5"`; a
	 * live subscription product at `"0.25"` or less, `details.min`); 409 `unique_violation` on
	 * reference (references are unique among the space's live products); a member's key needs
	 * the organization's `members.products` policy, and `members.subscriptions` to give the
	 * product subscription terms (403 `policy_disabled`, `details.policy` names it).
	 */
	public function create(CreateProductParams $params, ?RequestOptions $options = null): Product
	{
		$params->validate();

		return $this->requester->call('POST', '/products', Requester::one(Product::fromArray(...)), body: $params->toArray(), idempotent: true, options: $options);
	}

	/**
	 * A product by its UUID (`GET /products/:uuid`), hidden ones included. An unknown, deleted or
	 * another space's product is a {@see \QBitFlow\Exceptions\NotFoundException}.
	 */
	public function get(string $uuid, ?RequestOptions $options = null): Product
	{
		Validator::pathUuid('uuid', $uuid);

		return $this->requester->call('GET', Requester::path('/products/%s', $uuid), Requester::one(Product::fromArray(...)), options: $options);
	}

	/**
	 * The space's product with your reference, hidden or not: runs `list()`'s reference filter
	 * (`GET /products?reference=`) and returns its one product. No product with that reference is
	 * a {@see \QBitFlow\Exceptions\NotFoundException} (status 404, code `not_found`), as an unknown
	 * uuid is. The reference is checked like a created one's (1 to 100 of `A-Z a-z 0-9 . _ : @ -`,
	 * never `.` or `..` alone).
	 */
	public function getByReference(string $reference, ?RequestOptions $options = null): Product
	{
		return $this->requester->getByReference('/products', 'product', $reference, Product::fromArray(...),
			static fn (Product $product): string => $product->reference, $options);
	}

	/**
	 * Changes a product in place (`PATCH /products/:uuid`): only the fields set change (empty
	 * params are a no-op that returns the product); `metadata` is merged. The reference cannot
	 * change. A new price or new terms apply to new checkouts and new subscribers only: existing
	 * subscriptions and open checkout sessions keep theirs. Not retried.
	 *
	 * A member's key gives a product subscription terms only with the organization's
	 * `members.subscriptions` policy (403 `policy_disabled`); `removeSubscription` and other edits
	 * never need it.
	 */
	public function update(string $uuid, UpdateProductParams $params, ?RequestOptions $options = null): Product
	{
		Validator::pathUuid('uuid', $uuid);
		$params->validate();

		return $this->requester->call('PATCH', Requester::path('/products/%s', $uuid), Requester::one(Product::fromArray(...)), body: $params->toArray(), options: $options);
	}

	/**
	 * Deletes a product (`DELETE /products/:uuid`, 204; a soft delete): it leaves every read and
	 * frees its reference. It does not stop its subscriptions (they keep billing at their price):
	 * cancel them with `subscriptions->cancel()` if the product is gone for good. Not retried.
	 */
	public function delete(string $uuid, ?RequestOptions $options = null): void
	{
		Validator::pathUuid('uuid', $uuid);
		$this->requester->void('DELETE', Requester::path('/products/%s', $uuid), options: $options);
	}
}
