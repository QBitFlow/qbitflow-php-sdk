<?php

declare(strict_types=1);

namespace QBitFlow\Requests;

use QBitFlow\Dto\ApiKey;

/**
 * Read access to the API keys in your organization.
 *
 * Creating and deleting keys is a dashboard-only (JWT-authenticated) operation on the
 * API — it cannot be done with an API key, which is the only credential this SDK uses.
 * Manage keys from the QBitFlow dashboard instead.
 */
final class ApiKeyRequests extends Request
{
	private const BASE_ROUTE = '/api-key';

	/**
	 * Get the API keys visible to the current key: a user-level key sees only its user's
	 * keys; an admin, owner or organization-level key sees every key in the organization.
	 *
	 * @return list<ApiKey>
	 */
	public function getAll(): array
	{
		return $this->transport->get(self::BASE_ROUTE . '/', map: self::list(ApiKey::fromArray(...)));
	}

	/**
	 * Get the API keys belonging to a specific user. Requires an admin or owner key.
	 *
	 * @return list<ApiKey>
	 */
	public function getForUser(int $userId): array
	{
		$this->requirePositive($userId, 'User ID');

		return $this->transport->get(self::BASE_ROUTE . '/user/' . $userId, map: self::list(ApiKey::fromArray(...)));
	}
}
