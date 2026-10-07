# QBitFlow PHP SDK

[![License: MPL-2.0](https://img.shields.io/badge/License-MPL%202.0-brightgreen.svg)](https://opensource.org/licenses/MPL-2.0)

Official PHP SDK for [QBitFlow](https://qbitflow.app) — non-custodial cryptocurrency payment
processing and on-chain subscriptions. Feature-equivalent to the
[Go](https://github.com/QBitFlow/qbitflow-go-sdk),
[Python](https://github.com/QBitFlow/qbitflow-python-sdk) and
[JavaScript](https://github.com/QBitFlow/qbitflow-js-sdk) SDKs.

Works in any PHP project, with first-class Laravel integration.

## Features

- 🔐 **Strongly typed** — readonly DTOs and backed enums for every request and response,
  typed exactly as the API's Go models (nullable only where the API can send `null`)
- 🧩 **Framework-agnostic** — PSR-18 / PSR-17, so it runs anywhere
- 🅻 **Laravel-ready** — auto-discovered service provider, facade, config and webhook routes
- 🔄 **Automatic retries** — with backoff on server and network failures
- 💳 **One-time payments** — accept crypto with a single call
- ♻️ **Recurring subscriptions** — on-chain, with trials and minimum terms
- 🔌 **Webhooks** — verification, plus typed Laravel events
- 👥 **Customers, products and users** — full CRUD
- 💰 **Refunds** — query and track refund entries
- 📊 **Accounting export** — JSON or CSV
- 🔑 **Account claims** — invite users and settle what you owe them
- 🎭 **Act on behalf of a user** — one org key, every user

## Table of Contents

- [Requirements](#requirements)
- [Installation](#installation)
- [Quick Start](#quick-start)
- [Configuration](#configuration)
- [Laravel Integration](#laravel-integration)
- [Acting on Behalf of a User](#acting-on-behalf-of-a-user)
- [Response Types](#response-types)
- [One-Time Payments](#one-time-payments)
- [Subscriptions](#subscriptions)
- [Transaction Status](#transaction-status)
- [Webhooks](#webhooks)
- [Customers](#customers)
- [Products](#products)
- [Users](#users)
- [API Keys](#api-keys)
- [Currencies](#currencies)
- [Refunds](#refunds)
- [Accounting Export](#accounting-export)
- [Claims](#claims)
- [Pagination](#pagination)
- [Error Handling](#error-handling)
- [Coming from the Go, Python or JavaScript SDK](#coming-from-the-go-python-or-javascript-sdk)
- [Examples](#examples)
- [Testing](#testing)
- [License](#license)

## Requirements

- PHP 8.2 or newer
- A [PSR-18](https://www.php-fig.org/psr/psr-18/) HTTP client and
  [PSR-17](https://www.php-fig.org/psr/psr-17/) factories — **every Laravel application
  already has these**, via Guzzle
- Laravel 10, 11 or 12 for the optional framework integration

## Installation

### Laravel

```bash
composer require qbitflow/qbitflow-php
```

That's the whole install. Laravel's HTTP client depends on Guzzle, which satisfies the
PSR-18 and PSR-17 requirements, so nothing else is pulled in — the SDK is the only package
added. The service provider and `QBitFlow` facade register themselves through package
discovery; there is nothing to add to `config/app.php` or `bootstrap/providers.php`.

Then run the installer and check your key:

```bash
php artisan qbitflow:install     # publishes config, adds QBITFLOW_API_KEY to .env
php artisan qbitflow:verify      # confirms the key works and shows who it belongs to
```

### Everywhere else

The SDK speaks PSR-18, so it uses whatever HTTP client your project already has. If you
don't have one, install Guzzle alongside it:

```bash
composer require qbitflow/qbitflow-php guzzlehttp/guzzle
```

`composer.json` requires `psr/http-client-implementation` and
`psr/http-factory-implementation`, so if your project has no implementation at all Composer
refuses the install and names what's missing — rather than letting it fail later on the
first API call. Guzzle, Symfony HTTP Client, Nyholm, Laminas Diactoros and Slim PSR-7 are
all detected automatically.

## Quick Start

### 1. Get your API key

Sign up at [QBitFlow](https://qbitflow.app) and copy your API key from the dashboard. A
**test key** keeps every action on blockchain testnets, with data kept entirely separate
from live mode.

### 2. Create a client

```php
use QBitFlow\QBitFlow;

$client = new QBitFlow('your-api-key');
```

In Laravel, skip this — set `QBITFLOW_API_KEY` in `.env` and resolve the client from the
container or the facade instead. See [Laravel Integration](#laravel-integration).

### 3. Take a payment

```php
use QBitFlow\Dto\Session\CreatePaymentSessionDto;

$payment = $client->oneTimePayments->createSession(new CreatePaymentSessionDto(
    productId: 1,
    successUrl: 'https://example.com/success',
    cancelUrl: 'https://example.com/cancel',
));

// Send this link to your customer
echo $payment->link;
```

> **Webhook URLs are configured in the dashboard**, under Settings → Webhooks, with
> separate Test and Live endpoints. They can no longer be set per session.
> See [Webhooks](#webhooks).

### 4. Start a subscription

```php
use QBitFlow\Dto\Session\CreateSubscriptionSessionDto;
use QBitFlow\Support\Duration;

$subscription = $client->subscriptions->createSession(new CreateSubscriptionSessionDto(
    frequency: Duration::months(1),     // bill monthly
    productId: 1,
    trialPeriod: Duration::days(7),     // optional 7-day trial
));

echo $subscription->link;
```

### 5. Check where a transaction stands

```php
use QBitFlow\Enums\TransactionType;

$status = $client->transactionStatus->get('transaction-uuid', TransactionType::ONE_TIME_PAYMENT);

if ($status->isCompleted()) {
    echo 'Paid. Transaction hash: ', $status->txHash;
} elseif ($status->isFailed()) {
    echo 'Not paid: ', $status->message;   // '' when the API gave no message
}
```

## Configuration

| Argument      | Type     | Default                        | Description                                       |
| ------------- | -------- | ------------------------------ | ------------------------------------------------- |
| `$apiKey`     | `string` | *(required)*                   | Your QBitFlow API key (blank or whitespace-only is rejected) |
| `$baseUrl`    | `string` | `https://api.qbitflow.app/v1`  | API base URL; also read from `QBITFLOW_BASE_URL` (a blank value means the default) |
| `$timeout`    | `float`  | `30`                           | Request timeout in **seconds**                    |
| `$maxRetries` | `int`    | `3`                            | Retry attempts for **GET** requests that hit a 5xx or network failure (`0` disables) |
| `$httpClient` | `ClientInterface` | auto-discovered       | Your own PSR-18 client                            |
| `$requestFactory`, `$streamFactory` | PSR-17 | auto-discovered | Your own PSR-17 factories                  |

```php
$client = new QBitFlow(
    apiKey: 'your-api-key',
    timeout: 10.0,
    maxRetries: 5,
);
```

> ⚠️ **`timeout` is in seconds**, unlike the JavaScript SDK, which uses milliseconds. PHP
> HTTP clients work in seconds, so this one does too.

### Bring your own HTTP client

Pass any PSR-18 client and the SDK will use it as-is. Configure the timeout on that client
— the SDK's `$timeout` only applies to a client it builds itself.

```php
$client = new QBitFlow(
    apiKey: 'your-api-key',
    httpClient: new GuzzleHttp\Client(['timeout' => 5]),
);
```

The SDK classifies every non-2xx response itself and never follows a redirect: the API
never redirects, so a 3xx means a misconfigured base URL and is reported as a
`ServerException` (following it would replay your `X-API-Key` to another host). Guzzle's
PSR-18 `sendRequest()` already disables `http_errors` and `allow_redirects`, so no Guzzle
option is needed; any other PSR-18 client must likewise return 4xx/5xx responses rather
than throw, and must not follow redirects.

## Laravel Integration

The service provider and facade register themselves through package discovery — there is
nothing to add to `config/app.php` or `bootstrap/providers.php`.

### Set up

```bash
php artisan qbitflow:install
```

This publishes `config/qbitflow.php`, adds `QBITFLOW_API_KEY` and `QBITFLOW_WEBHOOK_SECRET`
placeholders to `.env` and `.env.example` when they are missing, and prints what to do
next. It never overwrites a value you have already set, so it is safe to re-run.

You can also do it by hand — the only thing the SDK truly needs is the key:

```dotenv
QBITFLOW_API_KEY=your-api-key
# Optional
QBITFLOW_WEBHOOK_SECRET=        # enables local webhook verification, see Webhooks
QBITFLOW_BASE_URL=
QBITFLOW_TIMEOUT=30
QBITFLOW_MAX_RETRIES=3
```

```bash
php artisan vendor:publish --tag=qbitflow-config
```

### Check it works

```bash
php artisan qbitflow:verify
```

It calls `GET /user` and reports who the key belongs to, its role, and your organization —
so you find out now, rather than on your first checkout. It distinguishes a rejected key
from an unreachable API, and warns you if the key is user-level (which means `onBehalfOf()`
will 403). Handy after rotating keys, and in CI.

### Use it

Inject the client wherever you need it:

```php
use QBitFlow\QBitFlow;

final class CheckoutController
{
    public function __construct(private readonly QBitFlow $qbitflow) {}

    public function store(Request $request)
    {
        $payment = $this->qbitflow->oneTimePayments->createSession(new CreatePaymentSessionDto(
            reference: (string) $request->user()->currentOrder()->id,
            productId: 1,
        ));

        return redirect($payment->link);
    }
}
```

Or use the facade:

```php
use QBitFlow\Laravel\Facades\QBitFlow;

$products = QBitFlow::products()->getAll();
```

Every service is reachable both as a property (`$client->products`) and as a method
(`$client->products()`); the facade forwards to the method form.

### Supplying your own HTTP client

Bind a PSR-18 client in the container and the SDK uses it instead of auto-detecting one —
useful for an outbound proxy, request logging, or your own retry middleware:

```php
// AppServiceProvider::register()
$this->app->bind(\Psr\Http\Client\ClientInterface::class, fn () => new \GuzzleHttp\Client([
    'timeout' => 15,
    'proxy' => config('services.proxy'),
]));
```

PSR-17 factories are picked up from the container the same way
(`RequestFactoryInterface`, `StreamFactoryInterface`). Bind nothing and the SDK detects
Guzzle on its own.

### Testing

Swap the client for one backed by a mock transport, and no test touches the network:

```php
$this->app->instance(QBitFlow::class, new QBitFlow('test-key', httpClient: $mockPsr18Client));
```

## Acting on Behalf of a User

With an **organization (admin or owner) API key** you can run any request as one of your
users, without storing a key per user. Funds route to that user's wallet and your platform
fee is applied, exactly as if you had used their own key.

Scope the whole client with `$client->onBehalfOf($userId)`, or a single service with
`$client->products->onBehalfOf($userId)`. Both return a **scoped copy** that sends
`On-Behalf-Of: <userId>` on every request; your base client keeps operating at the
organization level, and the copies share its configuration and HTTP client.

```php
$userId = 123;

// Every service on $vendor acts for user 123
$vendor = $client->onBehalfOf($userId);
$products = $vendor->products->getAll();
$payment = $vendor->oneTimePayments->createSession(new CreatePaymentSessionDto(productId: 1));

// Or scope one service
$products = $client->products->onBehalfOf($userId)->getAll();

// Unaffected — still organization-level
$allProducts = $client->products->getAll();
```

`onBehalfOf(0)` returns an organization-level copy (the header is omitted), which is how you
undo an earlier `onBehalfOf()`; a negative id raises `ValidationException`.

> Requires an admin- or owner-level key. A regular user key gets a `403`
> (`ForbiddenException`); a user outside your organization gets a `404`.

## Response Types

Every response object is typed exactly as the API's Go model, so a field is nullable only
when the API can send `null` for it:

- A field the API always sends is a plain, non-nullable property. That includes optional
  fields the API omits when empty: they read as their zero value — `''`, `0`, `0.0` or
  `false` — never `null` (a customer's `phoneNumber` is `''` when none was given, a
  transaction status's `txHash` is `''` until it is broadcast).
- Timestamps are `DateTimeImmutable`. One the API always sends is never `null`; when it was
  never set it is Go's zero time, `0001-01-01T00:00:00Z` — test it with
  `QBitFlow\Support\Time::isZero()` (or helpers such as `Subscription::hasNextBilling()`).
- Only pointer fields are nullable: `Payment::$reference` and `$customerUUID`,
  `Subscription::$minimumCancellationDate`, `User::$claimedAt`, `ApiKey::$expiresAt`,
  `Currency::$mainCurrency`, `RefundEntry::$respondedAt` and `$metadata`,
  `TransactionStatus::$settlementDetails`, the webhook's `status`, and a few more — each is
  marked `?type` and documented.
- Lists are never `null`; the API's `null` reads as `[]`.
- Payments, combined-feed entries, subscriptions and billing records carry their full
  `Currency` object as `currency`.
- Decimal amounts (`amountMinUnits`, `allowance`, accounting token amounts, fee shares in
  smallest units) are strings, so no precision is lost; USD amounts are floats.

A field that is **absent** from a response decodes to its zero value, exactly as Go does. A
field of the **wrong JSON type** — a string where a number belongs, a list where an object
belongs — means the response does not have the documented shape and raises a
`ServerException` carrying the HTTP status; so does an integer beyond PHP's `int` range
rather than being silently rounded.

## One-Time Payments

### Create a session

Identify the product in one of three ways:

```php
// 1. By ID, pre-filling a customer by their (bare) UUID
$payment = $client->oneTimePayments->createSession(new CreatePaymentSessionDto(
    productId: 1,
    customerUUID: $customer->uuid,
));

// 2. By your own product reference
$payment = $client->oneTimePayments->createSession(new CreatePaymentSessionDto(
    productReference: 'PROD-PREMIUM',
    customerReference: 'user-42',
));

// 3. Inline, with no stored product
$payment = $client->oneTimePayments->createSession(new CreatePaymentSessionDto(
    productName: 'Custom Product',
    description: 'One-off charge',
    price: 99.99,               // USD
));

echo $payment->uuid;   // session UUID, pay@-prefixed
echo $payment->link;   // send this to the customer
```

Checked before the request, as the API checks them: the product must be given by
`productId` (> 0), `productReference`, or all of `productName` (2–100 characters, no
markup), `description` (2–500) and `price` (finite, > 0); `successUrl` / `cancelUrl` must be
absolute `http(s)` URLs; `customerUUID` must be a bare UUID (`xxxxxxxx-xxxx-…`, not a
`pay@…` id). An empty string means "not provided" for every optional field and is left off
the request.

### Use your own identifiers

Rather than storing QBitFlow's UUIDs, pass your own. Set `reference` to your order or
invoice ID; use `productReference` and `customerReference` to select an existing product or
customer by your identifier. If no customer matches, one is created during checkout.

```php
$payment = $client->oneTimePayments->createSession(new CreatePaymentSessionDto(
    reference: 'order-1234',
    productReference: 'PROD-PREMIUM',
    customerReference: 'user-42',
));

// Later, with nothing of QBitFlow's stored on your side
$settled = $client->oneTimePayments->getByReference('order-1234');
```

`reference` comes back on the resulting `Payment` and in webhook payloads.

> References and emails are escaped correctly in URL paths, but the API currently cannot
> route a reference containing `/` (it answers 404 even when escaped) — avoid `/` in the
> references you assign.

### Read payments

```php
$payment  = $client->oneTimePayments->get('payment-uuid');
$payment  = $client->oneTimePayments->getByReference('order-1234');
$session  = $client->oneTimePayments->getSession('session-uuid');
$customer = $client->oneTimePayments->getCustomerForTransaction('transaction-uuid');

$page = $client->oneTimePayments->getAll(limit: 20);

foreach ($page as $payment) {
    echo $payment->amount, ' USD in ', $payment->currency->symbol, ' — ', $payment->metadata->feeBps, ' bps', PHP_EOL;
}

// One-time payments and subscription billings in one feed
$combined = $client->oneTimePayments->getAllCombined(limit: 20);

foreach ($combined as $entry) {
    echo $entry->isSubscriptionBilling() ? 'renewal' : 'one-off', ': ', $entry->amount, PHP_EOL;
}
```

## Subscriptions

Identify the product exactly as for a one-time payment — `productId`, `productReference`, or
an inline `productName` + `description` + `price` — and add a billing `frequency`.

```php
use QBitFlow\Enums\SubscriptionStatus;
use QBitFlow\Support\Duration;
use QBitFlow\Support\Enums;

$subscription = $client->subscriptions->createSession(new CreateSubscriptionSessionDto(
    frequency: Duration::months(1),
    productId: 1,
    trialPeriod: Duration::days(7),
    minPeriods: 3,                      // the subscriber commits to 3 periods
));
```

`Duration` accepts `seconds`, `minutes`, `hours`, `days`, `weeks`, `months` and `years`,
via named constructors (`Duration::months(1)`) or directly
(`new Duration(1, DurationUnit::MONTHS)`). Values fit a Go `uint32` (0–4294967295); a
billing `frequency` must be at least 1, while a `trialPeriod` of 0 means no trial.
`minPeriods` accepts 0–4294967295, and 0 ("no minimum") is left off the request.

### Manage subscriptions

```php
$subscription = $client->subscriptions->get('subscription-uuid');
$subscription = $client->subscriptions->getByReference('sub-1234');

echo Enums::value($subscription->subscriptionStatus);   // active, past_due, trial, …
echo $subscription->currency->symbol;                   // e.g. USDC
echo $subscription->allowance;                          // remaining on-chain allowance, USD

if ($subscription->hasNextBilling()) {                  // false when nextBillingDate is Go's zero time
    echo $subscription->nextBillingDate->format('Y-m-d');
}

if ($subscription->subscriptionStatus === SubscriptionStatus::PAST_DUE) { /* nudge */ }

$history = $client->subscriptions->getPaymentHistory('subscription-uuid');

// Cancel immediately, bypassing the usual signed-cancellation flow
$client->subscriptions->forceCancel('subscription-uuid');

// Run a billing cycle now — test-mode subscriptions only. Not yet due → ConflictException.
$client->subscriptions->executeTestBilling('subscription-uuid');   // SuccessResponse
```

Both actions are `GET` routes on the API but perform an action, so the SDK never retries
them.

### Statuses the SDK does not know yet

Every enum-backed response field (`subscriptionStatus`, a transaction `status`, a refund
`status`, a user `role`, an accounting row `type`, …) is typed `<Enum>|string`. A value this
SDK knows hydrates to the enum member; a value the API added after this release arrives as
the raw string instead of being guessed or failing the whole response (an absent value is
the empty string `''`, never a default member). `===` against an enum case and the
`isActive()`-style helpers keep working (an unknown value matches nothing);
`QBitFlow\Support\Enums` renders and tests the union:

```php
use QBitFlow\Support\Enums;

Enums::value($subscription->subscriptionStatus);                      // 'active', or e.g. 'paused'
Enums::is($subscription->subscriptionStatus, SubscriptionStatus::ACTIVE);
Enums::isUnknown($subscription->subscriptionStatus);                  // upgrade the SDK when true
```

## Transaction Status

```php
use QBitFlow\Enums\TransactionType;
use QBitFlow\Enums\TransactionStatusValue;

$status = $client->transactionStatus->get($uuid, TransactionType::ONE_TIME_PAYMENT);

match (true) {
    $status->isCompleted() => handlePaid($status->txHash),   // '' until broadcast
    $status->isFailed()    => handleFailed($status->message),
    default                => handlePending(),
};
```

The type may also be passed as its raw wire string (`'claimFunds'`), for a type this SDK
has no enum case for yet.

To learn that a payment settled, use [webhooks](#webhooks) (recommended) or poll
`transactionStatus->get()`.

## Webhooks

### Verifying a webhook signature

Every webhook QBitFlow sends carries three headers:

| Header | Meaning |
|---|---|
| `X-Webhook-Signature-256` | HMAC signature, formatted `sha256=<hex>` |
| `X-Webhook-Timestamp` | Send time, in unix seconds |
| `X-Webhook-Id` | Transaction id, e.g. `pay@<uuid>` |

They are available as constants on `WebhookVerifier` (`HEADER_SIGNATURE`, `HEADER_TIMESTAMP`,
`HEADER_WEBHOOK_ID`, `TEST_WEBHOOK_ID`). There are two ways to check a webhook is genuine,
and you can use either:

| | Needs the secret | Network call | Use when |
|---|---|---|---|
| **Local** | yes | none | Default. Faster, and keeps working if the API is unreachable. |
| **Remote** | no | one per webhook | You would rather not hold the secret at all. |

Local verification performs the same three checks the server does: the timestamp is within
a replay window (5 minutes by default), the HMAC matches, and the comparison is
constant-time so a timing side channel cannot be used to guess the signature.

**Why the signature covers a canonical rendering, not the raw bytes.** JSON object key
order is not significant, and proxies, frameworks and logging layers routinely re-serialize
a body and reorder keys. Signing raw bytes would reject a payload that is in fact
untouched. So both sides sign `<timestamp>.<canonical-json>`, where canonical means exactly
what Go's `encoding/json` produces: keys sorted by their UTF-8 bytes at every level, no
insignificant whitespace, strings left as strings (a `"1000000000000000000"` wei amount
stays a string), and numbers in Go's float64 notation — whatever PHP's
`serialize_precision` is set to. You do not have to do anything for this — **pass the raw
body you received** and the SDK handles it. (An already-decoded array works too, but an
associative decode cannot tell `{}` from `[]`.)

> ⚠️ **Argument order.** The local check is
> `WebhookVerifier::verify($secret, $timestamp, $signature, $payload)` — timestamp *before*
> signature. The API-backed `$client->webhooks->verify($payload, $signature, $timestamp)`
> takes them the other way round. Both are plain strings, so a swap fails verification
> rather than erroring; use named arguments, as the snippets below do.

Get your webhook secret from the QBitFlow dashboard. Treat it like a password: keep it in
your environment or secret manager, never in source control.

```php
use QBitFlow\Exceptions\ValidationException;
use QBitFlow\Webhooks\WebhookVerifier;

$headers = WebhookVerifier::extractHeaders($_SERVER);
$body = file_get_contents('php://input');

try {
    WebhookVerifier::verify(
        secret: (string) getenv('QBITFLOW_WEBHOOK_SECRET'),
        timestamp: $headers['timestamp'],
        signature: $headers['signature'],
        payload: $body,
    );
} catch (ValidationException $e) {
    http_response_code(400);
    exit;
}

// The dashboard's "Test the endpoint" probe carries fake data. Check it *after* verifying:
// the probe is signed like any other delivery, so putting it through the same path proves
// your setup end to end — a broken secret shows up as a failed test, not a false success.
if ($headers['isTest']) {
    http_response_code(200);
    exit;
}

$event = json_decode($body, true);   // then SessionWebhookResponse / SubscriptionWebhook::fromArray()
http_response_code(200);
```

`extractHeaders()` takes any array of headers, so it works with `$_SERVER` (where PHP
exposes them as `HTTP_X_WEBHOOK_*`), `getallheaders()`, a PSR-7 `$request->getHeaders()`, or
Laravel's `$request->headers->all()`. Lookup is case-insensitive and tolerates the `HTTP_`
prefix and underscore spelling.

```php
// Laravel, on a route of your own (the shipped middleware does this for you — see below)
public function handle(Request $request)
{
    $headers = WebhookVerifier::extractHeaders($request->headers->all());

    try {
        WebhookVerifier::verify(
            secret: (string) config('qbitflow.webhook_secret'),
            timestamp: $headers['timestamp'],
            signature: $headers['signature'],
            payload: $request->getContent(),
        );
    } catch (ValidationException $e) {
        abort(400);
    }

    // ...
}
```

To widen or narrow the replay window (it must match the server's setting), pass
`maxTimestampAgeSeconds` (a value of 0 or less means the default of 300 seconds). The
timestamp is parsed like Go's `strconv.ParseInt`: digits with an optional sign, nothing
else.

```php
WebhookVerifier::verify($secret, $timestamp, $signature, $body, maxTimestampAgeSeconds: 600);
```

To verify through the API instead, with no secret in your process — the raw body is
forwarded byte for byte:

```php
$ok = $client->webhooks->verify(payload: $raw, signature: $signature, timestamp: $timestamp);
```

Configure your endpoints in the dashboard under **Settings → Webhooks**. There are two,
each with separate Test and Live URLs:

- **Transaction webhook** — a checkout you created was completed by the customer
- **Subscription webhook** — an existing subscription changed status, or was billed

Every delivery carries `X-Webhook-Signature-256`, `X-Webhook-Timestamp` and
`X-Webhook-Id`. **Always verify before acting**, and **always answer 200** — anything else
makes QBitFlow retry.

### In Laravel

Register the routes, and everything above is handled for you:

```php
// routes/api.php
Route::qbitflowTransactionWebhook('/webhooks/qbitflow/transaction');
Route::qbitflowSubscriptionWebhook('/webhooks/qbitflow/subscription');
```

Put these in `routes/api.php`. If you prefer `routes/web.php`, exclude the paths from CSRF
protection — QBitFlow does not send a CSRF token.

Each route verifies the signature — **locally** when `QBITFLOW_WEBHOOK_SECRET`
(`qbitflow.webhook_secret`) is set (no API key needed for that), through the API
otherwise — then answers the dashboard's
"Test the endpoint" probe automatically (after verifying it, so the button proves your
setup end to end; this assumes the probe is signed like a normal delivery), and dispatches a
typed event. Listen for the ones you care about:

```php
use QBitFlow\Laravel\Events\SubscriptionBilled;
use QBitFlow\Laravel\Events\SubscriptionStatusChanged;
use QBitFlow\Laravel\Events\TransactionWebhookReceived;

class MarkOrderPaid implements ShouldQueue
{
    public function handle(TransactionWebhookReceived $event): void
    {
        $order = Order::where('id', $event->reference())->firstOrFail();

        $event->isSubscription()
            ? $order->startSubscription($event->payload->session->uuid)
            : $order->markPaid($event->payload->status?->txHash ?? '');
    }
}

class RecordRenewal
{
    public function handle(SubscriptionBilled $event): void
    {
        Renewal::create([
            'subscription_uuid' => $event->subscriptionUUID,      // from the webhook envelope
            'order_id' => $event->reference() ?: null,            // your own reference ('' when none)
            'amount' => $event->billing->amount,
            'tx_hash' => $event->billing->transactionHash,
        ]);
    }
}

class ReactToStatusChange
{
    public function handle(SubscriptionStatusChanged $event): void
    {
        $uuid = $event->subscriptionUUID;                          // which subscription changed

        if ($event->transition->currentStatus === SubscriptionStatus::PAST_DUE) {
            // nudge the customer
        }
    }
}
```

Queue your listeners. The route answers 200 as soon as the event is dispatched, so slow
work in a synchronous listener risks a timeout and a redelivery. A status or type this SDK
does not know yet is delivered as a raw string (see [Statuses the SDK does not know
yet](#statuses-the-sdk-does-not-know-yet)) rather than failing the delivery.

To verify on a route of your own, apply the middleware directly:

```php
Route::post('/hooks/qbitflow', MyController::class)->middleware('qbitflow.webhook');
```

### Outside Laravel

```php
$raw = file_get_contents('php://input');
$webhookId = $_SERVER['HTTP_X_WEBHOOK_ID'] ?? null;

$valid = $client->webhooks->verify(
    payload: $raw,
    signature: $_SERVER['HTTP_X_WEBHOOK_SIGNATURE_256'] ?? '',
    timestamp: $_SERVER['HTTP_X_WEBHOOK_TIMESTAMP'] ?? '',
);

if (! $valid) {
    http_response_code(400);
    exit;
}

// The dashboard's "Test the endpoint" probe carries fake data. Check it *after*
// verifying: the probe is signed like any other delivery, so putting it through the same
// path proves your verification setup works end to end.
if ($client->webhooks->isTestWebhook($webhookId)) {
    http_response_code(200);
    exit;
}

$payload = json_decode($raw, true);

// Transaction webhooks have no `type`; subscription webhooks do.
$event = isset($payload['type'])
    ? SubscriptionWebhook::fromArray($payload)       // ->data is a SubscriptionStatusTransition or SubscriptionHistory
    : SessionWebhookResponse::fromArray($payload);   // ->session is a OneTimePaymentSession or SubscriptionSession

http_response_code(200);
```

> `verify()` returns `false` only when QBitFlow rejects the signature (HTTP 400). A bad API
> key (401/403), a server or network failure is rethrown as its own exception instead, so an
> outage or a misconfigured credential is never mistaken for a forgery — let it bubble,
> answer non-200, and the delivery is retried.

## Customers

`name` and `lastName` are 2–100 characters of letters, decimal digits, spaces and
`- _ ' .`; `email` must be a valid address. The SDK checks these before sending, mirroring
the API. `phoneNumber`, `address` and `reference` read back as `''` when none was given.

```php
use QBitFlow\Dto\CreateCustomerDto;
use QBitFlow\Dto\UpdateCustomerDto;

$customer = $client->customers->create(new CreateCustomerDto(
    name: 'John',
    lastName: 'Doe',
    email: 'john@example.com',
    phoneNumber: '+1234567890',
    reference: 'CRM-12345',
));

$customer = $client->customers->get($customer->uuid);   // a bare UUID
$customer = $client->customers->getByEmail('john@example.com');
$customer = $client->customers->getByReference('CRM-12345');

$page = $client->customers->getAll(limit: 50);

// Only the fields you set are sent; everything else is left untouched ('' also means "not set").
// Note: `reference` is immutable and cannot be updated.
$customer = $client->customers->update($customer->uuid, new UpdateCustomerDto(
    email: 'new@example.com',
));

$client->customers->delete($customer->uuid);
```

## Products

Prices are in USD (strictly greater than 0); QBitFlow converts to crypto at checkout using
live rates. `name` (2–100) and `description` (2–500 characters) may not contain markup —
the SDK checks these before sending, mirroring the API's `producttext` rule.

```php
use QBitFlow\Dto\CreateProductDto;
use QBitFlow\Dto\UpdateProductDto;

$product = $client->products->create(new CreateProductDto(
    name: 'Premium Plan',
    description: 'Access to all premium features',
    price: 29.99,
    reference: 'PROD-PREMIUM',
));

$product  = $client->products->get(1);
$product  = $client->products->getByReference('PROD-PREMIUM');
$products = $client->products->getAll();

// Partial update: only the fields you set are sent; the others keep their current value
$product = $client->products->update(1, new UpdateProductDto(price: 39.99));

$product = $client->products->update(1, new UpdateProductDto('Premium Plus', 'More features', 39.99));

$client->products->delete(1);
```

On update, a field you leave `null` is untouched, but an empty string for `name` or
`description` is rejected — as the API rejects it. A product `reference` is always set (the
API generates one when you give none).

## Users

Most of these require an admin or owner key.

```php
use QBitFlow\Dto\CreateUserDto;
use QBitFlow\Dto\UpdateUserDto;
use QBitFlow\Enums\UserRole;

$user = $client->users->create(new CreateUserDto(
    name: 'Jane',
    lastName: 'Smith',
    email: 'jane@example.com',
    role: UserRole::USER,
    organizationFeeBps: 100,        // 1%
));

$me    = $client->users->get();     // the user this API key belongs to
$user  = $client->users->getById(5);
$user  = $client->users->getByEmail('jane@example.com');
$users = $client->users->getAll();

// Partial update: omitted fields keep their current value
$client->users->update(5, new UpdateUserDto(name: 'Jane'));

// organizationFeeBps requires admin authority (an admin/owner key, or an
// organization-level key via onBehalfOf()). A non-admin caller sending it gets a 403.
$client->users->update(5, new UpdateUserDto(organizationFeeBps: 150));

// Note: passwords cannot be changed through this SDK. It is a JWT-only, self-service
// operation on the API, so the field is intentionally absent from UpdateUserDto.
$client->users->delete(5);
```

No password is set at creation — users set their own through the [claim flow](#claims).

## API Keys

Read-only. Creating and deleting keys requires a dashboard session and cannot be done with
an API key; manage them in the dashboard.

```php
$keys = $client->apiKeys->getAll();
$keys = $client->apiKeys->getForUser(42);      // admin or owner only
```

## Currencies

Public endpoints. Use them to resolve the currency IDs in `availableCurrencies` on a
session, and in `currencyId` on payments and subscriptions.

```php
$currencies = $client->currencies->getAllAvailable();       // native currencies and tokens
$chains     = $client->currencies->getAllMain();            // one per chain, no tokens
$testnets   = $client->currencies->getAllAvailable(test: true);

foreach ($currencies as $currency) {
    printf("%d: %s (%s)%s\n", $currency->id, $currency->name, $currency->symbol,
        $currency->isToken() ? ' — token' : '');
}
```

## Refunds

```php
use QBitFlow\Support\Enums;

$refund = $client->refunds->getByTransaction('transaction-uuid');   // public endpoint
$active = $client->refunds->getAll();                               // awaiting a decision
$page   = $client->refunds->getAllInactive(limit: 20);              // resolved

echo Enums::value($refund->status);   // pending, approved, refused, failed — or a newer raw value
echo $refund->respondedAt?->format('c'); // null while it awaits a decision
echo $refund->txHash;                    // '' until the refund is processed
```

## Accounting Export

```php
use QBitFlow\Support\Enums;

$events = $client->accounting->exportJson('2026-01-01', '2026-01-31');

foreach ($events as $event) {
    printf("%s | %s | $%.2f net\n", $event->paymentId, Enums::value($event->type), $event->netAmountUsd);
}

file_put_contents('export.csv', $client->accounting->exportCsv('2026-01-01', '2026-01-31'));
```

`export($from, $to, $format)` matches the other SDKs and returns either shape;
`exportJson()` and `exportCsv()` are the same call with a single, definite return type.
Dates must be real `YYYY-MM-DD` dates and `from` may not be after `to` — both checked
locally before the request; how long a window may be is left to the API. An error response
to a CSV export is parsed like any other (a 400 is a `ValidationException` with its fields).

Token amounts (`grossAmount`, `netAmount`, fees) are decimal **strings** so no precision is
lost; the matching `…Usd` fields are floats.

## Claims

Create users whose earnings your organization holds initially. When you are ready, raise a
claim request: the user follows the link, sets a password, connects a wallet, and their
funds become transferable.

```php
$claim = $client->claims->createRequest(42);
// Send $claim->link to the user

$claim = $client->claims->getRequestByUser(42);   // resend an existing link

foreach ($client->claims->getFunds() as $fund) {
    printf("User %d is owed $%.2f (funded: %s)\n",
        $fund->userId, $fund->totalAmountOwed, $fund->funded ? 'yes' : 'no');
}

// Test mode: compute now instead of waiting for the hourly job. A GET that creates an
// entry, so it is never retried.
$client->claims->triggerTestClaimFunds(42);
```

## Pagination

List endpoints that can grow return a `CursorData` page, which is countable and iterable.

```php
$cursor = null;

do {
    $page = $client->oneTimePayments->getAll(limit: 50, cursor: $cursor);

    foreach ($page as $payment) {
        echo $payment->uuid, PHP_EOL;
    }

    $cursor = $page->nextCursor;
} while ($page->hasMore());
```

## Error Handling

Every exception extends `QBitFlowException`, so one `catch` covers the SDK.

| Exception                | Raised on                                                    |
| ------------------------ | ------------------------------------------------------------ |
| `ValidationException`    | `400`, `422`, and local validation before a request is sent   |
| `UnauthorizedException`  | `401` — invalid or missing API key                            |
| `ForbiddenException`     | `403` — key not allowed to perform the request                |
| `NotFoundException`      | `404`                                                         |
| `ConflictException`      | `409` — e.g. a test billing that is not yet due               |
| `RateLimitException`     | `429` — see `getRetryAfter()`                                 |
| `QBitFlowException`      | any other `4xx` (the base type; check `getStatusCode()`)      |
| `ServerException`        | `5xx` once retries are exhausted, an unexpected `3xx`, an empty (non-`204`) or non-JSON `2xx`, or a `2xx` body of the wrong shape (a field of the wrong JSON type, an integer beyond PHP's range) — always with the HTTP status |
| `NetworkException`       | any transport-level failure: connection refused or reset, DNS errors, timeouts, truncated responses |

Every exception exposes `getStatusCode()` (null for local and network failures),
`getResponse()` (the decoded body) and `getFields()` — the API's per-field failures, one
`FieldError{field, message}` each, whenever it reported a list. Local validation raises a
`ValidationException` too, but with a null status code and no fields — its message names
the problem:

```php
try {
    $client->customers->create(new CreateCustomerDto(name: 'John', lastName: 'Doe', email: $email));
} catch (ValidationException $e) {
    if ($e->getStatusCode() === null) {
        $errors['form'] = $e->getMessage();           // caught locally, e.g. 'email must be a valid email address'
    }

    foreach ($e->getFields() as $field) {
        $errors[$field->field] = $field->message;     // from the API's 400, e.g. 'Email' => 'email already exists'
    }
}
```

A request body JSON cannot carry (a `NAN`/`INF` float, invalid UTF-8) is also a local
`ValidationException`, never a bare `JsonException`.

```php
use QBitFlow\Exceptions\NotFoundException;
use QBitFlow\Exceptions\QBitFlowException;
use QBitFlow\Exceptions\RateLimitException;

try {
    $payment = $client->oneTimePayments->get($uuid);
} catch (NotFoundException) {
    return null;
} catch (RateLimitException $e) {
    sleep($e->getRetryAfter() ?? 60);
} catch (QBitFlowException $e) {
    Log::error('QBitFlow request failed', [
        'message' => $e->getMessage(),
        'status' => $e->getStatusCode(),
        'response' => $e->getResponse(),
    ]);
    throw $e;
}
```

**Retries.** Only `GET` requests are retried, and only on a transport-level failure (any
PSR-18 client exception — including the connection resets and truncated responses Guzzle
reports as request exceptions) or a `5xx`, up to `maxRetries` times with exponential
backoff (1s, 2s, 4s). `POST`, `PUT` and `DELETE` are sent exactly once — a create that timed
out after the server processed it must not be replayed into a duplicate — as are
`forceCancel()`, `executeTestBilling()` and `triggerTestClaimFunds()`, which are `GET`
routes that perform an action. `4xx`, `429` and `3xx` are never retried; back off from a
rate limit on your own schedule using `getRetryAfter()` (read from a delta-seconds or an
HTTP-date `Retry-After`). `maxRetries: 0` disables retries.

## Coming from the Go, Python or JavaScript SDK

Since 2.5.0 the four QBitFlow SDKs share one behaviour: GET-only retries with exponential
backoff (action GETs excluded), the same exception taxonomy (`400`/`422` validation, `401`,
`403`, `404`, `409` conflict, `429` with retry-after, other `4xx` as the base type,
`5xx`/`3xx`/empty or malformed bodies as the server type, network failures), the same
client-side validation rules, the same response types and decoding policy (absent → zero
value, wrong type → server error), unknown enum values preserved as raw strings, escaped
path segments, client- and service-level `onBehalfOf`, byte-identical canonical JSON for
local webhook verification, and API verification that returns `false` only for a 400. The
differences that remain are idiomatic to PHP:

- **Naming follows the JavaScript SDK** (`camelCase`), which is also PHP's convention:
  `getByReference()`, `oneTimePayments`, `claims`.
- **`timeout` is in seconds**, not milliseconds.
- **Services are both properties and methods** — `$client->products` and
  `$client->products()` are the same object. The facade needs the method form.
- **`onBehalfOf()`** exists on the client and on every service, each returning a scoped copy.
  `onBehalfOf(0)` means organization level everywhere.
- **Timestamps are `DateTimeImmutable`.** Decimal strings (`allowance`, `amountMinUnits`,
  accounting token amounts) stay strings, to preserve precision. A timestamp the API always
  sends is never `null`; an unset one is Go's zero time, detected with `Time::isZero()`.
- **Enum-backed response fields are `<Enum>|string`** (Go/JS: plain strings; Python:
  `Enum | str`). Use `Enums::value()` to print either form.
- **Laravel integration** (service provider, facade, middleware, route macros, Artisan
  commands) has no equivalent elsewhere.

## Examples

Runnable examples live in [`examples/`](examples):

- [`client.php`](examples/client.php) — a tour of the SDK outside any framework
- [`webhook-server.php`](examples/webhook-server.php) — a webhook endpoint in plain PHP
- [`laravel/`](examples/laravel) — checkout controller, routes and queued webhook listeners

## Testing

```bash
composer test
```

The unit suite runs against a mock PSR-18 client, so nothing touches the network. The
opt-in integration suite (`tests/Integration`) talks to a real server: it is skipped unless
`QBITFLOW_API_KEY` is set, and it fails — rather than falling back to any default server —
when the key is set without `QBITFLOW_BASE_URL`.

To test your own code against the SDK, inject a mock client the same way:

```php
$client = new QBitFlow('test-key', httpClient: $yourMockPsr18Client);
```

## License

[MPL-2.0](LICENSE). See [TRADEMARKS.md](TRADEMARKS.md) for brand usage,
[SECURITY.md](SECURITY.md) to report a vulnerability, and [COMPLIANCE.md](COMPLIANCE.md)
for compliance posture.

## Support

- 📚 [Documentation](https://qbitflow.app/docs) · [API reference](https://qbitflow.app/docs/api)
- 🐛 [Issues](https://github.com/qbitflow/qbitflow-php-sdk/issues)
- ✉️ support@qbitflow.app
