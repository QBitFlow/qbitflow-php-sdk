<?php

/**
 * A year of accounting events, as JSON rows and as CSV text.
 *
 *     QBITFLOW_API_KEY=sk_… php examples/accounting.php
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use QBitFlow\QBitFlow;

$client = QBitFlow::fromEnv();

// docs:start accounting-export
// The API exports at most 95 days at a time: the range helpers split any range and join the parts.
$events = $client->accounting->exportJsonRange('2026-01-01', '2026-12-31');
foreach ($events as $event) {
	echo "{$event->type} {$event->txTimeUtc->format('Y-m-d')} {$event->grossAmount} {$event->tokenSymbol}\n";
}

$csv = $client->accounting->exportCsvRange('2026-01-01', '2026-12-31'); // one header line, then the rows
// docs:end accounting-export
printf("%d event(s), %d CSV line(s)\n", count($events), substr_count($csv, "\n"));
