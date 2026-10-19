<?php

/**
 * Refunds: list the pending refunds, read one, and, given a payment id, refund half of it. The
 * refund stays pending until you sign the transfer in the dashboard.
 *
 *     QBITFLOW_API_KEY=sk_… php examples/refunds.php [pay@…]
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use QBitFlow\Exceptions\ConflictException;
use QBitFlow\QBitFlow;

$client = QBitFlow::fromEnv();

$paymentUuid = $argv[1] ?? null;
if ($paymentUuid !== null) {
	try {
		// docs:start refunds-create
		$refund = $client->refunds->create(new \QBitFlow\Params\CreateRefundParams(
			txUuid: $paymentUuid, // pay@…, or a bill's sub-hist@…
			refundPercent: '50',  // a percent string, of what the customer paid; null = "100"
			reason: 'Damaged item',
		));
		// Pending: no money moves until you sign the transfer in the dashboard (refund.approved).
		echo "Refund {$refund->uuid}: {$refund->status}\n";
		// docs:end refunds-create
	} catch (ConflictException $e) {
		echo "Not refunded ({$e->apiCode})\n"; // refund_already_exists: one refund per transaction
	}
}

// docs:start refunds-list
// The active ones: pending, waiting for an answer (no status: every status).
$page = $client->refunds->list(new \QBitFlow\Params\RefundListParams(
	status: [\QBitFlow\Enums\RefundStatus::PENDING],
));
foreach ($page->items as $refund) {
	echo "{$refund->uuid} {$refund->txUuid} ({$refund->initiatedBy}): {$refund->amount} {$refund->currency->symbol}, " . ($refund->reason ?? '-') . "\n";
}
// docs:end refunds-list

if ($page->items !== []) {
	$refundUuid = $page->items[0]->uuid;

	// docs:start refunds-get
	$refund = $client->refunds->get($refundUuid); // refund@…
	// pending, approved, rejected or cancelled (a merchant's draft dropped before it was sent).
	echo "{$refund->uuid} {$refund->status} {$refund->refundPercent}% {$refund->amount} {$refund->currency->symbol}\n";
	// docs:end refunds-get
}
