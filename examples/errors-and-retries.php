<?php

/**
 * Typed errors, isRetryable(), and an idempotency key that makes a create safe to retry across
 * processes (the checkout it opens is expired again so the example can run twice).
 *
 *     QBITFLOW_API_KEY=sk_… php examples/errors-and-retries.php
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use QBitFlow\Exceptions\ConflictException;
use QBitFlow\Exceptions\IdempotencyException;
use QBitFlow\Exceptions\QBitFlowException;
use QBitFlow\Exceptions\ValidationException;
use QBitFlow\Params\CreateCustomerParams;
use QBitFlow\QBitFlow;

$client = QBitFlow::fromEnv();

// Client-side validation: nothing is sent.
try {
	$client->customers->create(new CreateCustomerParams(name: 'A', email: 'not-an-email'));
} catch (ValidationException $e) {
	foreach ($e->fieldErrors as $fieldError) {
		echo "{$fieldError->field}: {$fieldError->message}\n"; // status 0: refused before sending
	}
}

// docs:start errors-handling
try {
	$payment = $client->payments->getByReference('order-1042');
	echo "order-1042 was paid by {$payment->uuid}\n";
} catch (\QBitFlow\Exceptions\ValidationException $e) {
	foreach ($e->fieldErrors as $fieldError) { // refused before sending (status 0), or a 400
		echo "{$fieldError->field}: {$fieldError->message}\n";
	}
} catch (\QBitFlow\Exceptions\NotFoundException) {
	echo "No payment for order-1042 yet\n";
} catch (\QBitFlow\Exceptions\ApiException $e) {
	// Branch on apiCode, never on the message; quote the request id to support.
	printf("QBitFlow error %d %s (request %s), retryable: %s\n",
		$e->status, $e->apiCode, $e->requestId, $e->isRetryable() ? 'yes' : 'no');
}
// docs:end errors-handling

try {
	// docs:start retries-idempotency
	// Reads and creates are retried on network errors, 5xx and 429 (3 retries by default).
	$client = \QBitFlow\QBitFlow::fromEnv(maxRetries: 5);

	$orderId = 'order-1042';
	$session = $client->checkoutSessions->createPayment(
		new \QBitFlow\Params\CreatePaymentSessionParams(
			productName: 'T-shirt',
			description: 'Blue, size M',
			price: 4.99,
			reference: $orderId,
			successUrl: 'https://shop.example.com/orders/success?uuid=' . \QBitFlow\Placeholders::UUID,
			cancelUrl: 'https://shop.example.com/orders/cancel',
		),
		// The same key returns the same session, even from another process after a crash.
		new \QBitFlow\RequestOptions(idempotencyKey: 'checkout-' . $orderId),
	);
	echo "Pay at {$session->link}\n";
	// docs:end retries-idempotency
} catch (IdempotencyException) {
	exit("This key was already used with other params (422 idempotency_key_reused)\n");
} catch (ConflictException $e) {
	exit("Conflict: {$e->apiCode}\n"); // unique_violation on the reference, merchant_not_ready
}

// Frees order-1042 for the other examples (a rerun within 24 hours gets the same, expired, session).
try {
	$client->checkoutSessions->expire($session->uuid);
} catch (QBitFlowException) {
	// already expired by an earlier run
}
