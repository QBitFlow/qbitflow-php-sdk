<?php

declare(strict_types=1);

namespace QBitFlow\Laravel\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use QBitFlow\Exceptions\QBitFlowException;
use QBitFlow\Webhooks\Webhook;
use QBitFlow\Webhooks\WebhookResult;
use QBitFlow\Webhooks\WebhookRouter;
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
 * (`$request->attributes->get('qbitflow.event')`, a {@see \QBitFlow\Events\Event}), with a
 * {@see WebhookRouter}. A bad signature, or a body that is not a v2 event, is answered 400
 * (`{"error":"invalid signature"}`, `{"error":"invalid event"}`), a body over 1 MiB 413; a
 * missing secret is a configuration error (500, so QBitFlow retries once it is fixed).
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

		$body = $request->getContent();
		// A router with no handler only verifies and parses: 200 with the event, or the 400.
		$result = (int) $request->headers->get('Content-Length', '0') > Webhook::MAX_BODY_BYTES || strlen($body) > Webhook::MAX_BODY_BYTES
			? new WebhookResult(413)
			: (new WebhookRouter($this->secret, $this->tolerance))->handle($body, (string) $request->headers->get(Webhook::SIGNATURE_HEADER, ''));
		if (! $result->ok() || $result->event === null) {
			return new JsonResponse(['error' => $result->reason()], $result->status);
		}

		$request->attributes->set(self::EVENT_ATTRIBUTE, $result->event);

		return $next($request);
	}
}
