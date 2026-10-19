<?php

/**
 * Refunds: list the pending refunds (customers' requests) and read one. Refunds are read only in
 * the SDK: they are answered, or sent, from the dashboard.
 *
 *     QBITFLOW_API_KEY=sk_… php examples/refunds.php
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use QBitFlow\QBitFlow;

$client = QBitFlow::fromEnv();

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
	// pending (a customer's request), approved or rejected.
	echo "{$refund->uuid} {$refund->status} {$refund->refundPercent}% {$refund->amount} {$refund->currency->symbol}\n";
	// docs:end refunds-get
}
