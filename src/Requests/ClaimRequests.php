<?php

declare(strict_types=1);

namespace QBitFlow\Requests;

use QBitFlow\Dto\ClaimFunds;
use QBitFlow\Dto\ClaimRequestResponse;
use QBitFlow\Dto\SuccessResponse;
use QBitFlow\Support\Cast;

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

		return ClaimRequestResponse::fromArray(
			$this->transport->post(self::BASE_ROUTE . '/request', ['userId' => $userId]),
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

		return ClaimRequestResponse::fromArray(
			$this->transport->get(self::BASE_ROUTE . '/request/' . $userId),
		);
	}

	/**
	 * Get every active claim-fund entry: what your organization currently owes users who
	 * have claimed their accounts.
	 *
	 * @return list<ClaimFunds>
	 */
	public function getFunds(): array
	{
		return Cast::listOf(
			$this->transport->get(self::BASE_ROUTE . '/funds'),
			ClaimFunds::fromArray(...),
		);
	}

	/**
	 * Compute a user's claim funds now instead of waiting for the hourly job.
	 *
	 * Test mode only, and requires an admin or owner key. Lets you exercise the whole
	 * claim flow end to end without waiting.
	 */
	public function triggerTestClaimFunds(int $userId): SuccessResponse
	{
		$this->requirePositive($userId, 'User ID');

		return SuccessResponse::fromArray(
			$this->transport->get(self::BASE_ROUTE . '/funds/test-trigger/' . $userId),
		);
	}
}
