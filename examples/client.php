<?php

/**
 * A tour of the QBitFlow PHP SDK, outside any framework.
 *
 * Run it with a test API key:
 *
 *   QBITFLOW_API_KEY=your-test-key php examples/client.php
 *
 * Every call here is safe against a test key: test-mode data is kept entirely separate
 * from live mode, and nothing touches a real blockchain.
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use QBitFlow\Dto\CreateCustomerDto;
use QBitFlow\Dto\CreateProductDto;
use QBitFlow\Dto\Session\CreatePaymentSessionDto;
use QBitFlow\Dto\Session\CreateSubscriptionSessionDto;
use QBitFlow\Enums\TransactionType;
use QBitFlow\Exceptions\NotFoundException;
use QBitFlow\Exceptions\QBitFlowException;
use QBitFlow\QBitFlow;
use QBitFlow\Support\Duration;

$apiKey = getenv('QBITFLOW_API_KEY') ?: '';

if ($apiKey === '') {
    fwrite(STDERR, "Set QBITFLOW_API_KEY first.\n");
    exit(1);
}

$client = new QBitFlow($apiKey);

function section(string $title): void
{
    echo PHP_EOL, '── ', $title, ' ', str_repeat('─', max(0, 60 - strlen($title))), PHP_EOL;
}

// ---------------------------------------------------------------------------
// Products
// ---------------------------------------------------------------------------

section('Products');

$product = $client->products->create(new CreateProductDto(
    name: 'Premium Plan',
    description: 'Access to all premium features',
    price: 29.99,
    reference: 'PROD-PREMIUM-' . bin2hex(random_bytes(3)),
));

printf("Created product #%d: %s at $%.2f\n", $product->id, $product->name, $product->price);

printf("Your catalogue holds %d product(s).\n", count($client->products->getAll()));

// ---------------------------------------------------------------------------
// Customers
// ---------------------------------------------------------------------------

section('Customers');

$customer = $client->customers->create(new CreateCustomerDto(
    name: 'John',
    lastName: 'Doe',
    email: sprintf('john+%s@example.com', bin2hex(random_bytes(3))),
    reference: 'CRM-' . bin2hex(random_bytes(3)),
));

printf("Created customer %s (%s)\n", $customer->fullName(), $customer->uuid);

// Your own reference resolves the customer without storing QBitFlow's UUID.
$resolved = $client->customers->getByReference((string) $customer->reference);
printf("Resolved by reference: %s\n", $resolved->uuid);

// ---------------------------------------------------------------------------
// A one-time payment
// ---------------------------------------------------------------------------

section('One-time payment');

$orderId = 'order-' . bin2hex(random_bytes(4));

$payment = $client->oneTimePayments->createSession(new CreatePaymentSessionDto(
    reference: $orderId,              // your own order ID, echoed back on the payment
    productId: $product->id,
    customerUUID: $customer->uuid,
    successUrl: 'https://example.com/success',
    cancelUrl: 'https://example.com/cancel',
));

printf("Session %s\n", $payment->uuid);
printf("Send this to the customer: %s\n", $payment->link);

// Read the session back. `availableCurrencies` holds currency IDs; resolve them below.
$session = $client->oneTimePayments->getSession($payment->uuid);
printf(
    "Checkout shows \"%s\" at $%.2f, accepting %d currencies.\n",
    $session->productName,
    $session->price,
    count($session->availableCurrencies),
);

// ---------------------------------------------------------------------------
// Currencies
// ---------------------------------------------------------------------------

section('Currencies');

$currencies = $client->currencies->getAllAvailable(test: true);
$byId = [];

foreach ($currencies as $currency) {
    $byId[$currency->id] = $currency;
}

foreach (array_slice($session->availableCurrencies, 0, 5) as $id) {
    $currency = $byId[$id] ?? null;

    if ($currency !== null) {
        printf("  %-6s %-20s %s\n", $currency->symbol, $currency->name, $currency->isToken() ? '(token)' : '');
    }
}

// ---------------------------------------------------------------------------
// Transaction status
// ---------------------------------------------------------------------------

section('Transaction status');

// Nothing has been paid yet, so this is normally `created`. In production, learn about
// settlement from a webhook rather than polling.
try {
    $status = $client->transactionStatus->get($payment->uuid, TransactionType::ONE_TIME_PAYMENT);
    printf("Status: %s\n", $status->status->value);
} catch (NotFoundException) {
    echo "No status yet — processing has not started.\n";
}

// ---------------------------------------------------------------------------
// A subscription
// ---------------------------------------------------------------------------

section('Subscription');

$subscription = $client->subscriptions->createSession(new CreateSubscriptionSessionDto(
    frequency: Duration::months(1),
    productId: $product->id,
    trialPeriod: Duration::days(7),
    minPeriods: 3,
    customerUUID: $customer->uuid,
    reference: 'sub-' . bin2hex(random_bytes(4)),
));

printf("Subscription link: %s\n", $subscription->link);

// ---------------------------------------------------------------------------
// Listing with pagination
// ---------------------------------------------------------------------------

section('Payments');

$cursor = null;
$seen = 0;

do {
    $page = $client->oneTimePayments->getAll(limit: 25, cursor: $cursor);

    foreach ($page as $settled) {
        printf("  %s  $%-8.2f %s\n", $settled->uuid, $settled->amount, $settled->reference ?? '');
        $seen++;
    }

    $cursor = $page->nextCursor;
} while ($page->hasMore() && $seen < 100);

printf("Listed %d settled payment(s).\n", $seen);

// ---------------------------------------------------------------------------
// Acting on behalf of a user (organization keys only)
// ---------------------------------------------------------------------------

section('Acting on behalf of a user');

try {
    $me = $client->users->get();
    printf("This key belongs to %s %s (role: %s)\n", $me->name, $me->lastName, $me->role->value);

    // With an admin or owner key you can run any request as one of your users.
    $theirProducts = $client->products->onBehalfOf($me->id)->getAll();
    printf("User #%d has %d product(s).\n", $me->id, count($theirProducts));
} catch (QBitFlowException $e) {
    printf("Skipped: %s\n", $e->getMessage());
}

// ---------------------------------------------------------------------------
// Accounting
// ---------------------------------------------------------------------------

section('Accounting');

$from = (new DateTimeImmutable('first day of january this year'))->format('Y-m-d');
$to = (new DateTimeImmutable('today'))->format('Y-m-d');

$events = $client->accounting->exportJson($from, $to);
$net = array_sum(array_map(static fn ($event) => $event->netAmountUsd, $events));

printf("%d event(s) between %s and %s, $%.2f net.\n", count($events), $from, $to, $net);

// ---------------------------------------------------------------------------
// Tidy up
// ---------------------------------------------------------------------------

section('Cleanup');

$client->customers->delete($customer->uuid);
$client->products->delete($product->id);

echo "Removed the customer and product created by this example.\n";
