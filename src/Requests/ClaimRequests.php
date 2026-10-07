<?php

declare(strict_types=1);

namespace QBitFlow\Requests;

use QBitFlow\Dto\ClaimFunds;
use QBitFlow\Dto\ClaimRequestResponse;
use QBitFlow\Dto\SuccessResponse;

/**
 * Account claims and the fund transfers that follow them.
 *
 * You can create users whose earnings your organization holds initially. When you are
 * ready, raise a claim request: the user follows the link, sets a password, connects a
 * wallet, and the accumulated funds become transferable to them.
 */
final class ClaimRequests extends Request
{
	private const BASE_ROUTE = '/user/claim';

	/**
	 * Create a claim request for a user. Requires an admin or owner key.
	 *
	 * ```php
	 * $claim = $client->claims->createRequest(42);
	 *
	 * Mail::to($user)->send(new ClaimYourAccount($claim->link));
	 * ```
	 */
	public function createRequest(int $userId): ClaimRequestResponse
	{
		$this->requirePositive($userId, 'User ID');

		return $this->transport->post(
			self::BASE_ROUTE . '/request',
			['userId' => $userId],
			self::one(ClaimRequestResponse::fromArray(...)),
		);
	}

	/**
	 * Get the claim request that already exists for a user, without creating a new one.
	 *
	 * Useful when a link needs resending. Requires an admin or owner key.
	 */
	public function getRequestByUser(int $userId): ClaimRequestResponse
	{
		$this->requirePositive($userId, 'User ID');

		return $this->transport->get(
			self::BASE_ROUTE . '/request/' . $userId,
			map: self::one(ClaimRequestResponse::fromArray(...)),
		);
	}

	/**
	 * Get every active claim-fund entry: what your organization currently owes the users
	 * it provisioned, per user.
	 *
	 * @return list<ClaimFunds>
	 */
	public function getFunds(): array
	{
		return $this->transport->get(self::BASE_ROUTE . '/funds', map: self::list(ClaimFunds::fromArray(...)));
	}

	/**
	 * Compute a user's claim funds now instead of waiting for the hourly job.
	 *
	 * Test mode only, and requires an admin or owner key. Lets you exercise the whole
	 * claim flow end to end without waiting. Although the route is a GET, it creates a
	 * claim-fund entry, so it is never retried.
	 */
	public function triggerTestClaimFunds(int $userId): SuccessResponse
	{
		$this->requirePositive($userId, 'User ID');

		return $this->transport->get(
			self::BASE_ROUTE . '/funds/test-trigger/' . $userId,
			retry: false,
			map: self::one(SuccessResponse::fromArray(...)),
		);
	}
}
