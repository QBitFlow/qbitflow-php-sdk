# QBitFlow PHP SDK

[![License: MPL-2.0](https://img.shields.io/badge/License-MPL%202.0-brightgreen.svg)](https://opensource.org/licenses/MPL-2.0)

Official PHP SDK for [QBitFlow](https://qbitflow.app) — non-custodial cryptocurrency payment
processing and on-chain subscriptions. Feature-equivalent to the
[Python](https://github.com/QBitFlow/qbitflow-python-sdk) and
[JavaScript](https://github.com/QBitFlow/qbitflow-js-sdk) SDKs.

Works in any PHP project, with first-class Laravel integration.

## Features

- 🔐 **Typed** — readonly DTOs and backed enums for every request and response
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
- [Coming from the Python or JavaScript SDK](#coming-from-the-python-or-javascript-sdk)
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
    customerUUID: 'customer-uuid',
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
    customerUUID: 'customer-uuid',
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
    echo 'Not paid: ', $status->message;
}
```

## Configuration

| Argument      | Type     | Default                        | Description                                       |
| ------------- | -------- | ------------------------------ | ------------------------------------------------- |
| `$apiKey`     | `string` | *(required)*                   | Your QBitFlow API key                             |
| `$baseUrl`    | `string` | `https://api.qbitflow.app/v1`  | API base URL; also read from `QBITFLOW_BASE_URL`  |
| `$timeout`    | `float`  | `30`                           | Request timeout in **seconds**                    |
| `$maxRetries` | `int`    | `3`                            | Retry attempts for server and network failures    |
| `$httpClient` | `ClientInterface` | auto-discovered       | Your own PSR-18 client                            |

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
    httpClient: new GuzzleHttp\Client(['timeout' => 5, 'http_errors' => false]),
);
```

Set `'http_errors' => false` on a Guzzle client you supply: the SDK classifies non-2xx
responses itself and needs to see them.

## Laravel Integration

The service provider and facade register themselves through package discovery — there is
nothing to add to `config/app.php` or `bootstrap/providers.php`.

### Set up

```bash
php artisan qbitflow:install
```

This publishes `config/qbitflow.php`, adds a `QBITFLOW_API_KEY` placeholder to `.env` and
`.env.example` if they don't have one, and prints what to do next. It never overwrites a
key you have already set, so it is safe to re-run.

You can also do it by hand — the only thing the SDK truly needs is the key:

```dotenv
QBITFLOW_API_KEY=your-api-key
# Optional
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
            reference: $request->user()->currentOrder()->id,
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
    'http_errors' => false,   // required: the SDK classifies responses itself
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

Every service exposes `onBehalfOf()`. It returns a **scoped copy** — your base client keeps
operating at the organization level.

```php
$userId = 123;

// This user's products
$products = $client->products->onBehalfOf($userId)->getAll();

// A checkout credited to this user
$payment = $client->oneTimePayments->onBehalfOf($userId)->createSession(
    new CreatePaymentSessionDto(productId: 1),
);

// Unaffected — still organization-level
$allProducts = $client->products->getAll();
```

> Requires an admin- or owner-level key. A regular user key gets a `403`
> (`ForbiddenException`).

## One-Time Payments

### Create a session

Identify the product in one of three ways:

```php
// 1. By ID
$payment = $client->oneTimePayments->createSession(new CreatePaymentSessionDto(
    productId: 1,
    customerUUID: 'customer-uuid',
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

echo $payment->uuid;   // session UUID
echo $payment->link;   // send this to the customer
```

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

### Read payments

```php
$payment  = $client->oneTimePayments->get('payment-uuid');
$payment  = $client->oneTimePayments->getByReference('order-1234');
$session  = $client->oneTimePayments->getSession('session-uuid');
$customer = $client->oneTimePayments->getCustomerForTransaction('transaction-uuid');

$page = $client->oneTimePayments->getAll(limit: 20);

// One-time payments and subscription billings in one feed
$combined = $client->oneTimePayments->getAllCombined(limit: 20);

foreach ($combined as $entry) {
    echo $entry->isSubscriptionBilling() ? 'renewal' : 'one-off', ': ', $entry->amount, PHP_EOL;
}
```

## Subscriptions

A subscription always bills against an existing product, so supply either `productId` or
`productReference`.

```php
use QBitFlow\Support\Duration;

$subscription = $client->subscriptions->createSession(new CreateSubscriptionSessionDto(
    frequency: Duration::months(1),
    productId: 1,
    trialPeriod: Duration::days(7),
    minPeriods: 3,                      // the subscriber commits to 3 periods
    customerUUID: 'customer-uuid',
));
```

`Duration` accepts `seconds`, `minutes`, `hours`, `days`, `weeks`, `months` and `years`,
via named constructors (`Duration::months(1)`) or directly
(`new Duration(1, DurationUnit::MONTHS)`).

### Manage subscriptions

```php
$subscription = $client->subscriptions->get('subscription-uuid');
$subscription = $client->subscriptions->getByReference('sub-1234');

echo $subscription->subscriptionStatus->value;   // active, past_due, trial, …
echo $subscription->nextBillingDate->format('Y-m-d');
echo $subscription->allowance;                   // remaining on-chain allowance, USD

$history = $client->subscriptions->getPaymentHistory('subscription-uuid');

// Cancel immediately, bypassing the usual signed-cancellation flow
$client->subscriptions->forceCancel('subscription-uuid');

// Run a billing cycle now — test-mode subscriptions only
$client->subscriptions->executeTestBilling('subscription-uuid');
```

## Transaction Status

```php
use QBitFlow\Enums\TransactionType;
use QBitFlow\Enums\TransactionStatusValue;

$status = $client->transactionStatus->get($uuid, TransactionType::ONE_TIME_PAYMENT);

match (true) {
    $status->isCompleted() => handlePaid($status->txHash),
    $status->isFailed()    => handleFailed($status->message),
    default                => handlePending(),
};
```

> The API also exposes a status WebSocket. This SDK does not wrap it — a long-lived socket
> has no place in a typical PHP request. Use [webhooks](#webhooks), which are the
> recommended way to learn that a payment settled, or poll the method above.

## Webhooks

### Verifying a webhook signature

Every webhook QBitFlow sends carries three headers:

| Header | Meaning |
|---|---|
| `X-Webhook-Signature-256` | HMAC signature, formatted `sha256=<hex>` |
| `X-Webhook-Timestamp` | Send time, in unix seconds |
| `X-Webhook-Id` | Transaction id, e.g. `pay@<uuid>` |

There are two ways to check a webhook is genuine, and you can use either:

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
untouched. So both sides sign `<timestamp>.<canonical-json>`, where canonical means keys
sorted at every level and no insignificant whitespace. You do not have to do anything for
this — pass the body you received and the SDK handles it.

Get your webhook secret from the QBitFlow dashboard. Treat it like a password: keep it in
your environment or secret manager, never in source control.

```php
use QBitFlow\Exceptions\ValidationException;
use QBitFlow\Webhooks\WebhookVerifier;

$headers = WebhookVerifier::extractHeaders($_SERVER);
$body = file_get_contents('php://input');

if ($headers['isTest']) {
    http_response_code(200); // connectivity check, nothing to process
    exit;
}

try {
    WebhookVerifier::verify(
        getenv('QBITFLOW_WEBHOOK_SECRET'),
        $headers['timestamp'],
        $headers['signature'],
        $body,
    );
} catch (ValidationException $e) {
    http_response_code(400);
    exit;
}

$event = json_decode($body, true);
http_response_code(200);
```

`extractHeaders()` takes any array of headers, so it works with `$_SERVER` (where PHP
exposes them as `HTTP_X_WEBHOOK_*`), `getallheaders()`, a PSR-7 `$request->getHeaders()`, or
Laravel's `$request->headers->all()`. Lookup is case-insensitive and tolerates the `HTTP_`
prefix and underscore spelling.

```php
// Laravel
public function handle(Request $request)
{
    $headers = WebhookVerifier::extractHeaders($request->headers->all());

    try {
        WebhookVerifier::verify(
            config('services.qbitflow.webhook_secret'),
            $headers['timestamp'],
            $headers['signature'],
            $request->getContent(),
        );
    } catch (ValidationException $e) {
        abort(400);
    }

    // ...
}
```

To widen or narrow the replay window (it must match the server's setting), pass it as the
fifth argument:

```php
WebhookVerifier::verify($secret, $timestamp, $signature, $body, 600);
```

To verify through the API instead, with no secret in your process:

```php
$ok = $client->webhooks->verify($payload, $signature, $timestamp);
```

Configure your endpoints in the dashboard under **Settings → Webhooks**. There are two,
each with separate Test and Live URLs:

- **Transaction webhook** — a checkout you created was completed by the customer
- **Subscription webhook** — an existing subscription changed status, or was billed

Every delivery carries `X-Webhook-Signature-256`, `X-Webhook-Timestamp` and
`X-Webhook-ID`. **Always verify before acting**, and **always answer 200** — anything else
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

Each route verifies the signature, answers the dashboard's "Test the endpoint" probe
automatically, and dispatches a typed event. Listen for the ones you care about:

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
            : $order->markPaid($event->payload->status->txHash);
    }
}

class RecordRenewal
{
    public function handle(SubscriptionBilled $event): void
    {
        Renewal::create([
            'subscription_uuid' => $event->billing->subscriptionUUID,
            'amount' => $event->billing->amount,
            'tx_hash' => $event->billing->transactionHash,
        ]);
    }
}

class ReactToStatusChange
{
    public function handle(SubscriptionStatusChanged $event): void
    {
        if ($event->transition->currentStatus === SubscriptionStatus::PAST_DUE) {
            // nudge the customer
        }
    }
}
```

Queue your listeners. The route answers 200 as soon as the event is dispatched, so slow
work in a synchronous listener risks a timeout and a redelivery.

To verify on a route of your own, apply the middleware directly:

```php
Route::post('/hooks/qbitflow', MyController::class)->middleware('qbitflow.webhook');
```

### Outside Laravel

```php
$raw = file_get_contents('php://input');
$webhookId = $_SERVER['HTTP_X_WEBHOOK_ID'] ?? null;

// The dashboard's "Test the endpoint" probe carries fake data
if ($client->webhooks->isTestWebhook($webhookId)) {
    http_response_code(200);
    exit;
}

$valid = $client->webhooks->verify(
    $raw,
    $_SERVER['HTTP_X_WEBHOOK_SIGNATURE_256'] ?? '',
    $_SERVER['HTTP_X_WEBHOOK_TIMESTAMP'] ?? '',
);

if (! $valid) {
    http_response_code(400);
    exit;
}

$event = SessionWebhookResponse::fromArray(json_decode($raw, true));

http_response_code(200);
```

> Pass the **raw** body to `verify()`. Decoding and re-encoding it reorders keys and
> invalidates the signature.
>
> `verify()` returns `false` only when QBitFlow rejects the signature. A network or server
> failure is rethrown instead, so an outage is never mistaken for a forgery — let it bubble,
> answer non-200, and the delivery is retried.

## Customers

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

$customer = $client->customers->get('customer-uuid');
$customer = $client->customers->getByEmail('john@example.com');
$customer = $client->customers->getByReference('CRM-12345');

$page = $client->customers->getAll(limit: 50);

// Only the fields you set are sent; everything else is left untouched.
// Note: `reference` is immutable and cannot be updated.
$customer = $client->customers->update('customer-uuid', new UpdateCustomerDto(
    email: 'new@example.com',
));

$client->customers->delete('customer-uuid');
```

## Products

Prices are in USD; QBitFlow converts to crypto at checkout using live rates.

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

// All three fields replace the current values
// Partial update: omitted fields keep their current value
$product = $client->products->update(1, new UpdateProductDto(price: 39.99));

$product = $client->products->update(1, new UpdateProductDto('Premium Plus', 'More features', 39.99));

$client->products->delete(1);
```

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
$refund = $client->refunds->getByTransaction('transaction-uuid');   // public endpoint
$active = $client->refunds->getAll();                               // awaiting a decision
$page   = $client->refunds->getAllInactive(limit: 20);              // resolved

echo $refund->status->value;   // pending, approved, refused, failed
```

## Accounting Export

```php
$events = $client->accounting->exportJson('2026-01-01', '2026-01-31');

foreach ($events as $event) {
    printf("%s | %s | $%.2f net\n", $event->paymentId, $event->type->value, $event->netAmountUsd);
}

file_put_contents('export.csv', $client->accounting->exportCsv('2026-01-01', '2026-01-31'));
```

`export($from, $to, $format)` matches the other SDKs and returns either shape;
`exportJson()` and `exportCsv()` are the same call with a single, definite return type.

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

// Test mode: compute now instead of waiting for the hourly job
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
| `ValidationException`    | `400`, and local validation before a request is sent          |
| `UnauthorizedException`  | `401` — invalid or missing API key                            |
| `ForbiddenException`     | `403` — key not allowed to perform the request                |
| `NotFoundException`      | `404`                                                         |
| `RateLimitException`     | `429` — see `getRetryAfter()`                                 |
| `ServerException`        | `5xx`, once retries are exhausted                             |
| `NetworkException`       | connection failures, DNS errors, timeouts                     |

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

**Retries.** Server (`5xx`) and network failures are retried up to `maxRetries` times, with
the delay growing on each attempt (1s, 2s, 3s…). Client errors (`4xx`) are never retried —
repeating them would not help. Rate limits are not retried automatically either; back off
on your own schedule using `getRetryAfter()`.

## Coming from the Python or JavaScript SDK

The surface is the same; these are the deliberate differences.

- **Naming follows the JavaScript SDK** (`camelCase`), which is also PHP's convention:
  `getByReference()`, `oneTimePayments`, `claims`.
- **`timeout` is in seconds**, not milliseconds.
- **Services are both properties and methods** — `$client->products` and
  `$client->products()` are the same object. The facade needs the method form.
- **Timestamps are `DateTimeImmutable`.** Decimal strings (`allowance`, `amountMinUnits`,
  accounting token amounts) stay strings, to preserve precision.
- **Unknown enum values do not throw.** If the API adds a status this SDK has not seen, the
  field falls back to a sensible default rather than failing the whole response.
- **`claims->triggerTestClaimFunds()` sends the user ID as a path segment**
  (`/user/claim/funds/test-trigger/{userID}`), as the REST reference specifies. The
  JavaScript SDK sends it as a `?userID=` query parameter, which does not match the route.
- **Subscription sessions require a stored product** (`productId` or `productReference`),
  matching the REST reference and the Python SDK. The JavaScript SDK's shared validator
  also accepts an inline product here, which the endpoint does not.
- **`webhooks->verify()` distinguishes a rejected signature from an outage.** It returns
  `false` only for the former and rethrows the latter, following the Python SDK. The
  JavaScript SDK returns `false` for both.
- **No WebSocket transaction status.** Like the Python SDK, this one omits it; use webhooks
  or poll `transactionStatus->get()`.
- **No pay-as-you-go service.** PAYG session creation is disabled on the API, so it is not
  exposed. `PaygSubscriptionSession` still hydrates if you read an existing PAYG session.

## Examples

Runnable examples live in [`examples/`](examples):

- [`client.php`](examples/client.php) — a tour of the SDK outside any framework
- [`webhook-server.php`](examples/webhook-server.php) — a webhook endpoint in plain PHP
- [`laravel/`](examples/laravel) — checkout controller, routes and queued webhook listeners

## Testing

```bash
composer test
```

The suite runs against a mock PSR-18 client, so nothing touches the network. To test your
own code against the SDK, inject a mock client the same way:

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
