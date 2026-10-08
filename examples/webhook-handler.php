<?php

/**
 * A plain-PHP webhook receiver: verify the QBitFlow-Signature over the raw body, deduplicate on
 * the event id, handle the types you need, answer 2xx to everything else.
 *
 *     QBITFLOW_WEBHOOK_SECRET=whsec_… php -S 127.0.0.1:8080 examples/webhook-handler.php
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use QBitFlow\Events\CheckoutExpiredEvent;
use QBitFlow\Events\PaymentCompletedEvent;
use QBitFlow\Events\SubscriptionStatusChangedEvent;
use QBitFlow\Exceptions\ValidationException;
use QBitFlow\Exceptions\WebhookSignatureException;
use QBitFlow\Webhooks\Webhook;

$rawBody = (string) file_get_contents('php://input'); // the bytes as received: never re-serialize
$header = $_SERVER['HTTP_QBITFLOW_SIGNATURE'] ?? '';

try {
	$event = Webhook::constructEvent($rawBody, $header, (string) getenv('QBITFLOW_WEBHOOK_SECRET'));
} catch (WebhookSignatureException $e) {
	http_response_code(400);
	exit('invalid signature: ' . $e->reason);
} catch (ValidationException $e) {
	http_response_code(400);
	exit('not a v2 event'); // an endpoint still on payload version v1
}

// Deliveries are at least once: deduplicate on the event id (use your database).
$seen = sys_get_temp_dir() . '/qbitflow-' . md5($event->id);
if (file_exists($seen)) {
	http_response_code(200);
	exit;
}
touch($seen);

if ($event instanceof PaymentCompletedEvent) {
	error_log(sprintf('fulfil order %s (%s): %.2f USD', $event->data->reference ?? '-', $event->data->uuid, $event->data->amount));
} elseif ($event instanceof CheckoutExpiredEvent) {
	error_log('release order ' . ($event->data->reference ?? '-'));
} elseif ($event instanceof SubscriptionStatusChangedEvent) {
	$access = $event->data->currentPeriodEnd !== null && new DateTimeImmutable() < $event->data->currentPeriodEnd;
	error_log(sprintf('%s: %s -> %s, access: %s', $event->data->uuid, $event->data->previousStatus, $event->data->status, $access ? 'yes' : 'no'));
}
// A member's event: $event->userUuid names them; read their resources with $client->onBehalfOf($event->userUuid).

http_response_code(200); // to every type, the ignored ones too
