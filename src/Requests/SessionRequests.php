<?php

declare(strict_types=1);

namespace QBitFlow\Requests;

use QBitFlow\Dto\Session\CreatePaymentSessionDto;
use QBitFlow\Dto\Session\CreateSubscriptionSessionDto;
use QBitFlow\Dto\Session\LinkResponse;
use QBitFlow\Dto\Session\SessionCheckout;

/**
 * Checkout-session endpoints, shared by {@see PaymentRequests} and {@see SubscriptionRequests}.
 *
 * You rarely need this service directly — create sessions through
 * `$client->oneTimePayments` or `$client->subscriptions` instead.
 */
final class SessionRequests extends Request
{
	private const BASE_ROUTE = '/transaction/session-checkout';

	/**
	 * Create a one-time payment session.
	 */
	public function createForPayment(CreatePaymentSessionDto $session): LinkResponse
	{
		return $this->transport->post(
			self::BASE_ROUTE . '/new/payment',
			$session->toArray(),
			self::one(LinkResponse::fromArray(...)),
		);
	}

	/**
	 * Create a subscription session.
	 */
	public function createForSubscription(CreateSubscriptionSessionDto $session): LinkResponse
	{
		return $this->transport->post(
			self::BASE_ROUTE . '/new/subscription',
			$session->toArray(),
			self::one(LinkResponse::fromArray(...)),
		);
	}

	/**
	 * Get a session by UUID.
	 *
	 * This endpoint is public — it backs the checkout page itself — but it honours the API
	 * key the SDK always sends, so the full session (organization ID, fees) comes back.
	 *
	 * @param bool|null $closeToExpireError Whether the API should error when the session is
	 *                                      close to expiry. Defaults to true server-side.
	 */
	public function get(string $sessionUUID, ?bool $closeToExpireError = null): SessionCheckout
	{
		$this->requireNonEmpty($sessionUUID, 'Session UUID');

		$params = $closeToExpireError === null ? [] : ['closeToExpireError' => $closeToExpireError];

		return $this->transport->get(
			self::BASE_ROUTE . '/' . $this->encode($sessionUUID),
			$params,
			map: self::one(SessionCheckout::discriminate(...)),
		);
	}
}
