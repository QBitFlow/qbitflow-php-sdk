<?php

/**
 * The lower level of webhook-handler.php: verify the QBitFlow-Signature over the raw body and
 * parse the event yourself, without the router.
 *
 *     QBITFLOW_WEBHOOK_SECRET=whsec_… php -S 127.0.0.1:8080 examples/webhook-verify.php
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

// docs:start webhook-verify
$rawBody = (string) file_get_contents('php://input'); // the bytes as received: never re-serialize
try {
	$event = \QBitFlow\Webhooks\Webhook::constructEvent(
		$rawBody,
		$_SERVER['HTTP_QBITFLOW_SIGNATURE'] ?? '',
		(string) getenv('QBITFLOW_WEBHOOK_SECRET'),
	);
} catch (\QBitFlow\Exceptions\WebhookSignatureException $e) {
	http_response_code(400); // $e->reason: noMatchingSignature, timestampOutsideTolerance, …
	exit;
} catch (\QBitFlow\Exceptions\ValidationException) {
	http_response_code(400); // not a v2 event: an endpoint still on payload version v1
	exit;
}

if ($event instanceof \QBitFlow\Events\PaymentCompletedEvent) {
	// Deliveries are at least once: skip an $event->id you already processed.
	error_log("fulfil order {$event->data->reference} ({$event->data->uuid})");
}
http_response_code(200); // to every type, the ignored ones too
// docs:end webhook-verify
