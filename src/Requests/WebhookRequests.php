<?php

declare(strict_types=1);

namespace QBitFlow\Requests;

use ArrayObject;
use JsonException;
use QBitFlow\Exceptions\ForbiddenException;
use QBitFlow\Exceptions\UnauthorizedException;
use QBitFlow\Exceptions\ValidationException;
use QBitFlow\Webhooks\CanonicalJson;
use QBitFlow\Webhooks\WebhookVerifier;

/**
 * Verify the webhooks QBitFlow sends you.
 *
 * Webhook URLs are configured in the dashboard under Settings → Webhooks, with separate
 * Test and Live endpoints — they can no longer be set per session. Every delivery carries
 * the {@see WebhookRequests::HEADER_SIGNATURE}, {@see WebhookRequests::HEADER_TIMESTAMP}
 * and {@see WebhookRequests::HEADER_WEBHOOK_ID} headers. Always verify before acting on
 * a payload, and always answer 200 — any other status makes QBitFlow retry.
 */
final class WebhookRequests extends Request
{
	private const BASE_ROUTE = '/webhooks';

	/** Header carrying the HMAC signature of the delivery (`sha256=<hex>`). */
	public const HEADER_SIGNATURE = WebhookVerifier::HEADER_SIGNATURE;

	/** Header carrying the timestamp the delivery was signed at, in unix seconds. */
	public const HEADER_TIMESTAMP = WebhookVerifier::HEADER_TIMESTAMP;

	/** Header carrying the transaction id of the delivery, e.g. `pay@<uuid>`. */
	public const HEADER_WEBHOOK_ID = WebhookVerifier::HEADER_WEBHOOK_ID;

	/**
	 * Webhook ID used by the dashboard's "Test the endpoint" action.
	 *
	 * When the incoming {@see WebhookRequests::HEADER_WEBHOOK_ID} equals this value the
	 * payload is a probe carrying fake data. Verify it like any other delivery first — the
	 * probe is signed like a normal delivery, and checking it end to end is the point of
	 * the dashboard button — then answer 200 and skip your normal processing.
	 */
	public const TEST_WEBHOOK_ID = WebhookVerifier::TEST_WEBHOOK_ID;

	/** Name of the signature header, for pulling it off an incoming request. */
	public function signatureHeader(): string
	{
		return self::HEADER_SIGNATURE;
	}

	/** Name of the timestamp header. */
	public function timestampHeader(): string
	{
		return self::HEADER_TIMESTAMP;
	}

	/** Name of the delivery-ID header. */
	public function webhookIdHeader(): string
	{
		return self::HEADER_WEBHOOK_ID;
	}

	/** The webhook ID that marks a dashboard test probe. */
	public function testWebhookId(): string
	{
		return self::TEST_WEBHOOK_ID;
	}

	/**
	 * Whether an incoming delivery is the dashboard's test probe rather than a real event.
	 */
	public function isTestWebhook(?string $webhookId): bool
	{
		return $webhookId === self::TEST_WEBHOOK_ID;
	}

	/**
	 * Verify that a delivery genuinely came from QBitFlow, by asking the API.
	 *
	 * **Argument order:** `verify($payload, $signature, $timestamp)` — signature *before*
	 * timestamp. (The local {@see WebhookVerifier::verify()} takes `($secret, $timestamp,
	 * $signature, $payload)`; named arguments avoid mixing them up.)
	 *
	 * Pass the raw request body — it is forwarded to the API byte for byte, so nothing about
	 * it can change on the way (`{}` stays an object, `-0` keeps its sign, long decimal
	 * strings stay strings). An already-decoded payload works too; it is sent in the same
	 * canonical form the API signs, but an empty PHP array is ambiguous and goes as `[]`.
	 *
	 * ```php
	 * $raw = file_get_contents('php://input');
	 *
	 * $valid = $client->webhooks->verify(
	 *     payload: $raw,
	 *     signature: $_SERVER['HTTP_X_WEBHOOK_SIGNATURE_256'] ?? '',
	 *     timestamp: $_SERVER['HTTP_X_WEBHOOK_TIMESTAMP'] ?? '',
	 * );
	 * ```
	 *
	 * Returns false only when QBitFlow rejects the signature (HTTP 400). Auth, network and
	 * server failures are rethrown rather than reported as an invalid signature, so an
	 * outage is never mistaken for a forgery — let those bubble up, answer non-200, and
	 * the delivery is retried.
	 *
	 * @param string|array<array-key,mixed>|object $payload Raw request body, or the decoded payload.
	 *
	 * @throws ValidationException If the payload is not a JSON object or array, or holds a
	 *                             value JSON cannot represent (NaN, INF). Its status code is null.
	 * @throws UnauthorizedException If the API key is invalid or expired.
	 * @throws ForbiddenException If the API key's role is below `user`.
	 * @throws \QBitFlow\Exceptions\NetworkException If the verification request never landed.
	 * @throws \QBitFlow\Exceptions\ServerException If QBitFlow failed to answer.
	 */
	public function verify(string|array|object $payload, string $signature, string $timestamp): bool
	{
		if (is_string($payload)) {
			$decoded = CanonicalJson::parse($payload);
			$json = trim($payload, " \t\n\r");
		} else {
			$decoded = CanonicalJson::fromValue($payload);
			$json = CanonicalJson::encode($decoded);
		}

		if (! $decoded instanceof ArrayObject && ! is_array($decoded)) {
			throw new ValidationException('Webhook payload must be a JSON object or array');
		}

		try {
			$body = sprintf(
				'{"payload":%s,"receivedSignature":%s,"receivedTimestamp":%s}',
				$json,
				json_encode($signature, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
				json_encode($timestamp, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
			);
		} catch (JsonException $e) {
			throw new ValidationException('Webhook signature or timestamp is not valid UTF-8', previous: $e);
		}

		try {
			$this->transport->postJson(self::BASE_ROUTE . '/verify', $body);

			return true;
		} catch (ValidationException $e) {
			// The API rejects a bad signature with a 400 — a verification result, not an
			// error the caller needs to handle. A 401/403 is deliberately NOT treated the
			// same way: those mean the API key is invalid or under-privileged, and
			// reporting that as a forged signature would hide a misconfiguration behind
			// what looks like an attack.
			if ($e->getStatusCode() === 400) {
				return false;
			}

			throw $e;
		}
	}
}
