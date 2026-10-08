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
 * Manages the customers (`/customer…`).
 */
final class CustomersService extends Service
{
	/**
	 * Creates a customer (`POST /customer`, 201). Sends an Idempotency-Key and is retried on
	 * transient failures. Errors: 409 `unique_violation` when the email or the reference is taken
	 * (`details.field`).
	 */
	public function create(CreateCustomerParams $params, ?RequestOptions $options = null): Customer
	{
		$params->validate();

		return $this->requester->call('POST', '/customer', Requester::one(Customer::fromArray(...)), body: $params->toArray(), idempotent: true, options: $options);
	}

	/**
	 * Changes a customer (`PUT /customer/:uuid`): only the fields set change; `''` clears
	 * `phoneNumber` or `address`. Not retried.
	 */
	public function update(string $uuid, UpdateCustomerParams $params, ?RequestOptions $options = null): Customer
	{
		Validator::pathUuid('uuid', $uuid);
		$params->validate();

		return $this->requester->call('PUT', Requester::path('/customer/%s', $uuid), Requester::one(Customer::fromArray(...)), body: $params->toArray(), options: $options);
	}

	/**
	 * One page of the space's customers (`GET /customer/all`; page size 10 by default, at most 100).
	 *
	 * @return Page<Customer>
	 */
	public function list(?CustomerListParams $params = null, ?RequestOptions $options = null): Page
	{
		$params?->validate();

		return $this->requester->call('GET', '/customer/all', Requester::page(Customer::fromArray(...)), $params?->toQuery() ?? [], options: $options);
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

	/** A customer by its UUID (`GET /customer/uuid/:uuid`). */
	public function get(string $uuid, ?RequestOptions $options = null): Customer
	{
		Validator::pathUuid('uuid', $uuid);

		return $this->requester->call('GET', Requester::path('/customer/uuid/%s', $uuid), Requester::one(Customer::fromArray(...)), options: $options);
	}

	/** The space's customer with this email, whatever its casing (`GET /customer/email/:email`). */
	public function getByEmail(string $email, ?RequestOptions $options = null): Customer
	{
		Validator::pathRequired('email', $email);

		return $this->requester->call('GET', Requester::path('/customer/email/%s', $email), Requester::one(Customer::fromArray(...)), options: $options);
	}

	/** The space's customer with your reference (`GET /customer/reference/:reference`). */
	public function getByReference(string $reference, ?RequestOptions $options = null): Customer
	{
		Validator::pathRequired('reference', $reference);

		return $this->requester->call('GET', Requester::path('/customer/reference/%s', $reference), Requester::one(Customer::fromArray(...)), options: $options);
	}

	/** Deletes a customer (`DELETE /customer/uuid/:uuid`, a soft delete). Not retried. */
	public function delete(string $uuid, ?RequestOptions $options = null): void
	{
		Validator::pathUuid('uuid', $uuid);
		$this->requester->void('DELETE', Requester::path('/customer/uuid/%s', $uuid), options: $options);
	}
}
