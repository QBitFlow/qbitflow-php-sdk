<?php

declare(strict_types=1);

namespace QBitFlow\Requests;

use QBitFlow\Dto\CreateUserDto;
use QBitFlow\Dto\SuccessResponse;
use QBitFlow\Dto\UpdateUserDto;
use QBitFlow\Dto\User;
use QBitFlow\Support\Validate;

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

		return $this->transport->post(self::BASE_ROUTE . '/', $dto->toArray(), self::one(User::fromArray(...)));
	}

	/**
	 * Get the user the current API key belongs to.
	 */
	public function get(): User
	{
		return $this->transport->get(self::BASE_ROUTE . '/', map: self::one(User::fromArray(...)));
	}

	/**
	 * Get every user in the organization. Requires an admin or owner key.
	 *
	 * @return list<User>
	 */
	public function getAll(): array
	{
		return $this->transport->get(self::BASE_ROUTE . '/all', map: self::list(User::fromArray(...)));
	}

	/**
	 * Get a user by ID. Requires an admin or owner key.
	 */
	public function getById(int $userId): User
	{
		$this->requirePositive($userId, 'User ID');

		return $this->transport->get(self::BASE_ROUTE . '/id/' . $userId, map: self::one(User::fromArray(...)));
	}

	/**
	 * Get a user by email address. Requires an admin or owner key.
	 */
	public function getByEmail(string $email): User
	{
		Validate::required('email', $email);
		Validate::email('email', $email);

		return $this->transport->get(
			self::BASE_ROUTE . '/email/' . $this->encode($email),
			map: self::one(User::fromArray(...)),
		);
	}

	/**
	 * Update a user.
	 *
	 * Setting `organizationFeeBps` requires admin authority — an admin or owner key, or an
	 * organization-level key acting through `onBehalfOf()`. A caller without it that sends
	 * the field is rejected with a 403 ({@see \QBitFlow\Exceptions\ForbiddenException}).
	 *
	 * @param UpdateUserDto|array<string,mixed> $user
	 */
	public function update(int $userId, UpdateUserDto|array $user): User
	{
		$this->requirePositive($userId, 'User ID');

		$dto = is_array($user) ? UpdateUserDto::fromArray($user) : $user;

		return $this->transport->put(self::BASE_ROUTE . '/' . $userId, $dto->toArray(), self::one(User::fromArray(...)));
	}

	/**
	 * Delete a user. Requires an admin or owner key.
	 */
	public function delete(int $userId): SuccessResponse
	{
		$this->requirePositive($userId, 'User ID');

		return $this->transport->delete(self::BASE_ROUTE . '/' . $userId, self::one(SuccessResponse::fromArray(...)));
	}
}
