<?php

declare(strict_types=1);

namespace QBitFlow\Requests;

use QBitFlow\Dto\Session\CreateSubscriptionSessionDto;
use QBitFlow\Dto\Session\LinkResponse;
use QBitFlow\Dto\Session\SessionCheckout;
use QBitFlow\Dto\Session\StatusLinkResponse;
use QBitFlow\Dto\Subscription;
use QBitFlow\Dto\SubscriptionHistory;
use QBitFlow\Dto\SuccessResponse;
use QBitFlow\Http\Transport;
use QBitFlow\Support\Cast;

/**
 * Recurring on-chain subscriptions.
 */
final class SubscriptionRequests extends Request
{
	private const BASE_ROUTE = '/transaction/subscription';

	private readonly SessionRequests $sessions;

	public function __construct(Transport $transport)
	{
		parent::__construct($transport);

		$this->sessions = new SessionRequests($transport);
	}

	/**
	 * Create a subscription session and get back the link to send your customer.
	 *
	 * A subscription always bills against an existing product, so supply either
	 * `productId` or `productReference`.
	 *
	 * ```php
	 * $subscription = $client->subscriptions->createSession(new CreateSubscriptionSessionDto(
	 *     frequency: Duration::months(1),
	 *     productId: 1,
	 *     trialPeriod: Duration::days(7),
	 *     customerUUID: 'customer-uuid',
	 * ));
	 * ```
	 *
	 * @param CreateSubscriptionSessionDto|array<string,mixed> $session
	 */
	public function createSession(CreateSubscriptionSessionDto|array $session): LinkResponse
	{
		$dto = is_array($session) ? CreateSubscriptionSessionDto::fromArray($session) : $session;
		$dto->validate();

		return $this->sessions->createForSubscription($dto);
	}

	/**
	 * Get a subscription session by UUID.
	 */
	public function getSession(string $sessionUUID, ?bool $closeToExpireError = null): SessionCheckout
	{
		return $this->sessions->get($sessionUUID, $closeToExpireError);
	}

	/**
	 * Get a subscription by UUID.
	 */
	public function get(string $subscriptionUUID): Subscription
	{
		$this->requireNonEmpty($subscriptionUUID, 'Subscription UUID');

		return Subscription::fromArray(
			$this->transport->get(self::BASE_ROUTE . '/' . $this->encode($subscriptionUUID)),
		);
	}

	/**
	 * Get a subscription by the reference you assigned when creating the session.
	 */
	public function getByReference(string $reference): Subscription
	{
		$this->requireNonEmpty($reference, 'Subscription reference');

		return Subscription::fromArray($this->transport->get(
			self::BASE_ROUTE . '/reference/subscription/' . $this->encode($reference),
		));
	}

	/**
	 * Get every billing record for a subscription.
	 *
	 * @return list<SubscriptionHistory>
	 */
	public function getPaymentHistory(string $subscriptionUUID): array
	{
		$this->requireNonEmpty($subscriptionUUID, 'Subscription UUID');

		return Cast::listOf(
			$this->transport->get(self::BASE_ROUTE . '/history/' . $this->encode($subscriptionUUID)),
			SubscriptionHistory::fromArray(...),
		);
	}

	/**
	 * Cancel a subscription immediately, bypassing the usual signed-cancellation flow.
	 *
	 * Normally a subscriber cancels by signing a message. This exists for cases where the
	 * merchant must act alone — suspicious activity, or an admin-initiated cancellation.
	 */
	public function forceCancel(string $subscriptionUUID): SuccessResponse
	{
		$this->requireNonEmpty($subscriptionUUID, 'Subscription UUID');

		return SuccessResponse::fromArray($this->transport->get(
			self::BASE_ROUTE . '/processing/force-cancel/' . $this->encode($subscriptionUUID),
		));
	}

	/**
	 * Run a billing cycle immediately. Test-mode subscriptions only.
	 *
	 * Live subscriptions bill automatically on schedule; this lets you exercise your
	 * webhook handling without waiting for one.
	 */
	public function executeTestBilling(string $subscriptionUUID): StatusLinkResponse
	{
		$this->requireNonEmpty($subscriptionUUID, 'Subscription UUID');

		return StatusLinkResponse::fromArray($this->transport->get(
			self::BASE_ROUTE . '/processing/execute-billing/' . $this->encode($subscriptionUUID),
		));
	}
}
