<?php

/**
 * The currencies customers can pay with (a public list: cache it rather than reading it per request).
 *
 *     QBITFLOW_API_KEY=sk_… php examples/currencies.php
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use QBitFlow\QBitFlow;

$client = QBitFlow::fromEnv();

// docs:start currencies-list
// One bounded list: the live currencies; test: the test networks'; main: the chains' native coins only.
foreach ($client->currencies->list() as $currency) {
	echo "{$currency->id}: {$currency->code} ({$currency->name} on {$currency->chain}), {$currency->decimals} decimals\n";
}
// docs:end currencies-list
