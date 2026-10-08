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
use QBitFlow\QBitFlow;

$client = QBitFlow::fromEnv(); // QBITFLOW_API_KEY, and QBITFLOW_BASE_URL when set

try {
	// docs:start checkout-create-payment
	$session = $client->checkoutSessions->createPayment(new \QBitFlow\Params\CreatePaymentSessionParams(
		productName: 'T-shirt',
		description: 'Blue, size M',
		price: 4.99,
		reference: 'order-1042', // your order id: unique per space
		successUrl: 'https://shop.example.com/orders/success?uuid=' . \QBitFlow\Placeholders::UUID,
		cancelUrl: 'https://shop.example.com/orders/cancel',
	));

	header('Location: ' . $session->link); // send the customer to the hosted checkout
	// docs:end checkout-create-payment
} catch (ConflictException $e) {
	if ($e->apiCode === 'merchant_not_ready') {
		exit('The space accepts no currency yet (' . ($e->details['reason'] ?? '?') . "): add a wallet in the dashboard.\n");
	}
	if ($e->apiCode === 'unique_violation') {
		exit("order-1042 already has a payment or an open checkout: expire it, or use another reference.\n");
	}

	throw $e;
}
printf("Send the customer to %s (session %s, expires %s)\n", $session->link, $session->uuid, $session->expiresAt?->format(DATE_ATOM) ?? '?');

$sessionUuid = $session->uuid;

if (in_array('--wait', $argv, true)) {
	// docs:start wait-for-completion
	// For scripts and back-office jobs: fulfil orders on the payment.completed webhook.
	$status = $client->checkoutSessions->waitForCompletion($sessionUuid, timeout: 600, interval: 3);
	if ($status->status === \QBitFlow\Enums\CheckoutSessionStatusValue::COMPLETED) {
		echo "Paid, tx {$status->txHash}\n";
	} else {
		echo "Not paid: {$status->status}\n"; // expired, or still pending when the timeout elapsed
	}
	// docs:end wait-for-completion
}

// docs:start checkout-status
$status = $client->checkoutSessions->getStatus($sessionUuid); // pay@… or sub@…
echo match ($status->status) {
	\QBitFlow\Enums\CheckoutSessionStatusValue::COMPLETED => "Paid, tx {$status->txHash}\n",
	\QBitFlow\Enums\CheckoutSessionStatusValue::EXPIRED => "Expired unpaid: {$status->message}\n",
	\QBitFlow\Enums\CheckoutSessionStatusValue::WAITING_CONFIRMATION => "Sent, waiting for the network\n",
	default => $status->lastAttempt !== null // created, or a status this SDK does not know
		? "Last attempt failed ({$status->lastAttempt->code}): the customer may try again\n"
		: "Waiting for the customer\n",
};
// docs:end checkout-status

if ($status->status === CheckoutSessionStatusValue::COMPLETED) {
	$payment = $client->payments->get($sessionUuid); // the payment has the session's id
	printf("Paid: %.2f USD, tx %s\n", $payment->amount, $payment->txHash);
	exit(0);
}
if ($status->status === CheckoutSessionStatusValue::EXPIRED) {
	exit(0);
}

// The order was cancelled on our side: end the session (checkout.expired follows).
// docs:start checkout-expire
$expired = $client->checkoutSessions->expire($sessionUuid);
echo "Now {$expired->status}\n"; // expired: the customer can no longer pay it
// docs:end checkout-expire
