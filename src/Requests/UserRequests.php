<?php

declare(strict_types=1);

namespace QBitFlow\Requests;

use QBitFlow\Dto\CreateUserDto;
use QBitFlow\Dto\SuccessResponse;
use QBitFlow\Dto\UpdateUserDto;
use QBitFlow\Dto\User;
use QBitFlow\Exceptions\ValidationException;
use QBitFlow\Support\Cast;

/**
 * Manage the users in your organization.
 *
 * Most of these endpoints require an admin or owner API key.
 */
final class UserRequests extends Request
{
	private const BASE_ROUTE = '/user';

	/**
	 * Create a user. Requires an admin or owner key.
	 *
	 * No password is set here — the user sets one through the claim flow. See
	 * {@see ClaimRequests::createRequest()}.
	 *
	 * @param CreateUserDto|array<string,mixed> $user
	 */
	public function create(CreateUserDto|array $user): User
	{
		$dto = is_array($user) ? CreateUserDto::fromArray($user) : $user;

		return User::fromArray($this->transport->post(self::BASE_ROUTE . '/', $dto->toArray()));
	}

	/**
	 * Get the user the current API key belongs to.
	 */
	public function get(): User
	{
		return User::fromArray($this->transport->get(self::BASE_ROUTE . '/'));
	}

	/**
	 * Get every user in the organization. Requires an admin or owner key.
	 *
	 * @return list<User>
	 */
	public function getAll(): array
	{
		return Cast::listOf($this->transport->get(self::BASE_ROUTE . '/all'), User::fromArray(...));
	}

	/**
	 * Get a user by ID. Requires an admin or owner key.
	 */
	public function getById(int $userId): User
	{
		$this->requirePositive($userId, 'User ID');

		return User::fromArray($this->transport->get(self::BASE_ROUTE . '/id/' . $userId));
	}

	/**
	 * Get a user by email address. Requires an admin or owner key.
	 */
	public function getByEmail(string $email): User
	{
		if (! str_contains($email, '@')) {
			throw new ValidationException('Valid email is required');
		}

		return User::fromArray($this->transport->get(self::BASE_ROUTE . '/email/' . $this->encode($email)));
	}

	/**
	 * Update a user.
	 *
	 * The organization fee is applied only when the call is made with an admin or owner
	 * key; it is ignored when a user updates themselves.
	 *
	 * @param UpdateUserDto|array<string,mixed> $user
	 */
	public function update(int $userId, UpdateUserDto|array $user): User
	{
		$this->requirePositive($userId, 'User ID');

		$dto = is_array($user) ? UpdateUserDto::fromArray($user) : $user;

		return User::fromArray($this->transport->put(self::BASE_ROUTE . '/' . $userId, $dto->toArray()));
	}

	/**
	 * Delete a user. Requires an admin or owner key.
	 */
	public function delete(int $userId): SuccessResponse
	{
		$this->requirePositive($userId, 'User ID');

		return SuccessResponse::fromArray($this->transport->delete(self::BASE_ROUTE . '/' . $userId));
	}
}
