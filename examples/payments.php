<?php

/**
 * Payments: one page with a filter, one payment by its id and by your order reference, every
 * payment with the iterator, the feed of payments and bills together, and exact amounts.
 *
 *     QBITFLOW_API_KEY=sk_… php examples/payments.php [pay@…]
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use QBitFlow\Exceptions\NotFoundException;
use QBitFlow\QBitFlow;

$client = QBitFlow::fromEnv();

// docs:start payments-list
$page = $client->payments->list(new \QBitFlow\Params\PaymentListParams(
	createdAfter: new \DateTimeImmutable('-30 days'),
	limit: 20,
));
foreach ($page->items as $payment) {
	// Amounts are decimal strings: the token's (amount, with its currency) and USD (amountUsd).
	echo "{$payment->uuid} " . ($payment->reference ?? '-') . ": {$payment->amount} {$payment->currency->symbol} ({$payment->amountUsd} USD)\n";
}
if ($page->hasMore) {
	$cursor = $page->nextCursor; // pass it back, verbatim, as `cursor` for the next page
}
// docs:end payments-list

$paymentUuid = $argv[1] ?? ($page->items[0]->uuid ?? null);
if ($paymentUuid !== null) {
	try {
		// docs:start payments-get
		$payment = $client->payments->get($paymentUuid); // pay@…
		echo "{$payment->uuid}: {$payment->amountUsd} USD, tx {$payment->txHash}\n";

		// Runs GET /payments?reference= and returns its one payment (a NotFoundException when none).
		$payment = $client->payments->getByReference('order-1042'); // your order id
		echo "order-1042 was paid by {$payment->uuid}\n";
		// docs:end payments-get
	} catch (NotFoundException) {
		echo "No payment for order-1042 yet\n";
	}
}

// docs:start pagination-iterate
// The next page is fetched when the loop needs it; it stops after the page whose hasMore is false.
$count = 0;
foreach ($client->payments->iterate(new \QBitFlow\Params\PaymentListParams(limit: 50)) as $payment) {
	$count++;
	echo "{$payment->uuid} {$payment->amountUsd} USD\n";
}
echo "{$count} payments\n";
// docs:end pagination-iterate

// docs:start transactions-list
// One feed of one-time payments and subscription bills, newest first; each row reads like its
// resource. `type` filters it to one kind (TransactionKind::PAYMENT or TransactionKind::BILL).
$feed = $client->transactions->list(new \QBitFlow\Params\TransactionListParams(limit: 20));
foreach ($feed->items as $transaction) {
	echo "{$transaction->type} {$transaction->uuid} {$transaction->amountUsd} USD\n";
}
// docs:end transactions-list

// docs:start amounts-display
// Amounts in a token's smallest unit are decimal strings: convert them exactly, never through floats.
$display = \QBitFlow\Support\Amount::format('4990000', 6); // "4.99" (USDC has 6 decimals)
$minUnits = \QBitFlow\Support\Amount::parse('4.99', 6);     // "4990000"
echo "{$display} USDC = {$minUnits} min units\n";
// docs:end amounts-display
