<?php

declare(strict_types=1);

namespace QBitFlow\Laravel\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use QBitFlow\Exceptions\ValidationException;
use QBitFlow\QBitFlow;
use QBitFlow\Webhooks\WebhookVerifier;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rejects webhook deliveries that QBitFlow did not sign.
 *
 * Registered as `qbitflow.webhook`, and applied automatically by the route macros:
 *
 * ```php
 * // routes/api.php
 * Route::qbitflowTransactionWebhook('/webhooks/qbitflow/transaction');
 *
 * // or on a route of your own
 * Route::post('/hooks/qbitflow', MyController::class)->middleware('qbitflow.webhook');
 * ```
 *
 * Verification is **local** when `qbitflow.webhook_secret` (`QBITFLOW_WEBHOOK_SECRET`) is
 * configured — no API call, and it keeps working when QBitFlow is unreachable — and goes
 * through `POST /webhooks/verify` otherwise.
 *
 * Two behaviours are worth knowing about:
 *
 * - The dashboard's "Test the endpoint" probe is verified like any other delivery, then
 *   answered 200 without reaching your handler. Verifying it first is what makes the
 *   dashboard button prove your setup end to end. (This assumes the probe is signed like
 *   a normal delivery.)
 * - A delivery whose signature does not verify gets a 400. On the API path, a network or
 *   server failure during verification is *not* swallowed: it surfaces as a 5xx so QBitFlow
 *   retries, rather than being silently mistaken for a forged request.
 */
final class VerifyQBitFlowWebhook
{
	/**
	 * @param QBitFlow|Closure(): QBitFlow|null $client        The client, or a resolver for it.
	 *                                                         Only needed to verify through the
	 *                                                         API, so local verification works
	 *                                                         without an API key.
	 * @param string|null                       $webhookSecret Your webhook secret, for local
	 *                                                         verification. When null, the API
	 *                                                         verifies each delivery.
	 */
	public function __construct(
		private readonly QBitFlow|Closure|null $client = null,
		private readonly ?string $webhookSecret = null,
	) {
	}

	public function handle(Request $request, Closure $next): Response
	{
		$signature = $request->header(WebhookVerifier::HEADER_SIGNATURE);
		$timestamp = $request->header(WebhookVerifier::HEADER_TIMESTAMP);

		if (! is_string($signature) || ! is_string($timestamp)) {
			return new JsonResponse(['message' => 'Missing webhook signature headers'], 400);
		}

		// The body is passed through as received; the signature covers a canonical
		// rendering, so the SDK (or the API) re-serialises it deterministically.
		$raw = $request->getContent();

		if (! $this->verified($raw, $signature, $timestamp)) {
			return new JsonResponse(['message' => 'Invalid webhook signature'], 400);
		}

		// A dashboard probe carries fake data — acknowledge it and stop here, but only
		// once it has been verified like any other delivery. That is what makes the
		// dashboard button a genuine end-to-end test of your setup: short-circuiting
		// before the signature check would report success even with a broken secret.
		if ($request->header(WebhookVerifier::HEADER_WEBHOOK_ID) === WebhookVerifier::TEST_WEBHOOK_ID) {
			return new JsonResponse(['message' => 'Test webhook received']);
		}

		return $next($request);
	}

	/**
	 * Locally when a secret is configured, otherwise through the API.
	 */
	private function verified(string $raw, string $signature, string $timestamp): bool
	{
		if ($this->webhookSecret !== null) {
			try {
				WebhookVerifier::verify($this->webhookSecret, $timestamp, $signature, $raw);

				return true;
			} catch (ValidationException) {
				// Bad signature, expired timestamp, or a body that is not JSON: not genuine.
				return false;
			}
		}

		// Resolved outside the try: a missing API key is a misconfiguration and must surface
		// (as a 5xx, so QBitFlow retries), not read as a rejected delivery.
		$client = $this->client();

		try {
			return $client->webhooks->verify($raw, $signature, $timestamp);
		} catch (ValidationException $e) {
			// A local rejection (a body that is not a JSON object) carries no status code:
			// not a genuine delivery. Anything with a status — including an API 422 — and
			// every auth, network or server failure propagates, so QBitFlow retries.
			if ($e->getStatusCode() === null) {
				return false;
			}

			throw $e;
		}
	}

	private function client(): QBitFlow
	{
		$client = $this->client instanceof Closure ? ($this->client)() : $this->client;

		if (! $client instanceof QBitFlow) {
			throw new \LogicException(
				'VerifyQBitFlowWebhook needs a QBitFlow client to verify through the API; '
					. 'configure QBITFLOW_API_KEY, or QBITFLOW_WEBHOOK_SECRET for local verification.',
			);
		}

		return $client;
	}
}
