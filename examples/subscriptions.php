<?php

/**
 * A subscription checkout with a trial, the past-due subscriptions, and, given a sub@… id: the
 * subscription, access, its bills, a test bill (--bill, test mode) and a cancellation at the end
 * of the paid period (--cancel).
 *
 *     QBITFLOW_API_KEY=sk_… php examples/subscriptions.php [sub@…] [--bill] [--cancel]
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use QBitFlow\Enums\SubscriptionStatus;
use QBitFlow\Exceptions\ConflictException;
use QBitFlow\Params\SubscriptionListParams;
use QBitFlow\QBitFlow;

$client = QBitFlow::fromEnv();

try {
	// docs:start checkout-create-subscription
	$session = $client->checkoutSessions->createSubscription(new \QBitFlow\Params\CreateSubscriptionSessionParams(
		productName: 'T-shirt',
		description: 'Blue, size M',
		price: 4.99, // USD per period
		reference: 'order-1043',
		frequency: \QBitFlow\Models\Duration::months(1),
		trialPeriod: \QBitFlow\Models\Duration::days(7), // the first bill comes after the trial
		successUrl: 'https://shop.example.com/orders/success?uuid=' . \QBitFlow\Placeholders::UUID,
		cancelUrl: 'https://shop.example.com/orders/cancel',
	));
	echo "Subscribe at {$session->link}\n";
	// docs:end checkout-create-subscription
} catch (ConflictException $e) {
	echo "No new checkout ({$e->apiCode}): order-1043 already has a subscription or an open checkout.\n";
}

foreach ($client->subscriptions->iterate(new SubscriptionListParams(status: SubscriptionStatus::PAST_DUE, limit: 50)) as $pastDue) {
	printf("%s past due, %d attempts left\n", $pastDue->uuid, $pastDue->dunning?->remainingAttempts ?? 0);
}

$subscriptionUuid = array_values(array_filter(array_slice($argv, 1), static fn (string $arg): bool => ! str_starts_with($arg, '--')))[0] ?? null;
if ($subscriptionUuid === null) {
	exit("Pass a sub@… id to read it, its bills, and bill (--bill) or cancel (--cancel) it.\n");
}

// docs:start subscriptions-get
$subscription = $client->subscriptions->get($subscriptionUuid); // sub@…
printf("%s: %s, paid until %s\n", $subscription->uuid, $subscription->status, $subscription->currentPeriodEnd?->format('Y-m-d') ?? '-');
// docs:end subscriptions-get

// docs:start has-access
$subscription = $client->subscriptions->get($subscriptionUuid);
if ($subscription->hasAccess()) { // now < currentPeriodEnd, whatever the status
	echo "Access granted\n";
} else {
	echo "No access: the paid period has ended\n";
}
// docs:end has-access

foreach ($client->subscriptions->iterateBills($subscriptionUuid) as $bill) {
	printf("  %s: %.2f USD, paid until %s\n", $bill->uuid, $bill->amount, $bill->periodEnd?->format('Y-m-d') ?? '?');
}

if (in_array('--bill', $argv, true)) {
	try {
		// docs:start subscriptions-test-bill
		// Test mode only: runs the next billing now instead of on its due date.
		$state = $client->subscriptions->executeTestBilling($subscriptionUuid);
		echo "Bill {$state->billUuid}: {$state->stage} {$state->outcome}\n";
		// docs:end subscriptions-test-bill
	} catch (ConflictException $e) {
		echo "Not billed ({$e->apiCode})\n"; // payment_not_due before nextBillingDate
	}
}

if (in_array('--cancel', $argv, true)) {
	// docs:start subscriptions-cancel
	$result = $client->subscriptions->cancel($subscriptionUuid, new \QBitFlow\Params\CancelSubscriptionParams(immediate: false));
	if ($result->pending) {
		// HTTP 202: the on-chain cancellation is still confirming; subscription.statusChanged tells the end.
		echo "Cancellation confirming\n";
	} else {
		echo "Now {$result->subscription->status}\n"; // stopped: cancelled at the end of the paid period
	}
	// docs:end subscriptions-cancel
}
