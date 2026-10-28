# Upgrading the QBitFlow PHP SDK from 2.x to 3.0

**Who should read this:** anyone running `qbitflow/qbitflow-php` 2.x (2.0.0 to 2.1.0) in production,
on plain PHP or in a Laravel application. If you are starting fresh, read the
[README](https://github.com/QBitFlow/qbitflow-php-sdk/blob/main/README.md) instead.

3.0 is the SDK for **QBitFlow API v2**. 2.x talks to API v1 only, and **API v1 is retired**: your
data (products, customers, payments, subscriptions, sellers, wallets, webhook URLs) has already been
moved to v2, so you upgrade your integration in one go rather than call by call. Plan for a short
code change on every QBitFlow call, a new API key, and new webhook verification code (your
webhook endpoints and their secrets carried over).

The full list of changes is in the
[CHANGELOG](https://github.com/QBitFlow/qbitflow-php-sdk/blob/main/CHANGELOG.md).

## Contents

1. [What changed at a glance](#1-what-changed-at-a-glance)
2. [Upgrade steps](#2-upgrade-steps)
3. [Breaking changes, before and after](#3-breaking-changes-before-and-after)
4. [Method map: every 2.x method](#4-method-map-every-2x-method)
5. [Models, ids and enums](#5-models-ids-and-enums)
6. [New capabilities](#6-new-capabilities)
7. [Behaviour changes](#7-behaviour-changes)
8. [Final checklist](#8-final-checklist)

## 1. What changed at a glance

| Area | 2.x | 3.0 |
|---|---|---|
| API | `https://api.qbitflow.app/v1` | `https://api.qbitflow.app/v2` (53 public routes) |
| PHP | `^8.2` | `~8.2.35 \|\| ~8.3.36 \|\| ~8.4.27 \|\| >=8.5.12` (patched releases only) |
| API keys | v1 keys | new v2 keys (`sk_test_…` / `sk_live_…`): **old keys are refused** |
| Client | positional arguments | named arguments, checked at construction, `$client->me()` to check the key |
| Services | `oneTimePayments`, `transactionStatus`, `users`, `claims`, `apiKeys`, … | `checkoutSessions`, `payments`, `transactions`, `failures`, `subscriptions`, `refunds`, `members`, `invitations`, `wallets`, … |
| Ids | integers for products and users | UUID strings everywhere (a currency keeps its integer id) |
| Money | `float` | decimal strings (`'4.99'`), in requests and responses |
| Requests | `Dto\…` objects or arrays | `Params\…` classes with named arguments, validated before sending |
| Responses | `Dto\…` | read-only `Models\…` whose properties are the JSON keys |
| Lists | `getAll($limit, $cursor)` → `CursorData` | `list(new …ListParams(...))` → `Page`, plus `iterate()` generators |
| Errors | 7 exception classes, no field details | one class per condition, `apiCode`, `details`, `fieldErrors`, `requestId` |
| Retries | every method, on network errors and 5xx | reads and the 6 creates (with an `Idempotency-Key`), on network errors, 5xx, 429 |
| Webhooks | `X-Webhook-Signature-256` over canonical JSON, two payload shapes | `QBitFlow-Signature` over the raw body, 15 typed event types, a router |
| Sellers | `users->create()` provisioned accounts, claimed later | invitations: a seller sells once they accepted (`members`, held funds, trust) |
| Laravel | two webhook routes, three Laravel events | one webhook route, one Laravel event per event type, local verification |

## 2. Upgrade steps

### 2.1 Check your PHP version

3.0 requires a PHP release that fixes the "php2shell" interpreter vulnerabilities:

| Branch | Minimum |
|---|---|
| 8.2 | 8.2.35 |
| 8.3 | 8.3.36 |
| 8.4 | 8.4.27 |
| 8.5 and later | 8.5.12 |

Composer refuses to install 3.0 on an older patch release of your branch, on purpose: a payment
integration should not run on an interpreter with known remote-code-execution flaws. Check with
`php -v` and `composer check-platform-reqs --no-dev`, and upgrade PHP first if needed. The details
are in [SECURITY.md](https://github.com/QBitFlow/qbitflow-php-sdk/blob/main/SECURITY.md).

### 2.2 Install

```bash
composer require qbitflow/qbitflow-php:^3
```

The package name and the root namespace (`QBitFlow\`) do not change. You still need a PSR-18 HTTP
client and PSR-17 factories (Guzzle covers both and is already in every Laravel application). 3.0
adds one small dependency, `psr/http-server-handler` (the PSR-15 interface its webhook router
implements).

### 2.3 Create new API keys, and check your webhook endpoints

- **API keys:** create a key in the dashboard for each space and mode you use. Keys from before API
  v2 answer `401`. A key belongs to one space (your organization's, or a member's) and one mode
  (test or live). Treat it as an opaque secret.
- **Webhooks:** your existing endpoints were moved to v2, with the secret they had, and now receive
  v2 events signed with the new scheme ([§ 3.7](#37-webhooks)). 3.0 verifies them locally with that
  secret: if your application doesn't hold it yet, copy it (`whsec_…`) from the dashboard. New
  endpoints come from the dashboard or `$client->webhooks->endpoints->create()` (the answer carries
  the secret, once).

### 2.4 Base URL

The default base URL is now `https://api.qbitflow.app/v2`. If you pass `baseUrl:`, point it at v2.
The constructor **no longer reads `QBITFLOW_BASE_URL`** by itself (2.x's `Config` did): pass
`baseUrl:` explicitly, or use `QBitFlow::fromEnv()`, which reads `QBITFLOW_API_KEY`,
`QBITFLOW_BASE_URL` and `QBITFLOW_ON_BEHALF_OF`. The Laravel config still maps `QBITFLOW_BASE_URL`.

### 2.5 Laravel (10, 11, 12 and 13)

- **Auto-discovery** still registers the service provider and the `QBitFlow` facade alias.
- **Config:** re-publish or merge it. Run `php artisan qbitflow:install` (or
  `php artisan vendor:publish --tag=qbitflow-config --force`): `config/qbitflow.php` gains
  `webhook_secret` (`QBITFLOW_WEBHOOK_SECRET`) and `webhook_tolerance`
  (`QBITFLOW_WEBHOOK_TOLERANCE`, default 300 seconds). `api_key`, `base_url`, `timeout` and
  `max_retries` are unchanged.
- **`.env`:** set the new `QBITFLOW_API_KEY` and add `QBITFLOW_WEBHOOK_SECRET` (the endpoint's
  `whsec_…`). `qbitflow:install` adds the missing keys to `.env` and `.env.example`.
- **Routes:** replace `Route::qbitflowTransactionWebhook()` and `Route::qbitflowSubscriptionWebhook()`
  with one `Route::qbitflowWebhooks('webhooks/qbitflow')` and point your endpoint at it. Declare it
  in `routes/api.php`, or exclude its URI from CSRF protection (QBitFlow sends no CSRF token).
- **Listeners:** move to the new Laravel events ([§ 3.10](#310-laravel)).
- **Check:** `php artisan qbitflow:verify` calls the API and prints the key's role, organization,
  space, mode and member.

## 3. Breaking changes, before and after

The examples assume `use QBitFlow\QBitFlow;`, `use QBitFlow\Params\…;`, `use QBitFlow\Enums\…;` and
so on.

### 3.1 Creating the client

```php
// 2.x
$client = new QBitFlow(getenv('QBITFLOW_API_KEY'), 'https://api.qbitflow.app/v1', 10.0, 3);
$user = $client->users->get();
```

```php
// 3.0
$client = new QBitFlow(
    apiKey: (string) getenv('QBITFLOW_API_KEY'),
    timeout: 10.0,   // seconds per attempt; the base URL defaults to …/v2
    maxRetries: 3,
);
$me = $client->me(); // what the key is: role, space, mode
echo ($me->space?->test ?? false) ? 'test' : 'live', PHP_EOL;
```

- Use named arguments: the order changed (`onBehalfOf:` now sits before `httpClient:`).
- `QBitFlow::fromArray()` and `Config` are gone: use the constructor, `QBitFlow::fromEnv()`, and
  the constants `QBitFlow::DEFAULT_BASE_URL`, `DEFAULT_TIMEOUT` (`30.0`), `DEFAULT_MAX_RETRIES` (`3`).
- The constructor refuses, before sending anything, a blank key, one that doesn't start with `sk_`
  or contains a character no HTTP header can carry, a negative `maxRetries`, a non-positive
  `timeout`, and a `baseUrl` that isn't `http(s)` with a host or carries credentials, a query or a
  fragment. The error never quotes the key. Call `$client->me()` at start-up to check the key online.
- `timeout` now applies **per attempt** (to the HTTP client the SDK builds; an injected client keeps
  its own timeout).
- `$client->getApiKey()` is removed. `getBaseUrl()` stays; `getOnBehalfOf()` is new.

### 3.2 Acting for a seller (`onBehalfOf`)

```php
// 2.x: per service, with an integer user id
$payments = $client->oneTimePayments->onBehalfOf(123)->getAll(50);
```

```php
// 3.0: one client for every service, with the member's user UUID
$seller = $client->onBehalfOf('0192f1c2-7b3a-7c4d-9e5f-6a7b8c9d0e1f'); // $member->userUuid
$payments = $seller->payments->list(new PaymentListParams(limit: 50));

// or for one call
$payment = $client->payments->get('pay@…', null, new RequestOptions(onBehalfOf: '0192f1c2-…'));
```

- The per-service `onBehalfOf(int)` is gone. `$client->onBehalfOf(string $userUuid)` returns a new
  client sharing the same configuration; a non-UUID (or the nil UUID) is a `ValidationException` at
  once, an integer a `TypeError`. `onBehalfOf('')` returns a client at the organization level.
- It needs an organization key. Acting for a member, the request has the member's `user` role:
  `members`, `invitations` and `wallets->listForMember()` answer `403`.

### 3.3 Opening a checkout and reading its outcome

```php
// 2.x
$session = $client->oneTimePayments->createSession(new CreatePaymentSessionDto(
    reference: 'order-1042',
    productId: 42,
    price: 4.99,
    successUrl: 'https://shop.example.com/thanks',
));
$status = $client->transactionStatus->get($session->uuid, TransactionType::ONE_TIME_PAYMENT);
if ($status->status === TransactionStatusValue::COMPLETED) { /* … */ }
```

```php
// 3.0
$session = $client->checkoutSessions->createPayment(new CreatePaymentSessionParams(
    productUuid: '0192f1c2-1111-7c4d-9e5f-6a7b8c9d0e1f', // or productReference:, or productName: + priceUsd: '4.99'
    reference: 'order-1042',
    successUrl: 'https://shop.example.com/thanks?session=' . Placeholders::UUID,
));
header('Location: ' . $session->link);

$status = $client->checkoutSessions->get($session->uuid); // no type argument
if ($status->status === CheckoutSessionStatusValue::COMPLETED) {
    $payment = $client->payments->get($session->uuid); // the payment keeps the session's id
}
```

- `CreatePaymentSessionDto` → `CreatePaymentSessionParams`: `productId` (int) → `productUuid`
  (string), `customerUUID` → `customerUuid`, `price` (float) → `priceUsd` (string). New:
  `expiresInMinutes`, `metadata`, `fees` (`CheckoutFees`).
- `subscriptions->createSession(CreateSubscriptionSessionDto)` →
  `checkoutSessions->createSubscription(CreateSubscriptionSessionParams)`; `frequency`,
  `trialPeriod` (`Models\Duration`) and `minPeriods` can come from a subscription product.
- The answer is a `Models\CreatedCheckoutSession` (`link`, `uuid`, `expiresAt`, always set).
- **Fulfil on the webhook** (`payment.completed`, `subscription.created`), not on the redirect. A
  failed attempt leaves the session `created` with `lastAttempt` set: the customer can still pay.
- **Sellers' redirect URLs:** a member's own key may send `successUrl` / `cancelUrl` while the
  organization's `members.checkoutRedirects` policy allows it (on by default). With the policy off
  the call answers `403` `policy_disabled`, with `details['policy']` and `details['field']`.

### 3.4 Products and customers

```php
// 2.x
$product = $client->products->create(new CreateProductDto(name: 'Pro', description: 'Pro plan', price: 9.99, reference: 'pro'));
$client->products->update($product->id, new UpdateProductDto(price: 12.5));
$customer = $client->customers->getByEmail('ada@example.com');
```

```php
// 3.0
$product = $client->products->create(new CreateProductParams(name: 'Pro', priceUsd: '9.99', reference: 'pro'));
$client->products->update($product->uuid, new UpdateProductParams(priceUsd: '12.50'));
$page = $client->customers->list(new CustomerListParams(email: 'ada@example.com')); // several may share an email
```

- Products are named by UUID (`$product->uuid`); `price` → `priceUsd` (a string); `description`
  is optional; `subscription: new SubscriptionTermsParams(...)` makes a subscription product; new
  `metadata`, `isActive`, `removeSubscription`.
- Customers: `name` → `firstName`; `lastName` is optional; new `metadata`. On update, `null` leaves
  a field unchanged and `''` **clears** `lastName`, `phoneNumber` or `address`.
- `products->getAll()` returned every product; `products->list()` is paginated.
- Every `delete()` returns `void` (2.x returned a `SuccessResponse`).
- References are 1 to 100 of `A-Z a-z 0-9 . _ : @ -`, and never `.` or `..` alone (refused before
  sending, in bodies, filters and `getByReference()`).

### 3.5 Reading payments and subscriptions

```php
// 2.x
$payment = $client->oneTimePayments->getByReference('order-1042');
echo $payment->amount; // float, USD

$sub = $client->subscriptions->get('sub@…');
if ($sub->subscriptionStatus === SubscriptionStatus::ACTIVE) { /* … */ }
$history = $client->subscriptions->getPaymentHistory('sub@…');
$client->subscriptions->forceCancel('sub@…');
```

```php
// 3.0
$payment = $client->payments->getByReference('order-1042'); // NotFoundException when none
echo $payment->amountUsd;              // '10', a decimal string
echo $payment->amount, ' ', $payment->currency->symbol; // the token amount: '10 USDC'

$sub = $client->subscriptions->get('sub@…'); // cancelled ones too: a 404 no longer means "cancelled"
if ($sub->hasAccess()) { /* grant access until currentPeriodEnd, whatever the status */ }
foreach ($client->subscriptions->iterateBills('sub@…') as $bill) { /* every bill */ }
$result = $client->subscriptions->cancel('sub@…'); // immediate; CancelSubscriptionParams(immediate: false) for period end
echo $result->pending ? 'confirming on-chain' : $result->subscription->status;
```

- `Payment::$amount` is now the **token amount**; the USD value is `amountUsd`.
- `subscriptionStatus` → `status` (a string); `stopped` is a status now; `lastBillingDate` /
  `nextBillingDate` / `minimumCancellationDate` → `lastBilledAt` / `nextBillingAt` /
  `cancellableAt`.

### 3.6 Pagination

```php
// 2.x
$page = $client->customers->getAll(50);
while ($page->hasMore()) {
    $page = $client->customers->getAll(50, $page->nextCursor);
}
```

```php
// 3.0
foreach ($client->customers->iterate(new CustomerListParams(limit: 100)) as $customer) {
    echo $customer->email, PHP_EOL; // walks every page lazily
}

// or page by page
$page = $client->customers->list(new CustomerListParams(limit: 50));
while ($page->hasMore) {
    $page = $client->customers->list(new CustomerListParams(limit: 50, cursor: $page->nextCursor));
}
```

`Support\CursorData` → `QBitFlow\Page` (`items`, `hasMore` is a property, `nextCursor`). The cursor
is opaque: pass it back verbatim. Short bounded lists (`currencies->list()`, `wallets->…`,
`members->listHeldFunds()`, `accounting->exportJson()`) return a plain `list<…>`.

### 3.7 Webhooks

```php
// 2.x
$payload = file_get_contents('php://input');
WebhookVerifier::verify(
    getenv('QBITFLOW_WEBHOOK_SECRET'),
    $_SERVER['HTTP_X_WEBHOOK_TIMESTAMP'] ?? '',
    $_SERVER['HTTP_X_WEBHOOK_SIGNATURE_256'] ?? '',
    $payload,
);
$data = SessionWebhookResponse::fromArray(json_decode($payload, true));
```

```php
// 3.0, with the router: verifies, parses, dispatches and answers 200 / 400 / 500
use QBitFlow\Enums\EventType;
use QBitFlow\Events\Event;
use QBitFlow\Models\PaymentCompleted;
use QBitFlow\Webhooks\WebhookRouter;

(new WebhookRouter((string) getenv('QBITFLOW_WEBHOOK_SECRET')))
    ->on(EventType::PAYMENT_COMPLETED, function (PaymentCompleted $payment, Event $event): void {
        // fulfil $payment->reference once: deduplicate on $event->id
    })
    ->handleGlobals();
```

```php
// 3.0, low level
use QBitFlow\Events\PaymentCompletedEvent;
use QBitFlow\Webhooks\Webhook;

$event = Webhook::constructEvent(
    file_get_contents('php://input'),            // the raw body, exactly as received
    $_SERVER['HTTP_QBITFLOW_SIGNATURE'] ?? '',
    (string) getenv('QBITFLOW_WEBHOOK_SECRET'),
); // throws WebhookSignatureException (->reason) on a bad signature
if ($event instanceof PaymentCompletedEvent) {
    $reference = $event->data->reference;
}
```

| | 2.x | 3.0 |
|---|---|---|
| Headers | `X-Webhook-Signature-256`, `X-Webhook-Timestamp`, `X-Webhook-ID` | `QBitFlow-Signature: t=<unix>,v1=<hex>`, `QBitFlow-Event-Id`, `QBitFlow-Event-Type` |
| Signed content | `timestamp.` + the body re-encoded as canonical JSON | `t.` + the **raw body** |
| Secret rotation | one secret | two `v1=` signatures for 24 hours: either secret verifies |
| Body | a session payload or a subscription transition | an event envelope (`id`, `type`, `createdAt`, `test`, `userUuid`, `data`) |
| Types | 2 payload shapes | 15 event types, each with a typed `data` |
| Dashboard test | `X-Webhook-ID: test-webhook-id` | a `webhook.test` event, signed like any other |

- `WebhookVerifier` → `QBitFlow\Webhooks\Webhook`: `verify()`, `constructEvent()`, `parseEvent()`,
  `verifyRequest()` (PSR-7), `sign()` (for your tests). The timestamp check can't be skipped any
  more (default tolerance 300 seconds).
- `$client->webhooks->verify($payload, $signature, $timestamp): bool` (a call to the API) →
  `$client->webhooks->verifyRemote($endpointUuid, $rawBody, $signatureHeader): void`, which throws
  on a bad signature. Local verification (`Webhook::verify()`) needs no API call.
- Always pass the raw body string, never a decoded array.
- v1's transaction webhook becomes `payment.completed` / `subscription.created`, its subscription
  webhook `subscription.billed` / `subscription.statusChanged`; 11 more types are new
  ([§ 6](#6-new-capabilities)).

### 3.8 Errors

```php
// 2.x
try {
    $client->products->get(42);
} catch (NotFoundException $e) {
    // …
} catch (QBitFlowException $e) {
    echo $e->getStatusCode(), ' ', $e->getMessage();
    $body = $e->getResponse();
}
```

```php
// 3.0
use QBitFlow\Exceptions\{ApiException, ConflictException, NotFoundException, ValidationException};

try {
    $client->products->get('0192f1c2-1111-7c4d-9e5f-6a7b8c9d0e1f');
} catch (NotFoundException $e) {
    // …
} catch (ValidationException $e) {
    foreach ($e->fieldErrors as $error) {
        echo $error->field, ': ', $error->message, PHP_EOL; // wire names: 'priceUsd', 'frequency.unit'
    }
} catch (ApiException $e) {
    echo $e->status, ' ', $e->apiCode, ' (request ', $e->requestId, ')', PHP_EOL; // branch on apiCode
}
```

| 2.x | 3.0 |
|---|---|
| `ValidationException` (every 4xx other than 401/403/404/429) | `ValidationException` (400 `validation_failed`, and client-side checks with status 0), `BadRequestException` (other 400), `ConflictException` (409), `GoneException` (410), `IdempotencyException` (422 `idempotency_key_reused`), `ApiException` (any other) |
| `UnauthorizedException` | `AuthenticationException` (401) |
| `ForbiddenException` | `PermissionDeniedException` (403) |
| `NotFoundException` | `NotFoundException` (404, and a `getByReference()` that finds nothing) |
| `RateLimitException` | `RateLimitException` (429): `retryAfter` (int seconds, `0` when unknown), `limit`, `periodSeconds` |
| `ServerException` | `ServerException` (5xx, an unexpected 3xx, an unusable 2xx) |
| `NetworkException` | `NetworkException` (no response; `getPrevious()` is the PSR-18 exception) |
| — | `WebhookSignatureException` (`reason`) |
| `getStatusCode()` (`?int`) | `$e->status` / `getStatus()` (`0` without a response) |
| `getResponse()` (decoded body) | `$e->details` (the error's `details`), `$e->rawBody` |
| `getMessage()` | `getMessage()` adds the status, code, request id and field errors; `$e->errorMessage` is the API's message alone |
| — | `$e->apiCode`, `$e->requestId`, `$e->fieldErrors`, `$e->isRetryable()` |

Every class extends `ApiException`, which extends `QBitFlowException` (still the catch-all) and
implements `QBitFlow\Exceptions\ExceptionInterface`.

### 3.9 Retries and idempotency

```php
// 2.x: you may have wrapped calls in your own retry loop
```

```php
// 3.0: built in. Drop your loop, or pass maxRetries: 0.
$client->customers->create(new CreateCustomerParams(firstName: 'Ada', email: 'ada@example.com'),
    new RequestOptions(idempotencyKey: 'signup-' . $userId)); // optional: the SDK generates one per call
```

2.x retried **every** request (creates and updates included) on network errors and 5xx, which could
duplicate a create. 3.0 retries only what is safe: every read, and the 6 creates that send an
`Idempotency-Key` (`products->create`, `customers->create`, `checkoutSessions->createPayment`,
`checkoutSessions->createSubscription`, `invitations->create`, `webhooks->endpoints->create`), with
the same key on every attempt. It retries network errors, 5xx, 429 (honouring `Retry-After`) and
`409 idempotency_key_in_use`, waiting 1 s, 2 s, 4 s… (`maxRetries`, default 3); a 429 waits at
least its `Retry-After`, and one asking for more than 60 seconds is thrown at once as a
`RateLimitException`. Updates, deletes,
`cancel()` and `testBill()` are never retried. Redirects are never followed.

### 3.10 Laravel

```php
// 2.x routes/web.php
Route::qbitflowTransactionWebhook('webhooks/qbitflow/transactions');
Route::qbitflowSubscriptionWebhook('webhooks/qbitflow/subscriptions');

// 2.x listener
Event::listen(TransactionWebhookReceived::class, fn ($e) => fulfil($e->reference()));
```

```php
// 3.0 routes/api.php (or routes/web.php with the URI excluded from CSRF)
Route::qbitflowWebhooks('webhooks/qbitflow'); // one route, named qbitflow.webhooks

// 3.0 listener
use QBitFlow\Laravel\Events\PaymentCompleted;
Event::listen(PaymentCompleted::class, fn (PaymentCompleted $e) => fulfil($e->data->reference, $e->event->id));
```

| 2.x | 3.0 |
|---|---|
| `Events\TransactionWebhookReceived` (`payload`, `reference()`, `isSubscription()`) | `Events\PaymentCompleted`, `Events\SubscriptionCreated` (each with `event` and typed `data`) |
| `Events\SubscriptionBilled` (`billing`, `subscriptionReference`, `reference()`) | `Events\SubscriptionBilled` (`data->bill`, `data->subscription`) |
| `Events\SubscriptionStatusChanged` (`transition`, `reference()`) | `Events\SubscriptionStatusChanged` (`data`: the subscription with `previousStatus`) |
| — | `SubscriptionActionRequiredChanged`, `SubscriptionBillingFailed`, `SubscriptionUpcomingBill`, `RefundRequested`, `RefundApproved`, `RefundRejected`, `MemberJoined`, `MemberRemoved`, `HeldFundsReleased`, `CheckoutExpired`, `WebhookTestReceived`, and `WebhookReceived` for every delivery (unknown types included) |
| `WebhookController::transaction()`, `::subscription()` | an invokable `WebhookController` |
| `VerifyQBitFlowWebhook` verified through the API, answered the dashboard probe itself | verifies locally with `QBITFLOW_WEBHOOK_SECRET` (missing secret: 500; bad signature or body: 400) and stores the event in `$request->attributes->get('qbitflow.event')`; the dashboard test arrives as `WebhookTestReceived`. The `qbitflow.webhook` middleware alias stays |
| Facade `QBitFlow::oneTimePayments()`, `users()`, `apiKeys()`, `claims()`, `transactionStatus()`, `getApiKey()` | `QBitFlow::checkoutSessions()`, `payments()`, `transactions()`, `failures()`, `members()`, `invitations()`, `wallets()`, `me()`, `onBehalfOf()` (plus the services that kept their names) |

## 4. Method map: every 2.x method

Every 3.0 method also takes an optional `?RequestOptions $options` last argument (omitted below).
2.x create and update methods accepted a DTO **or an array**; 3.0 takes the params class only.

### Checkout and status (2.x `oneTimePayments`, `subscriptions`, `transactionStatus`)

| 2.x | 3.0 |
|---|---|
| `oneTimePayments->createSession($dto)` | `checkoutSessions->createPayment(CreatePaymentSessionParams)` |
| `subscriptions->createSession($dto)` | `checkoutSessions->createSubscription(CreateSubscriptionSessionParams)` |
| `transactionStatus->get($uuid, $type)` | `checkoutSessions->get($uuid)` |
| `oneTimePayments->getSession($uuid)`, `subscriptions->getSession($uuid)` | removed: `checkoutSessions->get()` for the state; a session that expired unpaid comes with `checkout.expired` |

### Payments (2.x `oneTimePayments`)

| 2.x | 3.0 |
|---|---|
| `oneTimePayments->get($uuid)` | `payments->get($uuid, ?ReadParams)` (`pay@…` or the bare UUID) |
| `oneTimePayments->getByReference($ref)` | `payments->getByReference($ref)` |
| `oneTimePayments->getAll($limit, $cursor)` | `payments->list(?PaymentListParams)` / `payments->iterate()` |
| `oneTimePayments->getAllCombined($limit, $cursor)` | `transactions->list(?TransactionListParams)` / `transactions->iterate()` (payments and bills) |
| `oneTimePayments->getCustomerForTransaction($uuid)` | removed: `$payment->customer` (a summary) or `customers->get($payment->customerUuid)` |

### Subscriptions

| 2.x | 3.0 |
|---|---|
| `subscriptions->get($uuid)` | `subscriptions->get($uuid, ?ReadParams)` |
| `subscriptions->getByReference($ref)` | `subscriptions->getByReference($ref)` |
| `subscriptions->getPaymentHistory($uuid)` | `subscriptions->listBills($uuid, ?BillListParams)` / `iterateBills()` |
| `subscriptions->forceCancel($uuid)` | `subscriptions->cancel($uuid, ?CancelSubscriptionParams)` → `SubscriptionCancellation` (`subscription`, `pending`) |
| `subscriptions->executeTestBilling($uuid)` | `subscriptions->testBill($uuid)` → `TestBill` (test mode only) |

### Refunds

| 2.x | 3.0 |
|---|---|
| `refunds->getAll()` | `refunds->list(new RefundListParams(status: [RefundStatus::PENDING]))` / `iterate()` |
| `refunds->getAllInactive($limit, $cursor)` | `refunds->list(new RefundListParams(status: [RefundStatus::APPROVED, RefundStatus::REJECTED]))` |
| `refunds->getByTransaction($txUuid)` | `refunds->list(new RefundListParams(txUuid: $txUuid))`, or `$payment->refund` |

Refunds are read-only in the SDK: a merchant's refund is sent from the dashboard.

### Customers and products

| 2.x | 3.0 |
|---|---|
| `customers->create($dto)` | `customers->create(CreateCustomerParams)` |
| `customers->get($uuid)` | `customers->get($uuid)` |
| `customers->getByReference($ref)` | `customers->getByReference($ref)` |
| `customers->getByEmail($email)` | `customers->list(new CustomerListParams(email: $email))` (several customers may share an email) |
| `customers->getAll($limit, $cursor)` | `customers->list(?CustomerListParams)` / `iterate()` |
| `customers->update($uuid, $dto)` | `customers->update($uuid, UpdateCustomerParams)` |
| `customers->delete($uuid)` | `customers->delete($uuid)`: `void` |
| `products->create($dto)` | `products->create(CreateProductParams)` |
| `products->get(int $id)` | `products->get(string $uuid)` |
| `products->getAll()` | `products->list(?ProductListParams)` / `iterate()` (paginated; `includeHidden`, `subscription`, `reference`) |
| `products->getByReference($ref)` | `products->getByReference($ref)` (hidden products included) |
| `products->update(int $id, $dto)` | `products->update(string $uuid, UpdateProductParams)` |
| `products->delete(int $id)` | `products->delete(string $uuid)`: `void` |

### Users, claims and API keys

v1 provisioned sellers and sold for them at once, the seller claiming the account and its funds
later. **v2 creates no accounts: a seller sells once they accepted an invitation.**

| 2.x | 3.0 |
|---|---|
| `users->create($dto)` | `invitations->create(CreateInvitationParams)` → `CreatedInvitation` (with its `link`); store the seller's `userUuid` from the `member.joined` webhook |
| `users->get()` | `$client->me()` |
| `users->getAll()` | `members->list(?MemberListParams)` / `iterate()` |
| `users->getById(int $id)` | `members->get($userUuid)` |
| `users->getByEmail($email)` | removed: iterate `members` and match `$member->email` |
| `users->update(int $id, $dto)` | `members->update($userUuid, new UpdateMemberParams('2.5'))` (only the organization fee; names and emails are the person's own) |
| `users->delete(int $id)` | `members->remove($userUuid)`: `void` (`409 held_funds_pending` while you hold their funds) |
| `claims->createRequest($userId)`, `claims->getRequestByUser($userId)` | `invitations->create()`, `invitations->list()` |
| `claims->getFunds()` | `members->listHeldFunds()`, `members->getHeldFunds($userUuid)`, `members->getOwnHeldFunds()` |
| `claims->triggerTestClaimFunds($userId)` | removed: release held funds in the dashboard; `members->trust($userUuid)` sends new payments to the seller directly |
| `apiKeys->getAll()`, `apiKeys->getForUser($userId)` | removed: keys are managed in the dashboard |

`holdFunds: true` (required on every invitation) is v1's claim model: the seller's payments are
held in your wallet until you trust them. Sellers migrated from v1 are already members; those who
never claimed are members whose funds you hold (`$member->trustedAt === null`).

### Wallets, accounting, currencies

| 2.x | 3.0 |
|---|---|
| `accounting->export($from, $to, $format)` | `accounting->exportJson($from, $to)` or `accounting->exportCsv($from, $to)` |
| `accounting->exportJson($from, $to)` | `accounting->exportJson($from, $to)` → `list<Models\AccountingEvent>` (92 days at most; `exportJsonRange()` for longer) |
| `accounting->exportCsv($from, $to)` | `accounting->exportCsv($from, $to)` (`exportCsvRange()` for longer) |
| `currencies->getAllAvailable($test)` | `currencies->list(new CurrencyListParams(test: $test))` |
| `currencies->getAllMain($test)` | `currencies->list(new CurrencyListParams(test: $test, main: true))` |

### Webhooks

| 2.x | 3.0 |
|---|---|
| `WebhookVerifier::verify($secret, $timestamp, $signature, $payload, …)` | `Webhook::verify($rawBody, $signatureHeader, $secret, $tolerance = 300)` |
| `WebhookVerifier::computeSignature()`, `canonicalJson()` | removed; `Webhook::sign($rawBody, $secret)` signs bodies for your tests |
| `WebhookVerifier::extractHeaders($headers)` | removed: read `Webhook::SIGNATURE_HEADER`, or `Webhook::verifyRequest($psr7Request, $secret)` |
| `webhooks->verify($payload, $signature, $timestamp): bool` | `webhooks->verifyRemote($endpointUuid, $rawBody, $signatureHeader): void` |
| `webhooks->signatureHeader()`, `timestampHeader()`, `webhookIdHeader()`, `HEADER_*` constants | `Webhook::SIGNATURE_HEADER`, `Webhook::EVENT_ID_HEADER`, `Webhook::EVENT_TYPE_HEADER` |
| `TEST_WEBHOOK_ID`, `webhooks->testWebhookId()`, `webhooks->isTestWebhook($id)` | `$event instanceof Events\WebhookTestEvent` (and `$event->test` for test mode) |
| `DEFAULT_MAX_TIMESTAMP_AGE_SECONDS` | `Webhook::DEFAULT_TOLERANCE` |

### Helpers

| 2.x | 3.0 |
|---|---|
| `Support\CursorData` | `QBitFlow\Page` |
| `CursorData::queryParams($limit, $cursor)` | the `limit:` and `cursor:` arguments of the list params |
| `Support\Duration` (`Duration::months(1)`, …) | `Models\Duration` (`Duration::months(1)`, …); the unit is a string, `value` 0 means none |
| `Support\Validate` | removed: every params class validates itself before the request |
| `Support\Dto` (`toArray()`, `jsonSerialize()`) | params have `toArray()` / `toQuery()`; models are read-only values built with `fromArray()` |

## 5. Models, ids and enums

### Ids

- **Every id is a string** (a currency keeps its integer id). Products: `Product::$id` (int) →
  `Product::$uuid`; `productId` → `productUuid` everywhere. People: `userId` → `userUuid` (`null`
  for the organization's own records). `organizationId` is gone from every model; use
  `$client->me()->space->organizationUuid`.
- Transactions keep their prefixed ids (`pay@…`, `sub@…`, `sub-hist@…`, `refund@…`, `transfer@…`);
  every route also accepts the bare UUID.
- Property names are the JSON keys, in lowerCamelCase with acronyms as words: `customerUUID` →
  `customerUuid`, `transactionHash` → `txHash`, `txId` → `txUuid`.
- **Map the numeric ids you stored:** iterate your products
  (`$client->products->iterate(new ProductListParams(includeHidden: true))`) and match on
  `reference`, and your members (`members->iterate()`) on `email`; store the UUIDs.

### Money

Every amount is a decimal string, never a float: USD amounts (`priceUsd: '4.99'`, `amountUsd`),
token amounts in the token's units (`amount: '10'` for 10 USDC, with `amountMinUnits` and the
`currency` object), and rates in **percent** (`organizationFeeBps: 250` →
`organizationFeePercent: '2.5'`). Inputs are strings with at most 2 decimals; a float is refused.
Compute with `bcmath` or `brick/math`, or convert with `Support\Amount::format()` / `parse()`.

### Renamed and removed models

| 2.x (`QBitFlow\Dto\…`) | 3.0 (`QBitFlow\Models\…` or `QBitFlow\Params\…`) |
|---|---|
| `Session\CreatePaymentSessionDto`, `Session\CreateSubscriptionSessionDto` | `Params\CreatePaymentSessionParams`, `Params\CreateSubscriptionSessionParams` |
| `CreateProductDto`, `UpdateProductDto` | `Params\CreateProductParams`, `Params\UpdateProductParams` |
| `CreateCustomerDto`, `UpdateCustomerDto` | `Params\CreateCustomerParams`, `Params\UpdateCustomerParams` |
| `CreateUserDto`, `UpdateUserDto` | `Params\CreateInvitationParams`, `Params\UpdateMemberParams` |
| `Session\LinkResponse` | `CreatedCheckoutSession` (`link`, `uuid`, `expiresAt`) |
| `TransactionStatus` | `CheckoutSessionStatus` (`status` a string; `settlementDetails` removed; + `uuid`, `lastAttempt`, `expiresAt`) |
| `Payment` | `Payment`: `amount` is the token amount (`amountUsd` the USD value); `name` / `description` → `productName` / `productDescription`; `currencyId` → `currency`; `metadata` (the fee split) → `settlement`, and `metadata` is now your own key-value data; + `priceUsd`, `fees`, `paid`, `paidUsd`, `refund`, `refundable`, `customer`, `confirmedAt`, … |
| `CombinedPayment` | `Transaction` (`source` → `type`: `TransactionKind::PAYMENT` or `BILL`) |
| `Subscription` | `Subscription`: `subscriptionStatus` → `status`; `frequency` is a `Duration` (was seconds); `allowance` is an `Allowance` object; `currencyId` → `currency`; + `currentPeriodEnd`, `actionRequired`, `priceUsd`, `fees`, `cancelledAt`, `metadata`, `dunning`, `hasAccess()` |
| `SubscriptionHistory` | `Bill` (+ `settlement`, `periodStart`, `periodEnd`) |
| `RefundEntry` | `Refund` (`txId` → `txUuid`; amounts as strings; + `refundPercent`, `paid`, `amount`, `settlement`, …) |
| `Customer` | `Customer` (`name` → `firstName`; `lastName` optional; + `metadata`) |
| `Product` | `Product` (`id` → `uuid`; `price` → `priceUsd`; + `subscription`, `paymentLink`, `metadata`) |
| `User` | `Member` (`id` → `userUuid`; `name` → `firstName`; `claimedAt` → `trustedAt`; fee in percent; + `joinedAt`, `acceptedCurrencies`) and `Me` for the current key |
| `AccountingEvent` | `AccountingEvent` (`paymentId` → `txUuid`, `productId` → `productUuid`, `tokenSymbol` → `currency`; amounts in the currency's units as decimal strings, with `…MinUnits` twins; a fee that doesn't apply is `null`) |
| `Metadata\PaymentMetadata`, `TxAmounts*`, `TxMetadata`, `BlockData`, `NetworkFees` | `Settlement` (`feePercent`, `split`, `transaction`), `Split`, `SettlementTransaction` |
| `Metadata\OrganizationFee`, `Metadata\ReferralFee` | `OrganizationFee`, `ReferralFee` (`address`, `feePercent`) |
| `Currency` | `Currency` (+ `code` such as `BASE:USDC`, `chain`) |
| `Session\SessionCheckout`, `OneTimePaymentSession`, `SubscriptionSession`, `PaygSubscriptionSession`, `StatusLinkResponse`, `Session\SessionWebhookResponse`, `SubscriptionStatusTransition` | removed (webhooks carry typed events, [§ 3.7](#37-webhooks)) |
| `ApiKey`, `Organization`, `ClaimFunds`, `ClaimRequestResponse`, `StatusResponse`, `StatusResponseError`, `SuccessResponse` | removed |

A field the API always sends is non-nullable; an optional one is `?T` and `null` when absent;
lists are arrays (`[]` when empty). Timestamps are `DateTimeImmutable`.

### Enums

2.x enum properties were PHP backed enum cases (`$sub->subscriptionStatus === SubscriptionStatus::ACTIVE`,
`->value` for the string). In 3.0 every enum property is a plain `string`, and the `Enums\…`
classes hold string constants: compare with `$sub->status === SubscriptionStatus::ACTIVE`. A value
the SDK doesn't know yet is kept as is, never an error.

| 2.x | 3.0 |
|---|---|
| `SubscriptionStatus::PAST_DUE` (`past_due`), `TRIAL_EXPIRED` (`trial_expired`) | `PAST_DUE` (`pastDue`), `TRIAL_EXPIRED` (`trialExpired`) |
| `SubscriptionStatus::ACTIVE`, `TRIAL`, `CANCELLED` | unchanged |
| `SubscriptionStatus::LOW_ON_FUNDS` | no status: `$sub->actionRequired === ActionRequired::TOP_UP_ALLOWANCE` |
| `SubscriptionStatus::PENDING` | no status: `$sub->actionRequired === ActionRequired::RAISE_MAXIMUM` |
| `Subscription::$stopped === true` | `SubscriptionStatus::STOPPED` (ends at `nextBillingAt`); new `PAUSED` |
| `TransactionStatusValue` (7 cases) | `CheckoutSessionStatusValue`: `CREATED`, `WAITING_CONFIRMATION`, `COMPLETED`, `EXPIRED`. There is no failed or cancelled status: a failed attempt is `CREATED` with `lastAttempt` |
| `TransactionType::ONE_TIME_PAYMENT` | `TransactionType::PAYMENT`; `CREATE_SUBSCRIPTION` unchanged; the other cases are removed |
| `RefundStatus::REFUSED` | `RefundStatus::REJECTED` (`rejected`) |
| `RefundStatus::FAILED` | removed: the API never sends it (a failed approval leaves the refund `pending`) |
| `CombinedPaymentSource::SUBSCRIPTION_HISTORY` | `TransactionKind::BILL` |
| `AccountingEventType::SUBSCRIPTION_HISTORY` | `AccountingEventType::BILL` |
| `UserRole` (`ADMIN`, `USER`, `OWNER`) | `Role` (`ADMIN`, `USER`): an API key is at most `admin` |
| `ExportFormat`, `SubscriptionWebhookType`, `TransactionShortType` | removed |

**Grant access while now < `currentPeriodEnd`** (`$subscription->hasAccess()`), whatever the
status: a check on `status === 'active'` would cut off paying customers (`stopped`, `paused`).

## 6. New capabilities

- **Checkout:** `checkoutSessions->expire()`, `waitForCompletion()`; fee lines (`CheckoutFees`:
  tax, shipping, QBitFlow's processing fee); `expiresInMinutes`; `metadata` copied onto the payment
  or subscription and into its webhooks; redirect placeholders `Placeholders::UUID` and
  `Placeholders::TRANSACTION_TYPE`.
- **Reads:** `transactions` (payments and bills in one feed), `failures` (failed payment attempts),
  `refunds->get()`, `subscriptions->list()`, `update()` (metadata), `getBill()`, filters on every
  list (dates, customer, product, reference, members), `iterate()` generators.
- **Marketplaces:** `invitations`, `members` (fee, trust, removal), held funds, `wallets` (balances,
  a member's wallets, accepted currencies), `$client->onBehalfOf()`.
- **Webhooks:** endpoints (`webhooks->endpoints->…`) and the event log (`webhooks->events->…`);
  15 typed event types (`payment.completed`, `subscription.created`, `subscription.billed`,
  `subscription.statusChanged`, `subscription.actionRequiredChanged`, `subscription.billingFailed`,
  `subscription.upcomingBill`, `refund.requested`, `refund.approved`, `refund.rejected`,
  `member.joined`, `member.removed`, `heldFunds.released`, `checkout.expired`, `webhook.test`); a
  `WebhookRouter` usable as plain PHP (`handleGlobals()`), PSR-7 (`handleRequest()`) or PSR-15
  (`psr15()`).
- **Helpers:** `$client->me()`, `QBitFlow::fromEnv()`, `Support\Amount`, `Currency::formatAmount()`,
  `Subscription::hasAccess()`, `accounting->exportJsonRange()` / `exportCsvRange()`, per-call
  `RequestOptions` (`onBehalfOf`, `idempotencyKey`, `requestId`).
- **Metadata:** your own `array<string,string>` on products, customers, checkouts and subscriptions
  (at most 50 keys; an update merges, `''` deletes a key).

## 7. Behaviour changes

- **Errors:** one exception class per condition ([§ 3.8](#38-errors)). Field errors come from the
  API's `details.errors`, named by their wire name. Branch on `$e->apiCode` (`unique_violation`,
  `merchant_not_ready`, `policy_disabled`…), never on the message. Quote `$e->requestId` to support.
- **Client-side validation:** every params class is checked before the request (lengths, formats,
  amounts, references, metadata, UUIDs): a `ValidationException` with status 0, nothing sent.
- **Retries and idempotency:** only reads and the 6 idempotent creates, with an automatic
  `Idempotency-Key` ([§ 3.9](#39-retries-and-idempotency)). Remove your own retry loops.
- **Pagination:** cursor-based `Page` objects and lazy `iterate()` generators
  ([§ 3.6](#36-pagination)).
- **Webhooks:** v2 envelopes only, signed with `QBitFlow-Signature` over the raw body; deliveries
  are at least once (deduplicate on `$event->id`) and should be answered with a 2xx quickly, also
  for types you ignore. A body over 1 MiB or nested deeper than 64 levels is refused.
- **Test vs live:** the key decides the mode: a test key reads and writes test data only, a live
  key live data only. Check it at start-up with `$client->me()->space->test`. Test mode caps a
  checkout at 5 USD; `subscriptions->testBill()` works in test mode only.
- **Sellers' checkouts:** a member's key may give `successUrl` / `cancelUrl` while the
  organization's `members.checkoutRedirects` policy allows it; otherwise `403 policy_disabled`
  (`details['policy']`, `details['field']`). A member's key doing something another member policy
  forbids gets `403 policy_disabled` too.
- **Secrets stay out of logs:** the API key and webhook secrets are marked `#[\SensitiveParameter]`
  (kept out of stack traces) and wrapped so that no dump of the client or the webhook router shows
  them; `serialize()` of the client throws.

## 8. Final checklist

- [ ] PHP is at or above the patched floor of your branch (`php -v`).
- [ ] `composer require qbitflow/qbitflow-php:^3` succeeded.
- [ ] New v2 API keys are deployed, one per space and mode; `$client->me()` succeeds at start-up and
      reports the expected mode.
- [ ] The client is built with named arguments; any `baseUrl` points at `/v2`.
- [ ] Stored product and user ids are mapped to UUIDs; `onBehalfOf()` receives member UUIDs.
- [ ] Every call uses its 3.0 service and params class ([§ 4](#4-method-map-every-2x-method)).
- [ ] Amounts are sent and read as decimal strings; `Payment::$amount` is read as a token amount.
- [ ] Lists use `list()` / `iterate()` and pass `nextCursor` back verbatim.
- [ ] Status checks use the string constants; access checks use `hasAccess()`.
- [ ] Error handling catches the 3.0 classes and branches on `apiCode`; custom retry loops are gone.
- [ ] The webhook handler verifies `QBitFlow-Signature` on the raw body with the endpoint's
      `whsec_…` secret, handles the event types you need, and deduplicates on the event id.
- [ ] Laravel: config re-published, `QBITFLOW_WEBHOOK_SECRET` set, `Route::qbitflowWebhooks()`
      registered (in `routes/api.php`, or excluded from CSRF), listeners moved to the new events, `qbitflow:verify` passes.
- [ ] Tested end to end with a test key (checkout, webhook, refund read) before switching to live.
