<?php

/**
 * Typed errors, isRetryable(), and an idempotency key that makes a create safe to retry across
 * processes.
 *
 *     QBITFLOW_API_KEY=sk_… php examples/errors-and-retries.php
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use QBitFlow\Exceptions\ApiException;
use QBitFlow\Exceptions\ConflictException;
use QBitFlow\Exceptions\IdempotencyException;
use QBitFlow\Exceptions\NotFoundException;
use QBitFlow\Exceptions\RateLimitException;
use QBitFlow\Exceptions\ValidationException;
use QBitFlow\Params\CreateCustomerParams;
use QBitFlow\Params\CreatePaymentSessionParams;
use QBitFlow\QBitFlow;
use QBitFlow\RequestOptions;

$client = new QBitFlow(
	apiKey: (string) getenv('QBITFLOW_API_KEY'),
	baseUrl: getenv('QBITFLOW_BASE_URL') ?: null,
	timeout: 10.0,  // per attempt
	maxRetries: 5,  // reads and the 7 creates; 0 disables
);

// Client-side validation: nothing is sent.
try {
	$client->customers->create(new CreateCustomerParams(name: 'A', email: 'not-an-email'));
} catch (ValidationException $e) {
	foreach ($e->fieldErrors as $fieldError) {
		echo "{$fieldError->field}: {$fieldError->message}\n"; // status 0: refused before sending
	}
}

try {
	$client->products->get('0192f1c2-1111-7c4d-9e5f-6a7b8c9d0e1f');
} catch (NotFoundException $e) {
	echo "Not found (request {$e->requestId})\n";
} catch (RateLimitException $e) {
	echo "Slow down: retry in {$e->retryAfter} s\n";
} catch (ApiException $e) {
	printf("QBitFlow error %d %s (request %s), retryable: %s\n", $e->status, $e->apiCode, $e->requestId, $e->isRetryable() ? 'yes' : 'no');
}

// The same key returns the same session, even from another process after a crash.
$orderId = 'order-1044';
try {
	$session = $client->checkoutSessions->createPayment(
		new CreatePaymentSessionParams(productName: 'T-shirt', price: 4.99, reference: $orderId),
		new RequestOptions(idempotencyKey: 'checkout-' . $orderId, requestId: 'job-7781'),
	);
	echo "Pay at {$session->link}\n";
} catch (IdempotencyException) {
	echo "This key was already used with other params (422 idempotency_key_reused)\n";
} catch (ConflictException $e) {
	echo "Conflict: {$e->apiCode}\n"; // e.g. unique_violation on the reference, merchant_not_ready
}
