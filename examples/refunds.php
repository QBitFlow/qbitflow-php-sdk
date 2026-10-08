<?php

/**
 * Refunds: list the active refunds and, given a payment id, refund half of it. The refund stays
 * pending until you sign the transfer in the dashboard.
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
		$refund = $client->refunds->initiate(new \QBitFlow\Params\InitiateRefundParams(
			txUuid: $paymentUuid, // pay@…, or a bill's sub-hist@…
			refundPercent: 50.0,  // of what the customer paid
			reason: 'Damaged item',
		));
		echo "Refund {$refund->uuid}: {$refund->status}\n"; // pending: no money moves until you sign it in the dashboard
		// docs:end refunds-create
	} catch (ConflictException $e) {
		echo "Not refunded ({$e->apiCode})\n"; // refund_already_exists: one refund per transaction
	}
}

// docs:start refunds-list
foreach ($client->refunds->list() as $refund) {
	echo "{$refund->uuid} {$refund->txUuid} ({$refund->initiatedBy}): {$refund->amountUsd} USD, {$refund->reason}\n";
}
// docs:end refunds-list
