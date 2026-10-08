<?php

declare(strict_types=1);

namespace QBitFlow\Services;

use QBitFlow\Http\Requester;
use QBitFlow\Models\CheckoutSession;
use QBitFlow\Models\CheckoutSessionStatus;
use QBitFlow\Params\CreatePaymentSessionParams;
use QBitFlow\Params\CreateSubscriptionSessionParams;
use QBitFlow\RequestOptions;
use QBitFlow\Support\Validator;

/**
 * Creates checkout sessions (one-time payments and subscriptions), reads their status and
 * expires them (`/transaction/session-checkout…`).
 */
final class CheckoutSessionsService extends Service
{
	/**
	 * Opens a one-time payment checkout (`POST /transaction/session-checkout/new/payment`, 201):
	 * its link, its `pay@…` id and its expiry. Sends an Idempotency-Key and is retried on
	 * transient failures.
	 *
	 * Send the customer to `link`; fulfil on the `payment.completed` webhook (or `getStatus()`
	 * `completed`), give up on `checkout.expired`. Errors: 400 validation_failed (test mode caps
	 * the price at 5 USD); 404 for an unknown product or customer; 409 `merchant_not_ready`.
	 */
	public function createPayment(CreatePaymentSessionParams $params, ?RequestOptions $options = null): CheckoutSession
	{
		$params->validate();

		return $this->requester->call('POST', '/transaction/session-checkout/new/payment', Requester::one(CheckoutSession::fromArray(...)),
			body: $params->toArray(), idempotent: true, options: $options);
	}

	/**
	 * Opens a subscription checkout (`POST /transaction/session-checkout/new/subscription`, 201).
	 * The subscription exists, with the same `sub@…` id, once the customer signed (a trial) or
	 * paid: act on `subscription.created`.
	 */
	public function createSubscription(CreateSubscriptionSessionParams $params, ?RequestOptions $options = null): CheckoutSession
	{
		$params->validate();

		return $this->requester->call('POST', '/transaction/session-checkout/new/subscription', Requester::one(CheckoutSession::fromArray(...)),
			body: $params->toArray(), idempotent: true, options: $options);
	}

	/**
	 * Where a checkout session stands (`GET /transaction/session-checkout/:uuid/status`; `pay@…`
	 * or `sub@…`): `created`, `waitingConfirmation`, `completed` or `expired`. A failed attempt is
	 * `created` with `lastAttempt` set: never cancel an order on it.
	 */
	public function getStatus(string $uuid, ?RequestOptions $options = null): CheckoutSessionStatus
	{
		Validator::pathTxId('uuid', $uuid);

		return $this->requester->call('GET', Requester::path('/transaction/session-checkout/%s/status', $uuid),
			Requester::one(CheckoutSessionStatus::fromArray(...)), options: $options);
	}

	/**
	 * Ends a checkout session now (`POST /transaction/session-checkout/:uuid/expire`) and returns
	 * its status (`checkout.expired` follows). Not retried. 409 `tx_already_sent` once paid.
	 */
	public function expire(string $uuid, ?RequestOptions $options = null): CheckoutSessionStatus
	{
		Validator::pathTxId('uuid', $uuid);

		return $this->requester->call('POST', Requester::path('/transaction/session-checkout/%s/expire', $uuid),
			Requester::one(CheckoutSessionStatus::fromArray(...)), options: $options);
	}
}
