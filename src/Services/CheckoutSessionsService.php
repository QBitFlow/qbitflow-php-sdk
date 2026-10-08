<?php

declare(strict_types=1);

namespace QBitFlow\Services;

use QBitFlow\Enums\CheckoutSessionStatusValue;
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
	/** How long {@see waitForCompletion()} waits by default (seconds). */
	public const DEFAULT_WAIT_TIMEOUT = 600.0;

	/** How often {@see waitForCompletion()} checks by default (seconds); at least every 1 s. */
	public const DEFAULT_WAIT_INTERVAL = 3.0;

	/**
	 * Opens a one-time payment checkout (`POST /transaction/session-checkout/new/payment`, 201):
	 * its link, its `pay@…` id and its expiry. Sends an Idempotency-Key and is retried on
	 * transient failures.
	 *
	 * Send the customer to `link`; fulfil on the `payment.completed` webhook (or `getStatus()`
	 * `completed`), give up on `checkout.expired`. `fees` adds your lines and QBitFlow's
	 * processing fee to the price ({@see \QBitFlow\Params\CheckoutFees}): the customer pays the
	 * price plus the fees, the network fee on top. Errors: 400 validation_failed (test mode caps
	 * the amount, fees included, at 5 USD: on `price`, or on `fees` when the fees cross it); 404
	 * for an unknown product or customer; 409 `merchant_not_ready`.
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
	 * Polls {@see getStatus()} every `$interval` seconds (at least 1) until the session is
	 * `completed` or `expired`, and returns that status. When `$timeout` seconds elapse first,
	 * returns the last status seen (not final: check `->status`). Errors of `getStatus()` are
	 * thrown (a 404 included).
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
			$status = $this->getStatus($uuid, $options);
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
