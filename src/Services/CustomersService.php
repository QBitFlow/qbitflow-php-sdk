<?php

declare(strict_types=1);

namespace QBitFlow\Services;

use Generator;
use QBitFlow\Http\Requester;
use QBitFlow\Models\Customer;
use QBitFlow\Page;
use QBitFlow\Params\CreateCustomerParams;
use QBitFlow\Params\CustomerListParams;
use QBitFlow\Params\UpdateCustomerParams;
use QBitFlow\RequestOptions;
use QBitFlow\Support\Validator;

/**
 * Manages the customers (`/customers…`).
 */
final class CustomersService extends Service
{
	/**
	 * One page of the space's customers (`GET /customers`; newest first, page size 20 by default,
	 * at most 100), filtered by `email` (several customers may share one), `reference` or
	 * `verified`. {@see iterate()} walks every page.
	 *
	 * @return Page<Customer>
	 */
	public function list(?CustomerListParams $params = null, ?RequestOptions $options = null): Page
	{
		$params?->validate();

		return $this->requester->call('GET', '/customers', Requester::page(Customer::fromArray(...)), $params?->toQuery() ?? [], options: $options);
	}

	/**
	 * Every customer `list()` returns, the pages fetched lazily from `$params->cursor` (or the
	 * first page), filters and page size kept.
	 *
	 * @return Generator<int,Customer>
	 */
	public function iterate(?CustomerListParams $params = null, ?RequestOptions $options = null): Generator
	{
		$params ??= new CustomerListParams();

		return Requester::walk($params->cursor, fn (?string $cursor): Page => $this->list($params->withCursor($cursor), $options));
	}

	/**
	 * Creates a customer (`POST /customers`, 201; verified). Sends an Idempotency-Key and is
	 * retried on transient failures. The email is stored lowercase and is not unique: several
	 * customers of a space may share one.
	 *
	 * Errors: 400 validation_failed naming the field; 409 `unique_violation` when the reference is
	 * taken by another live customer of the space (`details.field` `reference`).
	 */
	public function create(CreateCustomerParams $params, ?RequestOptions $options = null): Customer
	{
		$params->validate();

		return $this->requester->call('POST', '/customers', Requester::one(Customer::fromArray(...)), body: $params->toArray(), idempotent: true, options: $options);
	}

	/**
	 * A customer by its UUID (`GET /customers/:uuid`). An unknown, deleted or another space's
	 * customer is a {@see \QBitFlow\Exceptions\NotFoundException}.
	 */
	public function get(string $uuid, ?RequestOptions $options = null): Customer
	{
		Validator::pathUuid('uuid', $uuid);

		return $this->requester->call('GET', Requester::path('/customers/%s', $uuid), Requester::one(Customer::fromArray(...)), options: $options);
	}

	/**
	 * The space's customer with your reference: runs `list()`'s reference filter
	 * (`GET /customers?reference=`) and returns its one customer. No customer with that reference
	 * is a {@see \QBitFlow\Exceptions\NotFoundException} (status 404, code `not_found`). To find
	 * customers by email, use `list(new CustomerListParams(email: …))` (an email may match several).
	 */
	public function getByReference(string $reference, ?RequestOptions $options = null): Customer
	{
		return $this->requester->getByReference('/customers', 'customer', $reference, Customer::fromArray(...),
			static fn (Customer $customer): ?string => $customer->reference, $options);
	}

	/**
	 * Changes a customer (`PATCH /customers/:uuid`): only the fields set change; `''` clears
	 * `lastName`, `phoneNumber` or `address`; `metadata` is merged. The reference cannot change.
	 * Not retried.
	 */
	public function update(string $uuid, UpdateCustomerParams $params, ?RequestOptions $options = null): Customer
	{
		Validator::pathUuid('uuid', $uuid);
		$params->validate();

		return $this->requester->call('PATCH', Requester::path('/customers/%s', $uuid), Requester::one(Customer::fromArray(...)), body: $params->toArray(), options: $options);
	}

	/**
	 * Deletes a customer (`DELETE /customers/:uuid`, 204; a soft delete): it leaves every read and
	 * frees its reference; its past payments keep naming it (`CustomerSummary::$deleted`). Not
	 * retried.
	 */
	public function delete(string $uuid, ?RequestOptions $options = null): void
	{
		Validator::pathUuid('uuid', $uuid);
		$this->requester->void('DELETE', Requester::path('/customers/%s', $uuid), options: $options);
	}
}
