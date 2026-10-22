<?php

declare(strict_types=1);

namespace QBitFlow\Services;

use QBitFlow\Enums\CheckoutSessionStatusValue;
use QBitFlow\Http\Requester;
use QBitFlow\Models\CreatedCheckoutSession;
use QBitFlow\Models\CheckoutSessionStatus;
use QBitFlow\Params\CreatePaymentSessionParams;
use QBitFlow\Params\CreateSubscriptionSessionParams;
use QBitFlow\RequestOptions;
use QBitFlow\Support\Validator;

/**
 * Creates checkout sessions (one-time payments and subscriptions), reads their status and
 * expires them (`/checkout-sessions…`).
 */
final class CheckoutSessionsService extends Service
{
	/** How long {@see waitForCompletion()} waits by default (seconds). */
	public const DEFAULT_WAIT_TIMEOUT = 600.0;

	/** How often {@see waitForCompletion()} checks by default (seconds); at least every 1 s. */
	public const DEFAULT_WAIT_INTERVAL = 3.0;

	/**
	 * Opens a one-time payment checkout session (`POST /checkout-sessions/payment`, 201): its
	 * link, its `pay@…` id and its expiry. Sends an Idempotency-Key and is retried on transient
	 * failures.
	 *
	 * Send the customer to `link`. The payment exists, with the same id, once the customer's
	 * transaction is confirmed: fulfil on the `payment.completed` webhook (or `get()`
	 * `completed`), give up on `checkout.expired`. `successUrl` and `cancelUrl` may carry
	 * {@see \QBitFlow\Placeholders::UUID} and {@see \QBitFlow\Placeholders::TRANSACTION_TYPE},
	 * filled in on redirect.
	 *
	 * `fees` adds lines to the price (a tax, shipping, QBitFlow's processing fee;
	 * {@see \QBitFlow\Params\CheckoutFees}): the customer pays the price plus every line (the
	 * payment's `amountUsd`), and the network fee on top of that. `metadata` is copied onto the
	 * payment and into its webhooks.
	 *
	 * Errors: 400 validation_failed (test mode caps the amount, fees included, at `"5"`,
	 * `details.max`; live mode needs https URLs); 404 for an unknown product or customer; 409
	 * `unique_violation` on reference; 409 `merchant_not_ready` (`details.reason`) when the space's
	 * wallets accept no currency; 410 `merchant_closed` for a removed member's or a closed
	 * organization's space.
	 */
	public function createPayment(CreatePaymentSessionParams $params, ?RequestOptions $options = null): CreatedCheckoutSession
	{
		$params->validate();

		return $this->requester->call('POST', '/checkout-sessions/payment', Requester::one(CreatedCheckoutSession::fromArray(...)),
			body: $params->toArray(), idempotent: true, options: $options);
	}

	/**
	 * Opens a subscription checkout session (`POST /checkout-sessions/subscription`, 201): its
	 * link, its `sub@…` id and its expiry. Sends an Idempotency-Key and is retried on transient
	 * failures.
	 *
	 * The subscription exists, with the same id, once the customer signed (a free trial) or paid:
	 * act on `subscription.created`. Terms left unset come from a subscription product.
	 *
	 * `fees` (optional) adds lines to the price, as for {@see createPayment()}, locked with it:
	 * every bill charges the price plus every line (the subscription's `priceUsd` plus its
	 * `fees`), and the network fee on top of that.
	 *
	 * Errors as {@see createPayment()}, plus 400 on `frequency` / `trialPeriod` (the frequency
	 * minimum is 1 hour live, 5 minutes test) and on `priceUsd` for a live subscription at
	 * `"0.25"` or less (`details.min`); a member's key needs the `members.subscriptions` policy
	 * (403 `policy_disabled`); 409 `merchant_not_ready` with `details.reason` `fundsHeld` for a
	 * member whose funds the organization holds (a held member sells one-time payments only,
	 * until `members->trust()`).
	 */
	public function createSubscription(CreateSubscriptionSessionParams $params, ?RequestOptions $options = null): CreatedCheckoutSession
	{
		$params->validate();

		return $this->requester->call('POST', '/checkout-sessions/subscription', Requester::one(CreatedCheckoutSession::fromArray(...)),
			body: $params->toArray(), idempotent: true, options: $options);
	}

	/**
	 * Where a checkout session stands (`GET /checkout-sessions/:uuid`; the session's `pay@…` or
	 * `sub@…` id, or its bare UUID): `created` (waiting for its customer), `waitingConfirmation`,
	 * `completed` or `expired` (the only final ones). It covers both phases: the session, then the
	 * payment or subscription it created.
	 *
	 * A failed attempt is `created` with `lastAttempt` set: the customer may still pay from the
	 * same checkout, so never cancel an order on it. An expired session answers `expired` for as
	 * long as QBitFlow keeps the record, then 404 like an unknown id or another space's session.
	 */
	public function get(string $uuid, ?RequestOptions $options = null): CheckoutSessionStatus
	{
		Validator::pathTxId('uuid', $uuid);

		return $this->requester->call('GET', Requester::path('/checkout-sessions/%s', $uuid),
			Requester::one(CheckoutSessionStatus::fromArray(...)), options: $options);
	}

	/**
	 * Polls {@see get()} every `$interval` seconds (at least 1) until the session is `completed`
	 * or `expired`, and returns that status. When `$timeout` seconds elapse first, returns the
	 * last status seen (not final: check `->status`). Errors of `get()` are thrown (a 404
	 * included).
	 *
	 * For scripts, tests and back-office jobs: fulfil orders on the `payment.completed` webhook.
	 *
	 * ```php
	 * $status = $client->checkoutSessions->waitForCompletion($session->uuid, timeout: 120);
	 * if ($status->status === CheckoutSessionStatusValue::COMPLETED) { … }
	 * ```
	 *
	 * @param float $timeout  Seconds to wait at most (default 600; 0 or less: the default). The last
	 *                        wait is shortened to the time left, then one last check is made.
	 * @param float $interval Seconds between two checks (default 3; less than 1 counts as 1).
	 *
	 * @throws \QBitFlow\Exceptions\ValidationException For a bad uuid, a non-finite timeout or interval.
	 */
	public function waitForCompletion(string $uuid, float $timeout = self::DEFAULT_WAIT_TIMEOUT, float $interval = self::DEFAULT_WAIT_INTERVAL, ?RequestOptions $options = null): CheckoutSessionStatus
	{
		Validator::pathTxId('uuid', $uuid);
		if (! is_finite($timeout)) {
			throw Validator::fieldError('timeout', 'must be a finite number of seconds');
		}
		if ($timeout <= 0) {
			$timeout = self::DEFAULT_WAIT_TIMEOUT;
		}
		if (! is_finite($interval)) {
			throw Validator::fieldError('interval', 'must be a finite number of seconds');
		}
		$interval = max(1.0, $interval);

		// Time spent: the requests (measured) plus the waits (counted, so an injected sleep in
		// tests is accounted for without waiting).
		$elapsed = 0.0;
		while (true) {
			$started = hrtime(true);
			$status = $this->get($uuid, $options);
			$elapsed += (hrtime(true) - $started) / 1e9;
			if ($status->status === CheckoutSessionStatusValue::COMPLETED || $status->status === CheckoutSessionStatusValue::EXPIRED) {
				return $status;
			}
			$remaining = $timeout - $elapsed;
			if ($remaining <= 0) {
				return $status;
			}
			$wait = min($interval, $remaining);
			$this->requester->transport->sleep($wait);
			$elapsed += $wait;
		}
	}

	/**
	 * Ends a checkout session now (`POST /checkout-sessions/:uuid/expire`) and returns its
	 * status, `expired`, with `expiresAt` the time it was expired (`checkout.expired` follows).
	 * Not retried.
	 *
	 * Errors: 409 `tx_already_sent` once the customer paid or is paying.
	 */
	public function expire(string $uuid, ?RequestOptions $options = null): CheckoutSessionStatus
	{
		Validator::pathTxId('uuid', $uuid);

		return $this->requester->call('POST', Requester::path('/checkout-sessions/%s/expire', $uuid),
			Requester::one(CheckoutSessionStatus::fromArray(...)), options: $options);
	}
}
