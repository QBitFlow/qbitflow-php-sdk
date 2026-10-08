<?php

/**
 * Creating a client: from the API key, checking it with me(), and from the environment.
 *
 *     QBITFLOW_API_KEY=sk_… php examples/client-setup.php
 *
 * The first client uses the default API server; the one from the environment also reads
 * QBITFLOW_BASE_URL and QBITFLOW_ON_BEHALF_OF when set.
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

// docs:start client-init
// One client per API key, shared by the whole application.
$client = new \QBitFlow\QBitFlow(apiKey: (string) getenv('QBITFLOW_API_KEY'));

// The recommended start-up check: which space and mode is this key in?
$me = $client->me(); // an AuthenticationException for an unknown or revoked key
printf("%s, role %s, %s mode\n", $me->space?->organizationName ?? '?', $me->role ?? '?', $me->space?->test ? 'test' : 'live');
// docs:end client-init

// docs:start config-from-env
// QBITFLOW_API_KEY (required), and QBITFLOW_BASE_URL and QBITFLOW_ON_BEHALF_OF when set, read from
// getenv(), $_ENV or $_SERVER (a .env loaded by phpdotenv works).
$client = \QBitFlow\QBitFlow::fromEnv();

// Named arguments override the environment and set the other options.
$client = \QBitFlow\QBitFlow::fromEnv(timeout: 10.0, maxRetries: 5);
// docs:end config-from-env
echo "Calling {$client->getBaseUrl()}\n";
