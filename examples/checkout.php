<?php

/**
 * A one-time payment checkout: create it, read its status, expire it. With --wait, wait for the
 * customer to pay (waitForCompletion: for scripts and tests; fulfil orders on the webhook).
 *
 *     QBITFLOW_API_KEY=sk_… php examples/checkout.php [--wait]
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use QBitFlow\Enums\CheckoutSessionStatusValue;
use QBitFlow\Exceptions\ConflictException;
use QBitFlow\Params\CreatePaymentSessionParams;
use QBitFlow\Placeholders;
use QBitFlow\QBitFlow;

$client = QBitFlow::fromEnv(); // QBITFLOW_API_KEY, and QBITFLOW_BASE_URL when set

// The recommended start-up check: which space and mode is this key in?
$me = $client->me();
printf("%s, role %s, %s mode\n", $me->space?->organizationName ?? '?', $me->role ?? '?', ($me->space?->test ?? false) ? 'test' : 'live');

$orderId = 'order-' . time();
try {
	$session = $client->checkoutSessions->createPayment(new CreatePaymentSessionParams(
		productName: 'Premium access',
		price: 4.99,
		reference: $orderId,
		successUrl: 'https://shop.example.com/thanks?session=' . Placeholders::UUID . '&type=' . Placeholders::TRANSACTION_TYPE,
		cancelUrl: 'https://shop.example.com/cart',
		expiresInMinutes: 30,
	));
} catch (ConflictException $e) {
	if ($e->apiCode === 'merchant_not_ready') {
		exit('The space accepts no currency yet (' . ($e->details['reason'] ?? '?') . "): add a wallet in the dashboard.\n");
	}

	throw $e;
}
printf("Send the customer to %s (session %s, expires %s)\n", $session->link, $session->uuid, $session->expiresAt?->format(DATE_ATOM) ?? '?');

$status = in_array('--wait', $argv, true)
	? $client->checkoutSessions->waitForCompletion($session->uuid, timeout: 600, interval: 3) // completed, expired, or the last seen
	: $client->checkoutSessions->getStatus($session->uuid);
switch ($status->status) {
	case CheckoutSessionStatusValue::COMPLETED:
		$payment = $client->payments->get($session->uuid); // the payment has the session's id
		printf("Paid: %.2f USD, tx %s\n", $payment->amount, $payment->txHash);
		exit(0);
	case CheckoutSessionStatusValue::EXPIRED:
		echo "Expired unpaid: {$status->message}\n";
		exit(0);
	default: // created, waitingConfirmation, or a status this SDK does not know
		if ($status->lastAttempt !== null) {
			echo "The last attempt failed ({$status->lastAttempt->code}): the customer may try again.\n";
		}
		echo "Status: {$status->status}\n";
}

// The order was cancelled on our side: end the session (checkout.expired follows).
$expired = $client->checkoutSessions->expire($session->uuid);
echo "Now {$expired->status}\n";
