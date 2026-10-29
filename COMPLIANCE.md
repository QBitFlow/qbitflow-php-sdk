# Compliance and data handling

This page describes what the QBitFlow PHP SDK does with data, for your privacy, security and
compliance reviews. It covers the SDK only: how QBitFlow itself processes data is described in
QBitFlow's own terms and privacy policy at [qbitflow.app](https://qbitflow.app).

## What the SDK is

A client library that runs inside your PHP application. It turns method calls into HTTPS
requests to the QBitFlow API, decodes the answers into typed objects, and verifies the webhooks
QBitFlow sends you. It performs no payment, custody or blockchain operation itself, and implements
no cryptography of its own: webhook signatures are checked with PHP's built-in `hash_hmac()`
(HMAC-SHA256) and `hash_equals()`, and TLS is handled by the HTTP client (Guzzle, or yours).

## Data it handles

- **Your credentials**: the API key you give the client, and the webhook secret you give the
  webhook verifier. They are kept in memory only, wrapped so they don't appear in stack traces,
  debug dumps or serialized objects (see
  [SECURITY.md](https://github.com/QBitFlow/qbitflow-php-sdk/blob/main/SECURITY.md)).
- **What you send**: the parameters of your calls, which may include your customers' personal data
  (names, email addresses, phone numbers, postal addresses), your references, and `metadata` you
  choose to attach. Send only what you need: `metadata` is stored by QBitFlow and copied into
  webhooks.
- **What the API returns**: payments, subscriptions, refunds, customers, members, wallets,
  accounting exports and events of your account, as decoded objects you own from then on.
- **Webhook bodies**: the events QBitFlow posts to your endpoint, verified and parsed in memory.

## Data it sends, and where

Only to the base URL you configure (by default `https://api.qbitflow.app/v2`), and only when you
call a method that needs the API:

- your request's path, query and JSON body;
- the headers `X-API-Key` (your key), `User-Agent: qbitflow-php/<SDK version>`, `Accept` and
  `Content-Type`, plus `On-Behalf-Of`, `Idempotency-Key` and `X-Request-Id` when they apply.

Local webhook verification (`Webhook::verify()`, `constructEvent()`, `parseEvent()`, the router,
the PSR-15 handler and the Laravel middleware) sends nothing. `$client->webhooks->verifyRemote()`
sends the webhook body and signature to the API, by design.

## What it stores

**Nothing.** The SDK keeps no cache, writes no file and opens no database, session or queue.
Results live in your process's memory until you discard them. The one exception is an explicit
command you run yourself: Laravel's `php artisan qbitflow:install` publishes the config file and
adds empty `QBITFLOW_API_KEY=` / `QBITFLOW_WEBHOOK_SECRET=` lines to your `.env` and `.env.example`
files when they are missing (it never writes or changes a value).

## No telemetry, no logging

The SDK collects no usage data, sends nothing to QBitFlow or anyone else beyond the API requests
you make, and contains no logging: it writes nothing to logs, `error_log()` or standard output
(the Artisan commands `qbitflow:install` and `qbitflow:verify` print only to the console you run
them in; `qbitflow:verify` never prints the key).
Errors are thrown as exceptions (with the API's `requestId` when there is one) for your own
error handling and logging to decide what to record.

## Dependencies

At runtime the SDK requires PHP (`~8.2.35 || ~8.3.36 || ~8.4.27 || >=8.5.12`, the releases with
the fixes for CVE-2026-109618 and CVE-2026-109619), the `json` extension, the PSR interface
packages `psr/http-client`, `psr/http-factory`, `psr/http-message` and `psr/http-server-handler`,
and Guzzle (`guzzlehttp/guzzle` 7.15.2 / 8.0.1 or later, with its `guzzlehttp/psr7` and
`guzzlehttp/promises`) as the default HTTP client. You can pass any other PSR-18 client and PSR-17
factories to the constructor instead (Symfony HttpClient, or a client with your own proxy and
middleware); either way the TLS, proxy and network settings are your HTTP client's. The Laravel
integration is used only when Laravel is installed.

## License

The SDK is open source under the
[Mozilla Public License 2.0](https://github.com/QBitFlow/qbitflow-php-sdk/blob/main/LICENSE).
