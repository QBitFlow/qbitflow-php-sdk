<?php

/**
 * A plain-PHP webhook receiver with the webhook router: it verifies the QBitFlow-Signature over
 * the raw body, runs the handler of the event's type with its typed data, and answers 200 (also
 * to the ignored types), 400 (bad signature, not a v2 event) or 500 (a handler threw: QBitFlow
 * retries).
 *
 *     QBITFLOW_WEBHOOK_SECRET=whsec_… php -S 127.0.0.1:8080 examples/webhook-handler.php
 *
 * Try it locally with a signed body:
 *
 *     php -r 'require "vendor/autoload.php"; echo QBitFlow\Webhooks\Webhook::sign(file_get_contents("body.json"), getenv("QBITFLOW_WEBHOOK_SECRET"));'
 *     curl -X POST --data-binary @body.json -H "QBitFlow-Signature: <that>" http://127.0.0.1:8080
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use QBitFlow\Enums\EventType;
use QBitFlow\Events\Event;
use QBitFlow\Models\PaymentCompleted;
use QBitFlow\Models\PaymentSessionData;
use QBitFlow\Models\SubscriptionStatusChanged;
use QBitFlow\Webhooks\WebhookRouter;

/** Deliveries are at least once: true the first time an event id is seen (use your database). */
function firstDelivery(string $eventId): bool
{
	$seen = sys_get_temp_dir() . '/qbitflow-' . md5($eventId);

	return @fopen($seen, 'x') !== false; // atomic: only one request creates it
}

$router = (new WebhookRouter((string) getenv('QBITFLOW_WEBHOOK_SECRET')))
	->on(EventType::PAYMENT_COMPLETED, function (PaymentCompleted $payment, Event $event): void {
		if (firstDelivery($event->id)) {
			error_log(sprintf('fulfil order %s (%s): %.2f USD', $payment->reference ?? '-', $payment->uuid, $payment->amount));
		}
	})
	->on(EventType::CHECKOUT_EXPIRED, function (PaymentSessionData $session): void {
		error_log('release order ' . ($session->reference ?? '-')); // a SubscriptionSessionData is one too
	})
	->on(EventType::SUBSCRIPTION_STATUS_CHANGED, function (SubscriptionStatusChanged $subscription): void {
		error_log(sprintf('%s: %s -> %s, access: %s', $subscription->uuid, $subscription->previousStatus, $subscription->status,
			$subscription->hasAccess() ? 'yes' : 'no'));
	})
	// A member's event: $event->userUuid names them; read their resources with $client->onBehalfOf($event->userUuid).
	->onAny(fn (Event $event) => error_log("received {$event->type} {$event->id}"))
	->onError(fn (?Event $event, Throwable $error) => error_log('webhook ' . ($event?->id ?? '-') . ' refused or failed: ' . $error->getMessage()));

$router->handleGlobals(); // 405 unless POST, 413 over 1 MiB; sends the status and a JSON body
