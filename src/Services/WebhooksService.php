<?php

declare(strict_types=1);

namespace QBitFlow\Services;

use Closure;
use DateTimeInterface;
use QBitFlow\Events\Event;
use QBitFlow\Exceptions\BadRequestException;
use QBitFlow\Exceptions\WebhookSignatureException;
use QBitFlow\Http\Requester;
use QBitFlow\RequestOptions;
use QBitFlow\Support\Validator;
use QBitFlow\Webhooks\Webhook;
use QBitFlow\Webhooks\WebhookRouter;

/**
 * Verifies webhook deliveries; `endpoints` and `events` manage the endpoints and the event log.
 * `verify()`, `constructEvent()` and `parseEvent()` are local, and also exist without a client
 * on {@see Webhook}.
 */
final class WebhooksService extends Service
{
	/** Manages the webhook endpoints (`/webhooks/endpoints…`). */
	public readonly WebhookEndpointsService $endpoints;

	/** Reads the event log (`/webhooks/events…`). */
	public readonly WebhookEventsService $events;

	/**
	 * @internal Created by {@see \QBitFlow\QBitFlow}.
	 */
	public function __construct(Requester $requester)
	{
		parent::__construct($requester);
		$this->endpoints = new WebhookEndpointsService($requester);
		$this->events = new WebhookEventsService($requester);
	}

	/**
	 * A webhook router for the endpoint of `$secret` (its `whsec_…` secret): verifies, parses
	 * and dispatches deliveries, and says what to answer. See {@see WebhookRouter}.
	 *
	 * ```php
	 * $client->webhooks->router(getenv('QBITFLOW_WEBHOOK_SECRET'))
	 *     ->on(EventType::PAYMENT_COMPLETED, fn (PaymentCompleted $data) => fulfil($data->reference))
	 *     ->handleGlobals();
	 * ```
	 */
	public function router(#[\SensitiveParameter] string $secret, int $tolerance = Webhook::DEFAULT_TOLERANCE): WebhookRouter
	{
		return new WebhookRouter($secret, $tolerance);
	}

	/**
	 * Checks a delivery's signature locally: see {@see Webhook::verify()}.
	 *
	 * @param (Closure(): (int|DateTimeInterface))|null $now
	 */
	public function verify(string $rawBody, string $signatureHeader, string $secret, int $tolerance = Webhook::DEFAULT_TOLERANCE, ?Closure $now = null): void
	{
		Webhook::verify($rawBody, $signatureHeader, $secret, $tolerance, $now);
	}

	/**
	 * Verifies and parses a delivery: see {@see Webhook::constructEvent()}.
	 *
	 * @param (Closure(): (int|DateTimeInterface))|null $now
	 */
	public function constructEvent(string $rawBody, string $signatureHeader, string $secret, int $tolerance = Webhook::DEFAULT_TOLERANCE, ?Closure $now = null): Event
	{
		return Webhook::constructEvent($rawBody, $signatureHeader, $secret, $tolerance, $now);
	}

	/** Parses a body without verifying it: see {@see Webhook::parseEvent()}. */
	public function parseEvent(string $rawBody): Event
	{
		return Webhook::parseEvent($rawBody);
	}

	/**
	 * Has the API check a delivery's signature (`POST /webhooks/verify`) with the endpoint's
	 * current secret (useful when you do not store it): `$endpointUuid` is the endpoint that
	 * received it, `$rawBody` the body exactly as received. Not retried. Parse the verified body
	 * with `parseEvent()`.
	 *
	 * Returns when the signature is valid. A mismatch, a malformed header or a stale timestamp
	 * (400 `invalid_signature`) is a {@see WebhookSignatureException} with reason
	 * `invalidSignature`; an empty header one with reason `missingHeader` (nothing is sent).
	 * Other errors as usual (another space's endpoint: 404).
	 */
	public function verifyRemote(string $endpointUuid, string $rawBody, string $signatureHeader, ?RequestOptions $options = null): void
	{
		Validator::pathUuid('endpointUuid', $endpointUuid);
		if ($rawBody === '') {
			throw Validator::fieldError('body', 'is required');
		}
		if (strlen($rawBody) > Webhook::MAX_BODY_BYTES) {
			throw Validator::fieldError('body', 'must be at most 1 MiB');
		}
		if ($signatureHeader === '') {
			throw new WebhookSignatureException(WebhookSignatureException::REASON_MISSING_HEADER, 'missing ' . Webhook::SIGNATURE_HEADER . ' header');
		}

		try {
			$this->requester->void('POST', '/webhooks/verify', [
				'endpointUuid' => $endpointUuid,
				'body' => $rawBody,
				'signature' => $signatureHeader,
			], $options);
		} catch (BadRequestException $e) {
			if ($e->apiCode !== 'invalid_signature') {
				throw $e;
			}

			throw new WebhookSignatureException(
				WebhookSignatureException::REASON_INVALID_SIGNATURE,
				$e->errorMessage,
				$e->status,
				$e->apiCode,
				$e->details,
				$e->requestId,
				$e->fieldErrors,
				$e->rawBody,
			);
		}
	}
}
