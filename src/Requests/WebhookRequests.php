<?php

declare(strict_types=1);

namespace QBitFlow\Requests;

use JsonException;
use QBitFlow\Exceptions\ForbiddenException;
use QBitFlow\Exceptions\UnauthorizedException;
use QBitFlow\Exceptions\ValidationException;

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

	/** Header carrying the HMAC signature of the delivery. */
	public const HEADER_SIGNATURE = 'X-Webhook-Signature-256';

	/** Header carrying the timestamp the delivery was signed at. */
	public const HEADER_TIMESTAMP = 'X-Webhook-Timestamp';

	/** Header carrying the unique ID of the delivery. */
	public const HEADER_WEBHOOK_ID = 'X-Webhook-ID';

	/**
	 * Webhook ID used by the dashboard's "Test the endpoint" action.
	 *
	 * When the incoming {@see WebhookRequests::HEADER_WEBHOOK_ID} equals this value the
	 * payload is a probe carrying fake data — answer 200 immediately and skip your normal
	 * processing.
	 */
	public const TEST_WEBHOOK_ID = 'test-webhook-id';

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
	 * Verify that a delivery genuinely came from QBitFlow.
	 *
	 * Pass the **raw** request body. Do not decode and re-encode it first: any change in
	 * key order or whitespace changes the signature and verification will fail.
	 *
	 * ```php
	 * $raw = file_get_contents('php://input');
	 *
	 * $valid = $client->webhooks->verify(
	 *     $raw,
	 *     $_SERVER['HTTP_X_WEBHOOK_SIGNATURE_256'] ?? '',
	 *     $_SERVER['HTTP_X_WEBHOOK_TIMESTAMP'] ?? '',
	 * );
	 * ```
	 *
	 * Returns false when QBitFlow rejects the signature. Network and server failures are
	 * rethrown rather than reported as an invalid signature, so an outage is never
	 * mistaken for a forgery — let those bubble up, answer non-200, and the delivery is
	 * retried.
	 *
	 * @param string|array<array-key,mixed> $payload Raw request body, or the decoded payload.
	 *
	 * @throws ValidationException If the payload is not valid JSON.
	 * @throws \QBitFlow\Exceptions\NetworkException If the verification request never landed.
	 * @throws \QBitFlow\Exceptions\ServerException If QBitFlow failed to answer.
	 */
	public function verify(string|array $payload, string $signature, string $timestamp): bool
	{
		if (is_string($payload)) {
			try {
				$decoded = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
			} catch (JsonException $e) {
				throw new ValidationException(
					'Webhook payload is not valid JSON: ' . $e->getMessage(),
					previous: $e,
				);
			}

			if (! is_array($decoded)) {
				throw new ValidationException('Webhook payload must decode to an object');
			}

			$payload = $decoded;
		}

		try {
			$this->transport->post(self::BASE_ROUTE . '/verify', [
				'payload' => $payload,
				'receivedSignature' => $signature,
				'receivedTimestamp' => $timestamp,
			]);

			return true;
		} catch (ValidationException | UnauthorizedException | ForbiddenException) {
			// The API rejects a bad signature with a 4xx; that is a verification failure,
			// not an error the caller needs to handle.
			return false;
		}
	}
}
