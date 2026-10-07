<?php

declare(strict_types=1);

namespace QBitFlow\Requests;

use QBitFlow\Dto\CreateCustomerDto;
use QBitFlow\Dto\Customer;
use QBitFlow\Dto\SuccessResponse;
use QBitFlow\Dto\UpdateCustomerDto;
use QBitFlow\Support\Validate;
use QBitFlow\Support\CursorData;

/**
 * Create and manage the customers who pay through your platform.
 */
final class CustomerRequests extends Request
{
	private const BASE_ROUTE = '/customer';

	/**
	 * Create a customer.
	 *
	 * ```php
	 * $customer = $client->customers->create(new CreateCustomerDto(
	 *     name: 'John',
	 *     lastName: 'Doe',
	 *     email: 'john@example.com',
	 * ));
	 * ```
	 *
	 * @param CreateCustomerDto|array<string,mixed> $customer
	 */
	public function create(CreateCustomerDto|array $customer): Customer
	{
		$dto = is_array($customer) ? CreateCustomerDto::fromArray($customer) : $customer;

		return $this->transport->post(self::BASE_ROUTE . '/', $dto->toArray(), self::one(Customer::fromArray(...)));
	}

	/**
	 * Get a customer by UUID.
	 */
	public function get(string $customerUUID): Customer
	{
		$this->requireNonEmpty($customerUUID, 'Customer UUID');

		return $this->transport->get(
			self::BASE_ROUTE . '/uuid/' . $this->encode($customerUUID),
			map: self::one(Customer::fromArray(...)),
		);
	}

	/**
	 * Get a customer by the reference you assigned when creating it.
	 *
	 * Lets you resolve a customer from your own identifier without storing QBitFlow's UUID.
	 */
	public function getByReference(string $reference): Customer
	{
		$this->requireNonEmpty($reference, 'Customer reference');

		return $this->transport->get(
			self::BASE_ROUTE . '/reference/' . $this->encode($reference),
			map: self::one(Customer::fromArray(...)),
		);
	}

	/**
	 * Get a customer by email address.
	 */
	public function getByEmail(string $email): Customer
	{
		Validate::required('email', $email);
		Validate::email('email', $email);

		return $this->transport->get(
			self::BASE_ROUTE . '/email/' . $this->encode($email),
			map: self::one(Customer::fromArray(...)),
		);
	}

	/**
	 * List customers, one page at a time.
	 *
	 * @param int|null    $limit  Maximum number of customers per page.
	 * @param string|null $cursor Cursor from a previous page's `nextCursor`.
	 *
	 * @return CursorData<Customer>
	 */
	public function getAll(?int $limit = null, ?string $cursor = null): CursorData
	{
		return $this->transport->get(
			self::BASE_ROUTE . '/all',
			CursorData::queryParams($limit, $cursor),
			map: self::page(Customer::fromArray(...)),
		);
	}

	/**
	 * Update a customer. Only the fields you set are sent.
	 *
	 * @param UpdateCustomerDto|array<string,mixed> $customer
	 */
	public function update(string $customerUUID, UpdateCustomerDto|array $customer): Customer
	{
		$this->requireNonEmpty($customerUUID, 'Customer UUID');

		$dto = is_array($customer) ? UpdateCustomerDto::fromArray($customer) : $customer;

		return $this->transport->put(
			self::BASE_ROUTE . '/' . $this->encode($customerUUID),
			$dto->toArray(),
			self::one(Customer::fromArray(...)),
		);
	}

	/**
	 * Delete a customer.
	 */
	public function delete(string $customerUUID): SuccessResponse
	{
		$this->requireNonEmpty($customerUUID, 'Customer UUID');

		return $this->transport->delete(
			self::BASE_ROUTE . '/uuid/' . $this->encode($customerUUID),
			self::one(SuccessResponse::fromArray(...)),
		);
	}
}
