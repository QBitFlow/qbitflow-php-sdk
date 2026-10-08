<?php

/**
 * A subscription checkout with a trial, the past-due subscriptions, a subscription's bills,
 * and a cancellation at the end of the paid period.
 *
 *     QBITFLOW_API_KEY=sk_… php examples/subscriptions.php [sub@…]
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use QBitFlow\Enums\SubscriptionStatus;
use QBitFlow\Models\Duration;
use QBitFlow\Models\Subscription;
use QBitFlow\Params\CancelSubscriptionParams;
use QBitFlow\Params\CreateSubscriptionSessionParams;
use QBitFlow\Params\SubscriptionListParams;
use QBitFlow\QBitFlow;

$client = new QBitFlow(apiKey: (string) getenv('QBITFLOW_API_KEY'), baseUrl: getenv('QBITFLOW_BASE_URL') ?: null);

// Grant access while now < currentPeriodEnd, whatever the status.
$hasAccess = static fn (Subscription $s): bool => $s->currentPeriodEnd !== null && new DateTimeImmutable() < $s->currentPeriodEnd;

$session = $client->checkoutSessions->createSubscription(new CreateSubscriptionSessionParams(
	productName: 'Pro plan',
	price: 4.99, // USD per period
	successUrl: 'https://app.example.com/billing?subscription={{UUID}}',
	frequency: Duration::months(1),
	trialPeriod: Duration::days(14),
	minPeriods: 3,
));
echo "Subscribe at {$session->link} ({$session->uuid})\n";

foreach ($client->subscriptions->iterate(new SubscriptionListParams(status: SubscriptionStatus::PAST_DUE, limit: 50)) as $subscription) {
	printf("%s past due, %d attempts left, access: %s\n", $subscription->uuid, $subscription->dunning?->remainingAttempts ?? 0,
		$hasAccess($subscription) ? 'yes' : 'no');
}

$uuid = $argv[1] ?? null;
if ($uuid === null) {
	exit("Pass a sub@… id to read its bills and cancel it at the end of its period.\n");
}

$subscription = $client->subscriptions->get($uuid);
echo "{$subscription->uuid}: {$subscription->status}, {$subscription->priceUsd} USD per period\n";
foreach ($client->subscriptions->iterateBills($uuid) as $bill) {
	printf("  %s: %.2f USD, paid until %s\n", $bill->uuid, $bill->amount, $bill->periodEnd?->format('Y-m-d') ?? '?');
}

$result = $client->subscriptions->cancel($uuid, new CancelSubscriptionParams(immediate: false));
echo $result->pending
	? "Cancellation confirming on-chain (HTTP 202): subscription.statusChanged tells the end.\n"
	: "Now {$result->subscription->status}\n";
