<?php

declare(strict_types=1);

/**
 * QBitFlow SDK configuration.
 *
 * Publish this file with:
 *   php artisan vendor:publish --tag=qbitflow-config
 */
return [
	/*
	|--------------------------------------------------------------------------
	| API key
	|--------------------------------------------------------------------------
	|
	| Your QBitFlow API key, from the dashboard. A test key keeps every action on
	| blockchain testnets, with data kept entirely separate from live mode.
	|
	*/
	'api_key' => env('QBITFLOW_API_KEY'),

	/*
	|--------------------------------------------------------------------------
	| Base URL
	|--------------------------------------------------------------------------
	|
	| Rarely worth changing. Leave it null to use the production API.
	|
	*/
	'base_url' => env('QBITFLOW_BASE_URL'),

	/*
	|--------------------------------------------------------------------------
	| Timeout
	|--------------------------------------------------------------------------
	|
	| Request timeout in SECONDS. (The JavaScript SDK expresses this in
	| milliseconds; PHP HTTP clients work in seconds, so this one does too.)
	|
	*/
	'timeout' => env('QBITFLOW_TIMEOUT', 30),

	/*
	|--------------------------------------------------------------------------
	| Retries
	|--------------------------------------------------------------------------
	|
	| How many times to retry a GET request that failed with a server (5xx) or
	| network error. POST, PUT and DELETE are never retried, nor are client
	| errors (4xx). Set to 0 to disable retries.
	|
	*/
	'max_retries' => env('QBITFLOW_MAX_RETRIES', 3),

	/*
	|--------------------------------------------------------------------------
	| Webhook secret
	|--------------------------------------------------------------------------
	|
	| Your webhook secret, from the dashboard under Settings → Webhooks. When set,
	| the `qbitflow.webhook` middleware verifies signatures locally (no API call, and
	| it keeps working when the API is unreachable). Leave it null to have the API
	| verify each delivery instead.
	|
	*/
	'webhook_secret' => env('QBITFLOW_WEBHOOK_SECRET'),
];
