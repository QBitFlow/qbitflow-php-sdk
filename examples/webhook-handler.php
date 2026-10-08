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

// docs:start webhook-handler
$router = (new \QBitFlow\Webhooks\WebhookRouter((string) getenv('QBITFLOW_WEBHOOK_SECRET')))
	->on(\QBitFlow\Enums\EventType::PAYMENT_COMPLETED, function (
		\QBitFlow\Models\PaymentCompleted $payment,
		\QBitFlow\Events\Event $event,
	): void {
		// Deliveries are at least once: skip an $event->id you already processed.
		error_log("event {$event->id}: fulfil order {$payment->reference} ({$payment->uuid}), {$payment->amount} USD");
	})
	->on(\QBitFlow\Enums\EventType::SUBSCRIPTION_STATUS_CHANGED, function (
		\QBitFlow\Models\SubscriptionStatusChanged $subscription,
	): void {
		error_log("{$subscription->uuid} is {$subscription->status}, access: " . ($subscription->hasAccess() ? 'yes' : 'no'));
	})
	->onError(fn (?\QBitFlow\Events\Event $event, \Throwable $error) => error_log("webhook refused or failed: {$error->getMessage()}"));

// Reads php://input and the QBitFlow-Signature header, runs the handler of the event's type, and
// answers 200 (ignored types too), 400 (bad signature) or 500 (a handler threw: QBitFlow retries).
$router->handleGlobals();
// docs:end webhook-handler
