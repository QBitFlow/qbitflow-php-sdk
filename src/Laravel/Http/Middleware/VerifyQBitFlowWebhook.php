<?php

declare(strict_types=1);

namespace QBitFlow\Laravel\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use QBitFlow\QBitFlow;
use QBitFlow\Requests\WebhookRequests;
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
 * Two behaviours are worth knowing about:
 *
 * - The dashboard's "Test the endpoint" probe is answered 200 immediately and never
 *   reaches your handler, as the API documentation requires.
 * - A delivery whose signature the API rejects gets a 400. A network or server failure
 *   during verification is *not* swallowed: it surfaces as a 5xx so QBitFlow retries,
 *   rather than being silently mistaken for a forged request.
 */
final class VerifyQBitFlowWebhook
{
	public function __construct(private readonly QBitFlow $client)
	{
	}

	public function handle(Request $request, Closure $next): Response
	{
		$webhookId = $request->header(WebhookRequests::HEADER_WEBHOOK_ID);

		// A dashboard probe carries fake data — acknowledge it and stop here.
		if ($this->client->webhooks->isTestWebhook($webhookId)) {
			return new JsonResponse(['message' => 'Test webhook received']);
		}

		$signature = $request->header(WebhookRequests::HEADER_SIGNATURE);
		$timestamp = $request->header(WebhookRequests::HEADER_TIMESTAMP);

		if (! is_string($signature) || ! is_string($timestamp)) {
			return new JsonResponse(['message' => 'Missing webhook signature headers'], 400);
		}

		// The raw body must be passed through untouched: decoding and re-encoding it
		// would reorder keys and invalidate the signature.
		$verified = $this->client->webhooks->verify($request->getContent(), $signature, $timestamp);

		if (! $verified) {
			return new JsonResponse(['message' => 'Invalid webhook signature'], 400);
		}

		return $next($request);
	}
}
