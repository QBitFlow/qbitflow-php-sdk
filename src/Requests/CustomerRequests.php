<?php

declare(strict_types=1);

namespace QBitFlow\Requests;

use QBitFlow\Dto\CreateCustomerDto;
use QBitFlow\Dto\Customer;
use QBitFlow\Dto\SuccessResponse;
use QBitFlow\Dto\UpdateCustomerDto;
use QBitFlow\Exceptions\ValidationException;
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

		return Customer::fromArray($this->transport->post(self::BASE_ROUTE . '/', $dto->toArray()));
	}

	/**
	 * Get a customer by UUID.
	 */
	public function get(string $customerUUID): Customer
	{
		$this->requireNonEmpty($customerUUID, 'Customer UUID');

		return Customer::fromArray(
			$this->transport->get(self::BASE_ROUTE . '/uuid/' . $this->encode($customerUUID)),
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

		return Customer::fromArray(
			$this->transport->get(self::BASE_ROUTE . '/reference/' . $this->encode($reference)),
		);
	}

	/**
	 * Get a customer by email address.
	 */
	public function getByEmail(string $email): Customer
	{
		if (! str_contains($email, '@')) {
			throw new ValidationException('Valid email is required');
		}

		return Customer::fromArray(
			$this->transport->get(self::BASE_ROUTE . '/email/' . $this->encode($email)),
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
		return CursorData::fromArray(
			$this->transport->get(self::BASE_ROUTE . '/all', CursorData::queryParams($limit, $cursor)),
			Customer::fromArray(...),
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

		return Customer::fromArray($this->transport->put(
			self::BASE_ROUTE . '/' . $this->encode($customerUUID),
			$dto->toArray(),
		));
	}

	/**
	 * Delete a customer.
	 */
	public function delete(string $customerUUID): SuccessResponse
	{
		$this->requireNonEmpty($customerUUID, 'Customer UUID');

		return SuccessResponse::fromArray(
			$this->transport->delete(self::BASE_ROUTE . '/uuid/' . $this->encode($customerUUID)),
		);
	}
}
