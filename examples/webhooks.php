<?php

/**
 * Webhook endpoints and the event log: create an endpoint (--create; its secret is returned only
 * once) and list the recent payment.completed events.
 *
 *     QBITFLOW_API_KEY=sk_… php examples/webhooks.php [--create]
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use QBitFlow\QBitFlow;

$client = QBitFlow::fromEnv();

if (in_array('--create', $argv, true)) {
	// docs:start webhook-endpoint-create
	$endpoint = $client->webhooks->endpoints->create(new \QBitFlow\Params\CreateWebhookEndpointParams(
		url: 'https://shop.example.com/webhooks/qbitflow',
		events: [ // null: every type, including the ones added later
			\QBitFlow\Enums\EventType::PAYMENT_COMPLETED,
			\QBitFlow\Enums\EventType::CHECKOUT_EXPIRED,
			\QBitFlow\Enums\EventType::SUBSCRIPTION_STATUS_CHANGED,
		],
	));
	// The whsec_… secret is returned only this once: put it in your secret store now.
	echo "Endpoint {$endpoint->uuid}: QBITFLOW_WEBHOOK_SECRET={$endpoint->secret}\n";
	// docs:end webhook-endpoint-create
}

// docs:start events-list
$page = $client->webhooks->events->list(new \QBitFlow\Params\EventListParams(
	type: \QBitFlow\Enums\EventType::PAYMENT_COMPLETED,
	limit: 20,
));
foreach ($page->items as $event) { // newest first
	echo "{$event->id} {$event->type} {$event->createdAt->format(DATE_ATOM)}\n";
}
// docs:end events-list
