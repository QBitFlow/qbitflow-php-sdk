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
	| Your QBitFlow API key (sk_…), from the dashboard. A key belongs to one space
	| (your organization's, or a member's) and one mode (test or live). Check it
	| with `php artisan qbitflow:verify`.
	|
	*/
	'api_key' => env('QBITFLOW_API_KEY'),

	/*
	|--------------------------------------------------------------------------
	| Base URL
	|--------------------------------------------------------------------------
	|
	| The API root. Leave it null for https://api.qbitflow.app/v2.
	|
	*/
	'base_url' => env('QBITFLOW_BASE_URL'),

	/*
	|--------------------------------------------------------------------------
	| Timeout
	|--------------------------------------------------------------------------
	|
	| Seconds each HTTP attempt may take (a retried call may take longer).
	|
	*/
	'timeout' => env('QBITFLOW_TIMEOUT', 30),

	/*
	|--------------------------------------------------------------------------
	| Retries
	|--------------------------------------------------------------------------
	|
	| How many times a read or one of the 7 idempotent creates is retried after a
	| network error, a 5xx, a 429 or a 409 idempotency_key_in_use (with an
	| exponential back-off). Other writes are never retried. 0 disables retries.
	|
	*/
	'max_retries' => env('QBITFLOW_MAX_RETRIES', 3),

	/*
	|--------------------------------------------------------------------------
	| Webhook secret
	|--------------------------------------------------------------------------
	|
	| Your webhook endpoint's whsec_… secret, shown once when the endpoint is
	| created. The `qbitflow.webhook` middleware verifies every delivery's
	| QBitFlow-Signature with it.
	|
	*/
	'webhook_secret' => env('QBITFLOW_WEBHOOK_SECRET'),

	/*
	|--------------------------------------------------------------------------
	| Webhook tolerance
	|--------------------------------------------------------------------------
	|
	| How far (seconds) a delivery's signed timestamp may be from your clock.
	|
	*/
	'webhook_tolerance' => env('QBITFLOW_WEBHOOK_TOLERANCE', 300),
];
