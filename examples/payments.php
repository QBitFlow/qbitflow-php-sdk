<?php

/**
 * Payments: one page with a filter, one payment by its id and by your order reference, every
 * payment with the iterator, and exact amounts.
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
	printf("%s %s: %.2f USD\n", $payment->uuid, $payment->reference ?? '-', $payment->amount);
}
$cursor = $page->nextCursor; // null on the last page; else pass it back as `cursor` for the next one
// docs:end payments-list

$paymentUuid = $argv[1] ?? ($page->items[0]->uuid ?? null);
if ($paymentUuid !== null) {
	try {
		// docs:start payments-get
		$payment = $client->payments->get($paymentUuid); // pay@…
		echo "{$payment->uuid}: {$payment->amount} USD, tx {$payment->txHash}\n";

		$payment = $client->payments->getByReference('order-1042'); // your order id
		echo "order-1042 was paid by {$payment->uuid}\n";
		// docs:end payments-get
	} catch (NotFoundException) {
		echo "No payment for order-1042 yet\n";
	}
}

// docs:start pagination-iterate
$total = 0.0;
foreach ($client->payments->iterate(new \QBitFlow\Params\PaymentListParams(limit: 50)) as $payment) {
	$total += $payment->amount; // the next page is fetched when the loop needs it
}
printf("%.2f USD received\n", $total);
// docs:end pagination-iterate

// docs:start amounts-display
// Amounts in a token's smallest unit are decimal strings: convert them exactly, never through floats.
$display = \QBitFlow\Support\Amount::format('4990000', 6); // "4.99" (USDC has 6 decimals)
$minUnits = \QBitFlow\Support\Amount::parse('4.99', 6);     // "4990000"
echo "{$display} USDC = {$minUnits} min units\n";
// docs:end amounts-display
