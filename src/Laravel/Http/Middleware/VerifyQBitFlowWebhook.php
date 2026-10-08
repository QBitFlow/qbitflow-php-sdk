<?php

declare(strict_types=1);

namespace QBitFlow\Laravel\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use QBitFlow\Exceptions\QBitFlowException;
use QBitFlow\Exceptions\ValidationException;
use QBitFlow\Exceptions\WebhookSignatureException;
use QBitFlow\Webhooks\Webhook;
use Symfony\Component\HttpFoundation\Response;

/**
 * Refuses the webhook deliveries QBitFlow did not sign.
 *
 * Registered as `qbitflow.webhook`, and applied by `Route::qbitflowWebhooks()`:
 *
 * ```php
 * // routes/api.php
 * Route::qbitflowWebhooks('webhooks/qbitflow');
 *
 * // or on a route of your own
 * Route::post('/hooks/qbitflow', MyController::class)->middleware('qbitflow.webhook');
 * ```
 *
 * It verifies the `QBitFlow-Signature` header over the raw body with `QBITFLOW_WEBHOOK_SECRET`
 * (the endpoint's `whsec_…` secret), then parses the event and puts it on the request
 * (`$request->attributes->get('qbitflow.event')`, a {@see \QBitFlow\Events\Event}). A bad
 * signature, or a body that is not a v2 event, is answered 400; a missing secret is a
 * configuration error (500, so QBitFlow retries once it is fixed).
 */
final class VerifyQBitFlowWebhook
{
	/** The request attribute the verified event is stored under. */
	public const EVENT_ATTRIBUTE = 'qbitflow.event';

	public function __construct(
		private readonly ?string $secret = null,
		private readonly int $tolerance = Webhook::DEFAULT_TOLERANCE,
	) {
	}

	public function handle(Request $request, Closure $next): Response
	{
		if ($this->secret === null || $this->secret === '') {
			throw new QBitFlowException('QBitFlow webhook secret is not configured: set QBITFLOW_WEBHOOK_SECRET (the endpoint\'s whsec_… secret).');
		}

		try {
			$event = Webhook::constructEvent(
				$request->getContent(),
				(string) $request->headers->get(Webhook::SIGNATURE_HEADER, ''),
				$this->secret,
				$this->tolerance,
			);
		} catch (WebhookSignatureException $e) {
			return new JsonResponse(['message' => 'Invalid webhook signature', 'reason' => $e->reason], 400);
		} catch (ValidationException $e) {
			// Signed, but not a v2 event (an endpoint still on payload version v1, or a bad body).
			return new JsonResponse(['message' => 'Invalid webhook body: ' . $e->getMessage()], 400);
		}

		$request->attributes->set(self::EVENT_ATTRIBUTE, $event);

		return $next($request);
	}
}
