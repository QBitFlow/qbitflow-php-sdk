# QBitFlow PHP SDK

[![Packagist](https://img.shields.io/packagist/v/qbitflow/qbitflow-php.svg)](https://packagist.org/packages/qbitflow/qbitflow-php)
[![PHP](https://img.shields.io/badge/PHP-~8.2.35_%7C%7C_~8.3.36_%7C%7C_~8.4.27_%7C%7C_%3E%3D8.5.12-777BB4?style=flat&logo=php)](https://github.com/QBitFlow/qbitflow-php-sdk/blob/main/SECURITY.md#php-interpreter-vulnerabilities-php2shell)
[![CI](https://github.com/QBitFlow/qbitflow-php-sdk/actions/workflows/ci.yml/badge.svg)](https://github.com/QBitFlow/qbitflow-php-sdk/actions/workflows/ci.yml)
[![License: MPL-2.0](https://img.shields.io/badge/License-MPL_2.0-brightgreen.svg)](https://opensource.org/licenses/MPL-2.0)

The official PHP SDK for [QBitFlow](https://qbitflow.app), non-custodial crypto payments: hosted
checkouts, one-time payments, subscriptions, refunds, marketplaces with commissions and held
funds, accounting exports and signed webhooks. Customers pay from their own wallets, on Ethereum,
Base and Solana, straight to yours.

- **API v2**, every integrator route: 14 services, 53 public routes.
- **Typed end to end**: readonly params classes with named arguments, readonly models named
  exactly as the API's JSON, typed webhook events. Money is a decimal string everywhere, never a
  float.
- **Typed exceptions**, each carrying the API's code, request id and field errors.
- **Safe retries**: reads and creates are retried on network errors, 5xx and 429, and every create
  sends an `Idempotency-Key`, so a retry never charges or creates twice.
- **Iterators**: `foreach ($client->payments->iterate() as $payment)` walks every page lazily;
  `getByReference()` finds a product, customer, payment or subscription by your own reference.
- **Webhooks** verified locally (`QBitFlow-Signature`, secret rotation included) and parsed into
  typed events, with or without a client; a **webhook router** turns a delivery into your typed
  handler and the right HTTP answer (plain PHP, PSR-7/PSR-15, Laravel).
- **Integration helpers**: `QBitFlow::fromEnv()`, `waitForCompletion()`, `hasAccess()`, exact
  amount formatting, accounting exports over any range, `Webhook::sign()` for your tests.
- **PSR-18 / PSR-17**: any HTTP client; **Laravel** service provider, facade, middleware, events
  and Artisan commands included.

> Coming from 2.x? Read [MIGRATION-v3.md](https://github.com/QBitFlow/qbitflow-php-sdk/blob/main/MIGRATION-v3.md): 3.0.0 targets API v2 and changes the
> client, the models and most names.

## Contents

- [Installation](#installation)
- [Quick start](#quick-start)
- [Integration recipes](#integration-recipes)
- [Authentication and acting for a member](#authentication-and-acting-for-a-member)
- [Checkout sessions](#checkout-sessions)
- [Products and customers](#products-and-customers)
- [Payments and failures](#payments-and-failures)
- [Subscriptions](#subscriptions)
- [Refunds](#refunds)
- [Marketplaces](#marketplaces)
- [Wallets](#wallets)
- [Accounting export](#accounting-export)
- [Webhooks](#webhooks)
- [Currencies](#currencies)
- [Pagination and iterators](#pagination-and-iterators)
- [Errors](#errors)
- [Retries and idempotency](#retries-and-idempotency)
- [Configuration](#configuration)
- [Laravel](#laravel)
- [Migrating from 2.x](#migrating-from-2x)
- [Examples](#examples) · [Testing](#testing) · [License](#license) · [Support](#support) · [Security](#security)

## Installation

```bash
composer require qbitflow/qbitflow-php
```

### Requirements

- **PHP `~8.2.35 || ~8.3.36 || ~8.4.27 || >=8.5.12`**: 8.2.35, 8.3.36, 8.4.27, 8.5.12 or later in
  its branch, the releases that fix php2shell (CVE-2026-109618 / CVE-2026-109619: see
  [SECURITY.md](https://github.com/QBitFlow/qbitflow-php-sdk/blob/main/SECURITY.md#php-interpreter-vulnerabilities-php2shell)); Composer refuses an older
  interpreter. The `json` extension (bundled with PHP).
- A [PSR-18](https://www.php-fig.org/psr/psr-18/) HTTP client and
  [PSR-17](https://www.php-fig.org/psr/psr-17/) factories.
- Optional: **Laravel 10, 11, 12 or 13** for the service provider, facade, middleware, events
  and Artisan commands (auto-discovered; see [Laravel](#laravel)). The core client needs no
  framework.

The SDK has no runtime dependency beyond the PSR interfaces. Guzzle (already in every Laravel
application) and Symfony HttpClient are detected and used automatically; otherwise install one:

```bash
composer require guzzlehttp/guzzle        # or: symfony/http-client nyholm/psr7
```

## Quick start

Create a client (or [`QBitFlow::fromEnv()`](#a-client-from-the-environment)) and check its key:

<!-- docs:snippet client-init -->
```php
// One client per API key, shared by the whole application.
$client = new \QBitFlow\QBitFlow(apiKey: (string) getenv('QBITFLOW_API_KEY'));

// The recommended start-up check: which space and mode is this key in?
$me = $client->me(); // an AuthenticationException for an unknown or revoked key
printf("%s, role %s, %s mode\n", $me->space?->organizationName ?? '?', $me->role ?? '?', $me->space?->test ? 'test' : 'live');
```
<!-- /docs:snippet -->

A hosted checkout for a one-time payment of 4.99 USD:

<!-- docs:snippet checkout-create-payment -->
```php
$session = $client->checkoutSessions->createPayment(new \QBitFlow\Params\CreatePaymentSessionParams(
	productName: 'T-shirt',
	description: 'Blue, size M',
	priceUsd: '4.99', // a decimal string, never a float
	reference: 'order-1042', // your order id: unique per space
	successUrl: 'https://shop.example.com/orders/success?uuid=' . \QBitFlow\Placeholders::UUID,
	cancelUrl: 'https://shop.example.com/orders/cancel',
));

header('Location: ' . $session->link); // send the customer to the hosted checkout
```
<!-- /docs:snippet -->

The snippets in this README come from the runnable programs in [`examples/`](https://github.com/QBitFlow/qbitflow-php-sdk/tree/main/examples) and name
classes by their fully qualified names (`\QBitFlow\Params\…`), so they work in any namespace; a
`use` statement does the same.

Then fulfil the order when the [`payment.completed` webhook](#webhooks) arrives for
`$session->uuid`, never on the customer's redirect to your success page.

### Conventions

- **Services are properties** of the client (`$client->products`, `$client->transactions`,
  `$client->webhooks->endpoints`), methods are camelCase (`createPayment`, `getByReference`).
  Every method takes an optional [`RequestOptions`](#configuration) last.
- **Params are readonly classes** built with named arguments (`new CreateProductParams(name: …,
  priceUsd: …)`). Optional params arguments are nullable: `null` means "no filter" or "the defaults".
- **Ids are strings.** Resources are named by UUIDs; transactions by prefixed ids (`pay@…` a
  payment, `sub@…` a subscription, `sub-hist@…` a bill, `refund@…` a refund) that you pass back
  verbatim (the bare UUID works too). Only currencies keep numeric ids (`int`).
- **Models are readonly classes** whose properties are the API's JSON keys (`$payment->txHash`,
  `$payment->settlement->split->merchant->amount`). A field the API always sends is non-nullable
  (its zero value when absent: `''`, `0`, `false`, `[]`, the zero time `0001-01-01T00:00:00Z`,
  see `QBitFlow\Support\Time::isZero()`); an optional field is nullable (`null` when absent).
- **Money is a decimal string** everywhere, inputs included, never a float: USD amounts
  (`priceUsd: '4.99'`, `amountUsd`), token amounts in the token's units (`amount` `"10"` for 10
  USDC, next to `amountMinUnits` and the `currency` object) and percents (`feePercent` `"0.99"`,
  `refundPercent` `"50"`). Convert token amounts exactly with [`Amount`](#showing-amounts).
- **Metadata**: products, customers, checkouts (copied onto their payment or subscription) and
  subscriptions carry your own `metadata`, an `array<string,string>` (`[]` without any), merged
  on updates (a key set to `''` deletes it).
- **Enums** are plain strings with a constants class (`QBitFlow\Enums\SubscriptionStatus::ACTIVE`).
  A value this SDK does not know yet is kept as is: give every `match` a `default` arm.
- **Times** are `DateTimeImmutable`, with the offset the API sent and microseconds.

### Services at a glance

53 public routes, plus local webhook helpers. `iterate()` / `iterateBills()` walk every page of
the list next to them; a "bounded" list answers one page, returned as a plain array.

| Service | Methods (route) |
|---|---|
| client | `me()` (`GET /me`), `onBehalfOf($userUuid)` (a derived client) |
| `products` | `list()` / `iterate()` (`GET /products`), `create()` (`POST /products`), `get()` (`GET /products/:uuid`), `getByReference()` (`GET /products?reference=`), `update()` (`PATCH /products/:uuid`), `delete()` (`DELETE /products/:uuid`) |
| `customers` | `list()` / `iterate()`, `create()`, `get()`, `getByReference()`, `update()`, `delete()` (`/customers…`, as products) |
| `currencies` | `list()` (`GET /currencies`, bounded), `get($id)` (`GET /currencies/:id`) |
| `checkoutSessions` | `createPayment()` (`POST /checkout-sessions/payment`), `createSubscription()` (`POST /checkout-sessions/subscription`), `get()` (`GET /checkout-sessions/:uuid`), `expire()` (`POST /checkout-sessions/:uuid/expire`), `waitForCompletion()` (polls `get()`) |
| `payments` | `list()` / `iterate()` (`GET /payments`), `get()` (`GET /payments/:uuid`), `getByReference()` |
| `transactions` | `list()` / `iterate()` (`GET /transactions`: payments and bills) |
| `failures` | `list()` / `iterate()` (`GET /payment-failures`) |
| `subscriptions` | `list()` / `iterate()` (`GET /subscriptions`), `get()` (`GET /subscriptions/:uuid`), `getByReference()`, `update()` (`PATCH /subscriptions/:uuid`), `listBills()` / `iterateBills()` (`GET /subscriptions/:uuid/bills`), `getBill()` (`GET /bills/:uuid`), `cancel()` (`POST /subscriptions/:uuid/cancel`), `testBill()` (`POST /subscriptions/:uuid/test-bill`) |
| `refunds` | `list()` / `iterate()` (`GET /refunds`), `get()` (`GET /refunds/:uuid`) |
| `members` | `list()` / `iterate()` (`GET /members`), `get()`, `update()`, `remove()` (`GET` / `PATCH` / `DELETE /members/:userUuid`), `trust()` (`POST /members/:userUuid/trust`), `listHeldFunds()` (`GET /members/held-funds`, bounded), `getHeldFunds()` (`GET /members/:userUuid/held-funds`), `getOwnHeldFunds()` (`GET /held-funds`) |
| `invitations` | `create()` (`POST /invitations`), `list()` / `iterate()` (`GET /invitations`), `revoke()` (`DELETE /invitations/:uuid`) |
| `wallets` | `list()` (`GET /wallets`), `listForMember()` (`GET /members/:userUuid/wallets`), `listAcceptedCurrencies()` (`GET /wallets/accepted-currencies`), all bounded |
| `accounting` | `exportJson()` / `exportCsv()` (`GET /accounting/export`, at most 92 days), `exportJsonRange()` / `exportCsvRange()` (any range, split and joined) |
| `webhooks` | `verifyRemote()` (`POST /webhooks/verify`); local, no request: `router()`, `verify()`, `constructEvent()`, `parseEvent()` |
| `webhooks->endpoints` | `list()` / `iterate()`, `create()`, `get()`, `update()`, `delete()` (`/webhooks/endpoints…`) |
| `webhooks->events` | `list()` / `iterate()` (`GET /webhooks/events`), `get()` (`GET /webhooks/events/:id`) |

## Integration recipes

The usual integration in a few lines: a client from the environment, a checkout, the webhook that
fulfils the order, and access control.

### A client from the environment

<!-- docs:snippet config-from-env -->
```php
// QBITFLOW_API_KEY (required), and QBITFLOW_BASE_URL and QBITFLOW_ON_BEHALF_OF when set, read from
// getenv(), $_ENV or $_SERVER (a .env loaded by phpdotenv works).
$client = \QBitFlow\QBitFlow::fromEnv();

// Named arguments override the environment and set the other options.
$client = \QBitFlow\QBitFlow::fromEnv(timeout: 10.0, maxRetries: 5);
```
<!-- /docs:snippet -->

A missing `QBITFLOW_API_KEY` is a `ValidationException` naming it; nothing is sent.

### The webhook endpoint

A `WebhookRouter` verifies the signature over the raw body, parses the event, runs the handler of
its type with the **typed data**, and says what to answer: 200 when handled or ignored, 400 for a
bad signature or a body that is not a v2 event, 500 when your handler throws (QBitFlow retries).
Deliveries are at least once: make the handlers idempotent, deduplicating on `$event->id`.

In plain PHP (a script such as `public/webhooks/qbitflow.php` that requires Composer's autoloader):

<!-- docs:snippet webhook-handler -->
```php
$router = (new \QBitFlow\Webhooks\WebhookRouter((string) getenv('QBITFLOW_WEBHOOK_SECRET')))
	->on(\QBitFlow\Enums\EventType::PAYMENT_COMPLETED, function (
		\QBitFlow\Models\PaymentCompleted $payment,
		\QBitFlow\Events\Event $event,
	): void {
		// Deliveries are at least once: skip an $event->id you already processed.
		error_log("event {$event->id}: fulfil order {$payment->reference} ({$payment->uuid}), {$payment->amountUsd} USD");
	})
	->on(\QBitFlow\Enums\EventType::SUBSCRIPTION_STATUS_CHANGED, function (
		\QBitFlow\Models\SubscriptionStatusChanged $subscription,
	): void {
		error_log("{$subscription->uuid} is {$subscription->status}, access: " . ($subscription->hasAccess() ? 'yes' : 'no'));
	})
	->onError(fn (?\QBitFlow\Events\Event $event, \Throwable $error) => error_log("webhook refused or failed: {$error->getMessage()}"));

// Reads php://input and the QBitFlow-Signature header, runs the handler of the event's type, and
// answers 200 (ignored types too), 400 (bad signature) or 500 (a handler threw: QBitFlow retries).
$router->handleGlobals();
```
<!-- /docs:snippet -->

`handleGlobals()` answers 405 to anything but a POST and 413 to a body over 1 MiB, and returns the
`WebhookResult` (`status`, `event`, `error`). Types without a handler (and the types added after
this SDK: `onUnknown()`) are answered 200; `onAny()` runs for every event.

**Laravel**: one route, and listeners for the Laravel events (see [Laravel](#laravel)):

```php
// routes/api.php
Route::qbitflowWebhooks('webhooks/qbitflow'); // verified with QBITFLOW_WEBHOOK_SECRET, dispatched as events
```

or the router on a route of your own (the container's router is built on `QBITFLOW_WEBHOOK_SECRET`):

```php
use Illuminate\Http\Request;
use QBitFlow\Enums\EventType;
use QBitFlow\Models\PaymentCompleted;
use QBitFlow\Webhooks\WebhookRouter;

Route::post('/hooks/qbitflow', function (Request $request, WebhookRouter $router) {
	$result = $router
		->on(EventType::PAYMENT_COMPLETED, fn (PaymentCompleted $payment) => FulfilOrder::dispatch($payment->reference))
		->handle($request->getContent(), (string) $request->header('QBitFlow-Signature'));

	return response($result->responseBody(), $result->status)->header('Content-Type', 'application/json');
});
```

**PSR-7 / PSR-15 frameworks** (Slim, Mezzio, Symfony with its PSR-7 bridge): `handleRequest()`
takes a PSR-7 request and returns the PSR-7 response, and `psr15()` is the router as a
`Psr\Http\Server\RequestHandlerInterface`. Responses are built with the PSR-17 factory found
(Guzzle, Nyholm, Laminas, Slim…) or the one given to `withResponseFactory()`.

```php
// Slim 4 and Mezzio: a request handler is a route handler
$app->post('/webhooks/qbitflow', $router->psr15());

// Symfony: $this->router is a WebhookRouter service (services.yaml:
// QBitFlow\Webhooks\WebhookRouter: { arguments: ['%env(QBITFLOW_WEBHOOK_SECRET)%'] })
// with symfony/psr-http-message-bridge, controllers take and return PSR-7 messages:
#[Route('/webhooks/qbitflow', methods: ['POST'])]
public function qbitflow(ServerRequestInterface $request): ResponseInterface
{
	return $this->router->handleRequest($request);
}

// Symfony without the bridge: the raw body and the header are all it needs
#[Route('/webhooks/qbitflow', methods: ['POST'])]
public function qbitflow(Request $request): Response
{
	$result = $this->router->handle($request->getContent(), (string) $request->headers->get('QBitFlow-Signature'));

	return new Response($result->responseBody(), $result->status, ['Content-Type' => 'application/json']);
}
```

**Test your handlers** with a body signed like QBitFlow does:

```php
use QBitFlow\Webhooks\Webhook;

$body = '{"id":"evt_1","type":"payment.completed","version":"v2","createdAt":"2026-10-01T12:00:00Z","test":true,"data":{"uuid":"pay@1","reference":"order-1042"}}';
$result = $router->handle($body, Webhook::sign($body, 'whsec_test_secret')); // the router's secret
assert($result->status === 200);
```

### Checkout, success page, and scripts

Create the checkout as in the [quick start](#quick-start): QBitFlow fills in
`Placeholders::UUID` in the success URL, and you fulfil on `payment.completed` (above). The success
page shows where the payment stands with `$client->checkoutSessions->get($_GET['uuid'])`
([Status](#status)): a redirect proves nothing. A script, a test or a back-office job can wait
until the session is completed or expired:

<!-- docs:snippet wait-for-completion -->
```php
// For scripts and back-office jobs: fulfil orders on the payment.completed webhook.
$status = $client->checkoutSessions->waitForCompletion($sessionUuid, timeout: 600, interval: 3);
if ($status->status === \QBitFlow\Enums\CheckoutSessionStatusValue::COMPLETED) {
	echo "Paid, tx {$status->txHash}\n";
} else {
	echo "Not paid: {$status->status}\n"; // expired, or still pending when the timeout elapsed
}
```
<!-- /docs:snippet -->

`waitForCompletion()` polls `get()` (at most every second) and returns the final status, or
the last one seen when `timeout` (seconds) elapses; errors (a 404 included) are thrown. Webhooks
remain the way to fulfil orders.

### Access control

<!-- docs:snippet has-access -->
```php
$subscription = $client->subscriptions->get($subscriptionUuid);
if ($subscription->hasAccess()) { // now < currentPeriodEnd, whatever the status
	echo "Access granted\n";
} else {
	echo "No access: the paid period has ended\n";
}
```
<!-- /docs:snippet -->

`hasAccess()` is true when `currentPeriodEnd` is set and now is before it, whatever the status;
`$subscription->hasAccess(new DateTimeImmutable('+1 day'))` checks another time.

### Showing amounts

Token amounts are decimal strings, in the token's units (`amount`) and in its smallest unit
(`amountMinUnits`). Convert between the two exactly, never through floats:

<!-- docs:snippet amounts-display -->
```php
// Amounts in a token's smallest unit are decimal strings: convert them exactly, never through floats.
$display = \QBitFlow\Support\Amount::format('4990000', 6); // "4.99" (USDC has 6 decimals)
$minUnits = \QBitFlow\Support\Amount::parse('4.99', 6);     // "4990000"
echo "{$display} USDC = {$minUnits} min units\n";
```
<!-- /docs:snippet -->

A model's currency knows its decimals: `$payment->currency->formatAmount($payment->amountMinUnits)`
gives `"10"` for 10 USDC (`$payment->amount` already carries it).

### A yearly accounting export

The API exports at most 92 days at a time (both included); the range helpers split any range and
join the parts (see [Accounting export](#accounting-export)).

## Authentication and acting for a member

Every request sends your API key in `X-API-Key`. Keys are created in the QBitFlow dashboard; each
belongs to one **space** (your organization's, or one of its members') and one **mode** (test or
live). The constructor only checks the key's shape (non-blank, starting with `sk_`, printable
ASCII without spaces) and sends nothing: call `me()` to check it online
([quick start](#quick-start)). `$me->role` is `admin` for
an organization key, and a job that must run in test mode can refuse to start unless
`$me->space?->test` is true.

Keep the key in a secret store or an environment variable, never in code. API v2 issues keys
shaped `sk_<test|live>_<uuid>_<secret>`; keys in an earlier format (v1's `sk_<digits>_…`, and
`sk_<uuid>_<test|live>_…`) are refused by the API (401): create a new key in the dashboard. The
SDK never parses the key: it only checks the `sk_` prefix and the characters.

### `On-Behalf-Of`: acting in a member's space

A marketplace's **organization key** can act in any of its members' spaces: create their products
and checkouts, read their payments. Name the member by their **user UUID** (`Member::$userUuid`,
also in the `member.joined` webhook). A user who is not a member (someone on the organization's
team included), or a member of the other mode, answers 404. Acting for a member, the role is `user`: the admin methods (members,
invitations, `wallets->listForMember()`) answer 403.

<!-- docs:snippet client-on-behalf-of -->
```php
// An organization key acts in a member's space: every request sends On-Behalf-Of.
$seller = $client->onBehalfOf($memberUuid); // shares $client's transport and configuration
echo count($seller->products->list()->items), " products on the first page of the member's space\n";
```
<!-- /docs:snippet -->

For one request only, pass the request option: it wins over the client's.

```php
use QBitFlow\RequestOptions;

// One request only: the request option wins over the client's.
$page = $client->payments->list(null, new RequestOptions(onBehalfOf: $memberUuid));
echo count($page->items), " payments of the seller\n";

// '' forces the organization's own space for one request of the seller's client (the header is
// then omitted: it is never sent blank).
$own = $seller->products->list(null, new RequestOptions(onBehalfOf: ''));
echo count($own->items), " products of the organization\n";
```

`onBehalfOf` is also a constructor argument: `new QBitFlow(apiKey: $key, onBehalfOf: $memberUuid)`.
A value that is not a UUID (or the nil UUID) is a `ValidationException`, thrown at once by
`onBehalfOf()` or the constructor, and nothing is sent.

### Test and live mode

A key belongs to one mode, and every result it reads is that mode's: a test-mode key
(`sk_test_…`) sees only test-mode resources, paid on test networks with test tokens, and a
live-mode key (`sk_live_…`) only live ones. There is no per-request mode switch: use one client per
key (one for staging, one for production). `$client->me()->space?->test` tells which mode a key is
in. Test mode caps a checkout at `"5"` USD (fees included), takes `http` redirect URLs, and is the
only mode where `subscriptions->testBill()` works (a live subscription answers
`400 test_mode_only`); webhooks, events and objects carry `test` so a shared handler can tell them
apart.

## Checkout sessions

A checkout session is a hosted payment page. Create it, send your customer to its `link`, and act
on the webhook. The session's id (`pay@…` or `sub@…`) is also the id of the payment or the
subscription it creates once the customer's transaction is confirmed.

Name the product with **exactly one** of `productUuid`, `productReference`, or an inline product
(`productName` + `priceUsd`, `description` optional). The [quick start](#quick-start) names an inline
product; a catalog product is named by its reference (or `productUuid`):

<!-- docs:snippet checkout-create-payment-product -->
```php
$session = $client->checkoutSessions->createPayment(new \QBitFlow\Params\CreatePaymentSessionParams(
	productReference: 'tshirt-blue-m', // or productUuid: $product->uuid
	reference: 'order-1042',
	successUrl: 'https://shop.example.com/orders/success?uuid=' . \QBitFlow\Placeholders::UUID,
	cancelUrl: 'https://shop.example.com/orders/cancel',
));
echo "Pay at {$session->link}\n";
```
<!-- /docs:snippet -->

The other fields: `reference` (your order id, unique per space; like every reference, 1 to 100
of `A-Z a-z 0-9 . _ : @ -`, and never `.` or `..` alone), `customerUuid` or
`customerReference` (kept on the payment), `successUrl` / `cancelUrl`, `expiresInMinutes` (10 to
1440; null = the default) and `metadata` (your own data, copied onto the payment or subscription
and into its webhooks; at most 50 keys).

A subscription checkout takes the same fields plus its terms (`frequency`, `trialPeriod`, and
`minPeriods`, the periods the customer commits to), each optional over a subscription product's:

<!-- docs:snippet checkout-create-subscription -->
```php
$session = $client->checkoutSessions->createSubscription(new \QBitFlow\Params\CreateSubscriptionSessionParams(
	productName: 'T-shirt',
	description: 'Blue, size M',
	priceUsd: '4.99', // USD per period, a decimal string
	reference: 'order-1043',
	frequency: \QBitFlow\Models\Duration::months(1),
	trialPeriod: \QBitFlow\Models\Duration::days(7), // the first bill comes after the trial
	successUrl: 'https://shop.example.com/orders/success?uuid=' . \QBitFlow\Placeholders::UUID,
	cancelUrl: 'https://shop.example.com/orders/cancel',
));
echo "Subscribe at {$session->link}\n";
```
<!-- /docs:snippet -->

- **Redirect placeholders.** In `successUrl` and `cancelUrl`, QBitFlow replaces `{{UUID}}` with the
  session's id and `{{TRANSACTION_TYPE}}` with `payment` or `createSubscription`
  (`QBitFlow\Placeholders::UUID`, `::TRANSACTION_TYPE`; the SDK sends them as they are). In live mode both
  URLs must be `https`. A redirect proves nothing (anyone can open the URL): fulfil on the
  webhook, or on `get()`.
- **A member's redirects follow the organization's policy.** A member's own key may send
  `successUrl` and `cancelUrl` while the organization's `members.checkoutRedirects` policy allows
  it (it does by default, so a member's shop sends its customers back to its own site). With the
  policy off, either URL is a `403 policy_disabled` (a `PermissionDeniedException` with
  `$e->details['policy']` `members.checkoutRedirects` and `$e->details['field']` naming the URL,
  `successUrl` first) and nothing is created: the member's checkouts then go where the
  organization's checkout defaults say. An organization key may always send both, acting for the
  member (`onBehalfOf`) or not.
- **Errors to expect:** `409 merchant_not_ready` (`details['reason']`) when the space's wallets
  accept no currency, or for a subscription of a member whose funds you hold (`fundsHeld`);
  `409 unique_violation` when another payment or open session holds the
  `reference`; `400 validation_failed` above `"5"` USD in test mode, fees included
  (`details['max']` `"5"`), or for a live subscription at `"0.25"` or less (`details['min']`);
  `404` for an unknown product or customer; `410 merchant_closed` for a removed member's or a
  closed organization's space.

### Fees

A checkout, a payment's or a subscription's, can add amounts to its product's price: a tax,
shipping, a service fee, and QBitFlow's processing fee when you have the customer pay it. The
customer sees them line by line and pays them with the price (a subscription: with every bill):

<!-- docs:snippet checkout-create-payment-fees -->
```php
$session = $client->checkoutSessions->createPayment(new \QBitFlow\Params\CreatePaymentSessionParams(
	productName: 'T-shirt',
	description: 'Blue, size M',
	priceUsd: '3.99',
	reference: 'order-1044',
	successUrl: 'https://shop.example.com/orders/success?uuid=' . \QBitFlow\Placeholders::UUID,
	cancelUrl: 'https://shop.example.com/orders/cancel',
	fees: new \QBitFlow\Params\CheckoutFees(
		items: [
			new \QBitFlow\Params\FeeItem(label: 'Shipping', amountUsd: '0.75', description: 'Standard, 3 to 5 days'),
		],
		processingFee: true, // the customer pays QBitFlow's fee: you keep 3.99 + 0.75
	),
));
echo "Pay at {$session->link}\n"; // the customer pays the price, the shipping and the processing fee
```
<!-- /docs:snippet -->

- **Your lines** (`FeeItem`, at most 10, shown in that order): `label` (one line, 1 to 40
  characters), `description` (optional, at most 200) and `amountUsd`, above 0, at most 1,000,000,
  with at most 2 decimals, a decimal string (`'4.99'`) sent as typed, so no float rounds it. A bad
  line is a `ValidationException` on its path
  (`fees.items[0].amountUsd`), before any request.
- **The processing fee** (`processingFee: true`) is a last line (`Processing fee`) that QBitFlow
  computes on the price and your lines at your platform fee, grossed up (its fee is also taken on
  that line) and rounded up to the cent: you keep at least the price and your lines, as if no fee
  were taken ($100 + $20 VAT at 0.99 % → a $1.20 processing fee, $121.20 charged, $120.00 kept).
  The rate is the checkout's: QBitFlow's fee for your space when the checkout is created (a
  schedule by volume, unless QBitFlow set you a custom rate). Left `null`, the space's
  **`checkout.customerPaysProcessingFee`** setting decides (off by default; set by the
  organization, for its own space and for each member's: a member can't change it; it also
  applies to product links); `false` turns it off for this checkout.
- **What the customer pays** is the payment's `amountUsd` = the price + every line; the network
  fee comes on top, once they pick a currency. QBitFlow's fee (and a marketplace's organization
  fee) is taken on that amount. Test mode caps it, fees included, at `"5"` USD (`400` on `fees`).
- The lines are fixed when the session is created; `checkout.expired` carries them
  (`$event->data->fees`, `->amountUsd`), and the payment keeps them (`priceUsd`, `fees`: see
  [Payments](#payments-and-failures)).
- **Subscriptions** take the same `fees` (`CreateSubscriptionSessionParams`, the same rules),
  locked with the price: every bill, the first one and each next one, charges the price plus every
  line, whatever the product's price or your fee becomes. The `Subscription` keeps them as `fees`
  next to `priceUsd` (which stays the price); a bill's `amountUsd` and `subscription.upcomingBill`'s
  `amountUsd` include them, and the maximum and allowance the subscriber signs are sized on that
  amount. A trial's upgrade keeps the trial's lines. `checkout.customerPaysProcessingFee` applies
  to subscription checkouts too.

### Status

<!-- docs:snippet checkout-status -->
```php
$status = $client->checkoutSessions->get($sessionUuid); // pay@… or sub@…
echo match ($status->status) {
	\QBitFlow\Enums\CheckoutSessionStatusValue::COMPLETED => "Paid, tx {$status->txHash}\n",
	\QBitFlow\Enums\CheckoutSessionStatusValue::EXPIRED => "Expired unpaid: {$status->message}\n",
	\QBitFlow\Enums\CheckoutSessionStatusValue::WAITING_CONFIRMATION => "Sent, waiting for the network\n",
	default => $status->lastAttempt !== null // created, or a status this SDK does not know
		? "Last attempt failed ({$status->lastAttempt->code}): the customer may try again\n"
		: "Waiting for the customer\n",
};
```
<!-- /docs:snippet -->

| `status` | Meaning | Final |
|---|---|---|
| `created` | Waiting for the customer. With `lastAttempt` set, their last attempt failed (`lastAttempt->code` says why) | no |
| `waitingConfirmation` | A transaction was sent; waiting for the network. It may last: the transaction can still land | no |
| `completed` | Confirmed and recorded: the `Payment` or `Subscription` exists, with the session's id | yes |
| `expired` | Expired unpaid, at its `expiresAt` or by its merchant (`checkout.expired` was sent). Read after QBitFlow dropped the record, an expired session is a 404 | yes |

In a script, a test or a back-office job, `waitForCompletion($uuid, timeout: 600, interval: 3)`
polls until the session is `completed` or `expired` (see [the recipe](#checkout-success-page-and-scripts)).

**Never cancel an order on `lastAttempt`:** a failed attempt is not final, and the customer can pay
from the same checkout until it expires. Release what the order holds on `checkout.expired`.

### Expire

End a session early (an order cancelled on your side). It answers its status, `expired`, with
`expiresAt` the time it was expired, and `checkout.expired` follows. Once the customer paid or is paying it is a `409 tx_already_sent`.

<!-- docs:snippet checkout-expire -->
```php
$expired = $client->checkoutSessions->expire($sessionUuid);
echo "Now {$expired->status}\n"; // expired: the customer can no longer pay it
```
<!-- /docs:snippet -->

## Products and customers

Products are optional (a checkout can name an inline product) and give you a reusable catalog
with payment links.

<!-- docs:snippet products-create -->
```php
$product = $client->products->create(new \QBitFlow\Params\CreateProductParams(
	name: 'T-shirt',
	priceUsd: '4.99', // a decimal string, never a float
	description: 'Blue, size M',
	reference: 'tshirt-blue-m', // your own reference, unique per space
));
echo "Created {$product->uuid}, payment link {$product->paymentLink}\n";
```
<!-- /docs:snippet -->

<!-- docs:snippet products-list -->
```php
// iterate() walks every page (list() returns one); hidden products only with includeHidden.
foreach ($client->products->iterate() as $product) {
	echo "{$product->reference} {$product->name}: {$product->priceUsd} USD\n";
}
```
<!-- /docs:snippet -->

The reference is the product's uuid when null. A subscription product carries its terms, an
update (`PATCH`) changes only the arguments given (a new price applies to new checkouts and
subscribers only; `metadata` is merged), and the list (paginated, newest first: `list()` returns
one `Page`, `iterate()` walks them) can include the hidden products. A member's key gives a product terms (on `create()` or `update()`)
only while the organization's `members.subscriptions` policy allows it (`403 policy_disabled`
otherwise); with the policy off, the links of the member's subscription products open nothing:

```php
use QBitFlow\Models\Duration;
use QBitFlow\Params\CreateProductParams;
use QBitFlow\Params\ProductListParams;
use QBitFlow\Params\SubscriptionTermsParams;
use QBitFlow\Params\UpdateProductParams;

$plan = $client->products->create(new CreateProductParams(
	name: 'Pro plan',
	priceUsd: '4.99',
	subscription: new SubscriptionTermsParams(frequency: Duration::months(1)),
	metadata: ['sku' => 'PRO-M'],
));
$plan = $client->products->update($plan->uuid, new UpdateProductParams(
	priceUsd: '5.99',
	isActive: false,
	metadata: ['sku' => 'PRO-M2', 'legacy' => ''], // '' deletes a key, keys not named are kept
));
$page = $client->products->list(new ProductListParams(includeHidden: true, subscription: true));

// Your own reference: runs GET /products?reference= (a NotFoundException when none).
$plan = $client->products->getByReference('tshirt-blue-m');
```

`products->get()` and `delete()` (204) complete the set. Deleting a product does not stop its
subscriptions: cancel them with `subscriptions->cancel()` if the product is gone for good.

```php
use QBitFlow\Params\CreateCustomerParams;
use QBitFlow\Params\CustomerListParams;
use QBitFlow\Params\UpdateCustomerParams;

$customer = $client->customers->create(new CreateCustomerParams(
	firstName: 'Ada',
	email: 'ada@example.com',
	lastName: 'Lovelace',
	reference: 'crm-42',
	metadata: ['crm:id' => 'C-5521'],
));

// '' clears lastName, phoneNumber or address; null (the default) leaves them unchanged.
$customer = $client->customers->update($customer->uuid, new UpdateCustomerParams(phoneNumber: ''));

// By your reference (one customer), or by email (several customers may share one).
$customer = $client->customers->getByReference('crm-42');
$page = $client->customers->list(new CustomerListParams(email: 'ada@example.com'));
```

`customers->get()`, `list()` / `iterate()` (by `email` or `reference`) and `delete()` (204)
complete the set. An email is not unique: a checkout that names no customer creates one from the
email its payer typed, so several customers of a space may share an address; the `reference` is
unique per space (409 `unique_violation`). A checkout given `customerUuid` or `customerReference` asks the
customer nothing; without either, the checkout asks what your `checkout.customerDetails` setting
says (the full details by default).

## Payments and failures

A `Payment` exists once its transaction is confirmed, with its checkout session's `pay@…` id.

<!-- docs:snippet payments-list -->
```php
$page = $client->payments->list(new \QBitFlow\Params\PaymentListParams(
	createdAfter: new \DateTimeImmutable('-30 days'),
	limit: 20,
));
foreach ($page->items as $payment) {
	// Amounts are decimal strings: the token's (amount, with its currency) and USD (amountUsd).
	echo "{$payment->uuid} " . ($payment->reference ?? '-') . ": {$payment->amount} {$payment->currency->symbol} ({$payment->amountUsd} USD)\n";
}
if ($page->hasMore) {
	$cursor = $page->nextCursor; // pass it back, verbatim, as `cursor` for the next page
}
```
<!-- /docs:snippet -->

<!-- docs:snippet payments-get -->
```php
$payment = $client->payments->get($paymentUuid); // pay@…
echo "{$payment->uuid}: {$payment->amountUsd} USD, tx {$payment->txHash}\n";

// Runs GET /payments?reference= and returns its one payment (a NotFoundException when none).
$payment = $client->payments->getByReference('order-1042'); // your order id
echo "order-1042 was paid by {$payment->uuid}\n";
```
<!-- /docs:snippet -->

A payment also says how it settled (`$payment->settlement`: the fee terms, the `split` the
contracts paid, its `transaction`), where to see it (`explorerUrl`), your checkout's `metadata`
(read-only), and whether it can be refunded (`refundable`, `refund`).

**What the amounts mean.** `amountUsd` is what the customer paid in USD: the product's `priceUsd`
at the checkout plus the checkout's `fees` (`FeeLine`s: `type` `custom` or `processingFee`,
`label`, `description`, and `amountUsd`, such as `"0.75"`), so
`amountUsd = priceUsd + Σ fees[]->amountUsd`. `amount` is the same in the token paid (`"10"` for 10
USDC, with `amountMinUnits` and the `currency` object). It is what the contracts split
(`settlement->split`: `platform`, `organization`, `referral` and `merchant` add up to it exactly),
and QBitFlow's fee is taken on it; with a processing fee the merchant's share is at least the price
and the other lines. The network fee the customer paid on top is not in it (`paid`, `paidUsd` and
`settlement->split->networkFee` include it).

- **Filters** (`PaymentListParams`): `customerUuid`, `productUuid`, `createdAfter` / `createdBefore`
  (both excluded), `reference`, `refunded`, and, with an organization key acting for itself, `includeMembers`
  (every member's rows, each naming its `userUuid`) or `userUuid` (one member's). The last two
  exclude each other.
- **Reading a member's row** from the organization's space:
  `$client->payments->get($id, new ReadParams(includeMembers: true))`, or `onBehalfOf` the member.
- **Failures** are the failed attempts to pay a checkout or a bill. They never moved money and
  are not final: the customer can try again.

```php
use QBitFlow\Enums\FailureCategory;
use QBitFlow\Params\FailureListParams;

foreach ($client->failures->iterate(new FailureListParams(category: FailureCategory::INSUFFICIENT_BALANCE)) as $f) {
	printf("%s attempt %d: %s (%s USD)\n", $f->txUuid, $f->attempt, $f->code, $f->attemptedUsd);
}
```

### The transactions feed

`$client->transactions` lists the one-time payments and the subscriptions' bills together, newest
first, each row with its `type` (`payment` or `bill`, `QBitFlow\Enums\TransactionKind`) and the
fields of its resource. It takes the payment filters plus `type`, `subscriptionUuid` (one
subscription's bills) and `refunded`; open a row with `payments->get()` or
`subscriptions->getBill()`.

<!-- docs:snippet transactions-list -->
```php
// One feed of one-time payments and subscription bills, newest first; each row reads like its
// resource. `type` filters it to one kind (TransactionKind::PAYMENT or TransactionKind::BILL).
$feed = $client->transactions->list(new \QBitFlow\Params\TransactionListParams(limit: 20));
foreach ($feed->items as $transaction) {
	echo "{$transaction->type} {$transaction->uuid} {$transaction->amountUsd} USD\n";
}
```
<!-- /docs:snippet -->

## Subscriptions

A subscription exists once its customer signed its checkout: paid the first period, or started
the free trial. It keeps the checkout's `sub@…` id for life.

| `status` | Meaning |
|---|---|
| `trial` | In its free trial |
| `trialExpired` | The trial ended without the customer confirming it (`actionRequired` `confirmTrial`); cancelled 7 days later unless confirmed |
| `active` | Billed on its due dates |
| `pastDue` | A bill failed; retried for up to 7 days (`dunning` on reads), then cancelled |
| `paused` | Paused by its customer: not billed until resumed |
| `stopped` | Cancelled during its period: never billed again, `cancelled` at `nextBillingAt` |
| `cancelled` | Over (`cancellationReason` says why). Final |

**Access rule: grant access while `now < currentPeriodEnd`, whatever the status.** A `stopped` or
`paused` subscription has paid for its period; a `pastDue` one's period has ended.
`$subscription->hasAccess()` applies it now, `hasAccess(new DateTimeImmutable('2026-12-01'))` at
another time (see [Access control](#access-control)).

The subscription webhooks' data (`SubscriptionStatusChanged`, …) are subscriptions too:
`$data->hasAccess()` works there.

`actionRequired` says what the customer must do (`topUpAllowance`, `raiseMaximum`,
`confirmTrial`; `null`: nothing): point them to the subscription's `managementPageLink`.
`allowance` (null during a trial) says what the subscriber's wallet still lets QBitFlow bill
(`amount`, `remainingPeriods`, the signed `maxPerPeriod`); `priceUsd` plus `fees` is what each bill
charges; `lastBilledAt`, `nextBillingAt` and `cancellableAt` the dates.

<!-- docs:snippet subscriptions-get -->
```php
$subscription = $client->subscriptions->get($subscriptionUuid); // sub@…
printf("%s: %s, paid until %s\n", $subscription->uuid, $subscription->status, $subscription->currentPeriodEnd?->format('Y-m-d') ?? '-');
```
<!-- /docs:snippet -->

`get()` reads cancelled subscriptions too. List them by status, and walk a subscription's bills:

```php
use QBitFlow\Enums\SubscriptionStatus;
use QBitFlow\Params\SubscriptionListParams;

$page = $client->subscriptions->list(new SubscriptionListParams(status: SubscriptionStatus::PAST_DUE));
foreach ($page->items as $sub) {
	if ($sub->dunning !== null) {
		echo "{$sub->uuid}: {$sub->dunning->remainingAttempts} attempts left\n";
	}
}

// Every bill, newest first: the iterator fetches the pages lazily.
foreach ($client->subscriptions->iterateBills($subscriptionUuid) as $bill) {
	printf("%s: %s USD, paid until %s\n", $bill->uuid, $bill->amountUsd, $bill->periodEnd?->format('Y-m-d') ?? '?');
}
```

- `subscriptions->getByReference()` reads one by your checkout's `reference` (it runs
  `GET /subscriptions?reference=`; a `NotFoundException` when none); `getBill()` one bill
  (`sub-hist@…`, `GET /bills/:uuid`).
- Before its checkout completes, `subscriptions->get()` is a 404: read the checkout session instead.
- With an organization key acting for itself, `get()` and `getBill()` read a member's subscription
  or bill with `new \QBitFlow\Params\ReadParams(includeMembers: true)` (or act `onBehalfOf` the
  member).

### Metadata

A subscription's `metadata` is its checkout's, copied when it was created; `update()` merges keys
into it (the only thing a merchant edits on a subscription, cancelled ones included):

<!-- docs:snippet subscriptions-update -->
```php
// Metadata is merged into the subscription's: '' deletes a key, keys not named are kept. It is the
// only thing a merchant edits on a subscription; webhooks carry it.
$updated = $client->subscriptions->update($subscriptionUuid, new \QBitFlow\Params\UpdateSubscriptionParams(
	metadata: ['plan' => 'pro', 'coupon' => ''],
));
echo ($updated->metadata['plan'] ?? '-') . "\n";
```
<!-- /docs:snippet -->

### Cancel

`cancel()` cancels without the customer signing. By default it is immediate (`cancelled`, reason
`merchant`); `immediate: false` stops it now and cancels it at the end of the period paid for.

<!-- docs:snippet subscriptions-cancel -->
```php
$result = $client->subscriptions->cancel($subscriptionUuid, new \QBitFlow\Params\CancelSubscriptionParams(immediate: false));
if ($result->pending) {
	// HTTP 202: the on-chain cancellation is still confirming; subscription.statusChanged tells the end.
	echo "Cancellation confirming\n";
} else {
	echo "Now {$result->subscription->status}\n"; // stopped: cancelled at the end of the paid period
}
```
<!-- /docs:snippet -->

`cancel()` answers 200 when done and 202 (`pending`) while confirming on-chain. It is never retried
automatically. Errors worth knowing: 409 `subscription_already_stopped_or_inactive` (`immediate:
false` on a subscription already `stopped`), 409 `tx_already_sent` (a cancellation is running),
404 (already cancelled, or another space's).

### Test billing

In test mode a subscription is billed only when you ask, with live's statuses and webhooks:
`testBill()` returns where the test bill stands once its attempt settled (a `TestBill`: its
`billUuid`, `stage`, `outcome`, `failureCode`).

<!-- docs:snippet subscriptions-test-bill -->
```php
// Test mode only: runs the next billing now instead of on its due date.
$bill = $client->subscriptions->testBill($subscriptionUuid);
echo "Bill {$bill->billUuid}: {$bill->stage} " . ($bill->outcome ?? '-') . ' ' . ($bill->failureCode ?? '') . "\n"; // sub-hist@…
```
<!-- /docs:snippet -->

Before `nextBillingAt` it is a `409 payment_not_due`, and a live subscription a `400
test_mode_only`; a failed bill sets `$bill->failureCode`.

Walk the timeline once in test mode: a 5-minute frequency, pay from a test wallet, trigger the
bill, then empty the wallet and trigger it again to see `subscription.billingFailed` and
`pastDue`.

## Refunds

One list for every refund (`GET /refunds`, paginated), filtered by `status`: `pending` lists the
ones waiting for an answer.

<!-- docs:snippet refunds-list -->
```php
// The active ones: pending, waiting for an answer (no status: every status).
$page = $client->refunds->list(new \QBitFlow\Params\RefundListParams(
	status: [\QBitFlow\Enums\RefundStatus::PENDING],
));
foreach ($page->items as $refund) {
	echo "{$refund->uuid} {$refund->txUuid} ({$refund->initiatedBy}): {$refund->amount} {$refund->currency->symbol}, " . ($refund->reason ?? '-') . "\n";
}
```
<!-- /docs:snippet -->

From the organization's space the members' refunds are included by default:
`new RefundListParams(includeMembers: false)` leaves them out; `txUuid` keeps one transaction's,
`held` the held sellers' ones. Every status, page by page:

```php
use QBitFlow\Enums\RefundStatus;
use QBitFlow\Params\RefundListParams;

$answered = new RefundListParams(status: [RefundStatus::APPROVED, RefundStatus::REJECTED]);
foreach ($client->refunds->iterate($answered) as $r) {
	echo "{$r->uuid} {$r->status} {$r->explorerUrl}\n";
}
```

`refunds->get()` reads one refund by its `refund@…` id:

<!-- docs:snippet refunds-get -->
```php
$refund = $client->refunds->get($refundUuid); // refund@…
// pending (a customer's request), approved or rejected.
echo "{$refund->uuid} {$refund->status} {$refund->refundPercent}% {$refund->amount} {$refund->currency->symbol}\n";
```
<!-- /docs:snippet -->

Refunds are read only here: there is no create. A refund is **requested by the customer** (the
refund page; you approve or reject it in the dashboard) or **sent by you from the dashboard**,
signed from the wallet that was paid. Yours exists only once its transfer confirmed: it is recorded
`approved`, and `refund.approved` (with `initiatedBy` `merchant`) is the first and only event about
it. `refund.requested` is always a customer's request.

`refundPercent` is a percent string (`"100"`: all of it) of everything the customer paid (`paid`,
network fee included); `amount` / `amountMinUnits` / `amountUsd` is what it sends back, in the
token paid. There is one refund per transaction, whatever it became: `$payment->refundable` /
`notRefundableReason` say whether one would be accepted now.

A refund is `pending` (a customer's request waiting for your answer), `approved` or `rejected`
(both final). `initiatedBy` is `customer` or `merchant`.

## Marketplaces

A marketplace is an organization whose sellers are its **members**. You invite them, sell for
them with your organization key and `On-Behalf-Of`, take a commission on their payments, and may
hold their funds until you trust them. QBitFlow stays non-custodial: money goes from the customer's
wallet to the seller's (and your commission to yours) in one transaction.

**1. Invite the seller.** The SDK always invites members (`role: user`); the team is invited from
the dashboard.

<!-- docs:snippet members-invite -->
```php
$created = $client->invitations->create(new \QBitFlow\Params\CreateInvitationParams(
	email: 'seller@example.com',
	holdFunds: true,              // hold their payments until you trust them (always sent)
	organizationFeePercent: '10', // your commission on their payments: "0" to "50" %
	redirectUrl: 'https://shop.example.com/sellers/welcome',
));
echo "Invitation {$created->uuid}: {$created->link}\n"; // the link is also emailed
```
<!-- /docs:snippet -->

`holdFunds` is required; the fee is a percent string, `"0"` to `"50"`, at most 2 decimals. The
answer is the invitation itself with its `link` (a `CreatedInvitation`). `sendEmail: false`
creates the invitation without emailing it: share `link` in your own onboarding (signing up
through it still emails the person a confirmation). Inviting a member is a `409 already_joined`,
and more than 50 invitations an hour, emailed or not, a 429 (thrown at once: the wait is over the
SDK's 60 s retry cap).

**2. Wait for `member.joined`.** The seller exists once they accepted: store the event's
`userUuid` and match `invitationUuid` to your invitation. Never trust the redirect's
`?invitationUuid=` (anyone can open it).

```php
use QBitFlow\Events\MemberJoinedEvent;

if ($event instanceof MemberJoinedEvent) {
	echo "invitation {$event->data->invitationUuid} accepted by {$event->data->userUuid}\n";
}
```

**3. Sell for them.** The seller adds their receiving wallet in their QBitFlow dashboard (only
they can, not needed while you hold their funds). Then act in their space:

```php
use QBitFlow\Params\CreatePaymentSessionParams;
use QBitFlow\Params\AcceptedCurrenciesParams;

$currencies = $client->wallets->listAcceptedCurrencies(new AcceptedCurrenciesParams(userUuid: $memberUuid));
if ($currencies === []) {
	throw new RuntimeException('the seller cannot be paid yet: their checkouts would answer 409 merchant_not_ready');
}

$seller = $client->onBehalfOf($memberUuid);
$session = $seller->checkoutSessions->createPayment(new CreatePaymentSessionParams(
	productName: 'Handmade mug',
	priceUsd: '4.50',
	successUrl: 'https://market.example.com/orders/' . \QBitFlow\Placeholders::UUID,
)); // 403 policy_disabled when your policies don't let members do this
echo $session->link, "\n";
```

While you hold a seller's funds (`Member::$trustedAt` null), they sell **one-time payments only**:
a subscription checkout in their space answers `409 merchant_not_ready` with `details['reason']`
`fundsHeld` (`QBitFlow\Enums\MerchantNotReadyReason::FUNDS_HELD`) until you trust them
(`members->trust()`), and their subscription products' links open nothing.

**4. Hear of every sale.** An organization webhook endpoint receives every seller's events by
default; the event's `userUuid` names the seller. Use it as `onBehalfOf` for follow-up reads.

**5. Commission and held funds.** Each payment records your fee in `settlement->organizationFee`
and its share in `settlement->split->organization`. While you hold a seller's funds (`holdFunds`
true, `Member::$trustedAt` null), their payments go to your wallet and the net is owed to them
(`totalUsd`, and each line's `owed` in the token paid):

<!-- docs:snippet members-held-funds -->
```php
foreach ($client->members->listHeldFunds() as $owed) { // every member you owe, the longest held first
	echo "{$owed->userUuid}: {$owed->totalUsd} USD over {$owed->count} lines\n";
}

$held = $client->members->getHeldFunds($memberUuid);
printf("Owed to %s: %s USD over %d lines\n", $memberUuid, $held->totalUsd, count($held->ledgers));
```
<!-- /docs:snippet -->

The seller reads their side with their own key, or through `$client->onBehalfOf($memberUuid)`:

<!-- docs:snippet members-own-held-funds -->
```php
// With a member's key (or a client from $client->onBehalfOf($memberUuid)):
$held = $client->members->getOwnHeldFunds();
printf("Held for me: %s USD over %d lines\n", $held->totalUsd, count($held->ledgers));
```
<!-- /docs:snippet -->

Trust them once you are ready to pay them directly:

<!-- docs:snippet members-trust -->
```php
// Their new payments go to their own wallets from now on. What is already held stays held
// until you release it from the dashboard (heldFunds.released tells you).
$member = $client->members->trust($memberUuid);
echo 'Trusted since ' . ($member->trustedAt?->format(DATE_ATOM) ?? '?') . "\n";
```
<!-- /docs:snippet -->

Change the commission (a percent string; a checkout already created keeps its fee, a running
subscription's next bills take the new one):

<!-- docs:snippet members-update -->
```php
$member = $client->members->update($memberUuid, new \QBitFlow\Params\UpdateMemberParams(
	organizationFeePercent: '10', // a percent string ("0" to "50"); existing checkouts keep their fee
));
echo "Fee now {$member->organizationFeePercent} %\n";
```
<!-- /docs:snippet -->

**6. Manage members and invitations.**

<!-- docs:snippet members-list -->
```php
foreach ($client->members->iterate() as $member) {
	printf("%s %s %s: fee %s %%, %s\n", $member->userUuid, $member->firstName, $member->email,
		$member->organizationFeePercent, $member->trustedAt !== null ? 'trusted' : 'funds held');
}
```
<!-- /docs:snippet -->

<!-- docs:snippet members-get -->
```php
$member = $client->members->get($memberUuid); // the member's userUuid
echo "{$member->firstName} " . ($member->lastName ?? '') . " <{$member->email}>, joined {$member->joinedAt->format('Y-m-d')}\n";
foreach ($member->acceptedCurrencies as $currency) {
	echo "  accepts {$currency->code}\n"; // none: their checkouts answer 409 merchant_not_ready
}
```
<!-- /docs:snippet -->

<!-- docs:snippet members-wallets -->
```php
foreach ($client->wallets->listForMember($memberUuid) as $wallet) {
	echo "{$wallet->currency->chain} {$wallet->address}\n";
}
```
<!-- /docs:snippet -->

`members->remove()` (204) ends a seller's membership in the key's mode: their keys stop working and
their checkouts close. It is a `409 held_funds_pending` while you hold their live funds: release
them first.

<!-- docs:snippet members-remove -->
```php
try {
	$client->members->remove($memberUuid); // their keys stop working, their checkouts close
	echo "Removed\n";
} catch (\QBitFlow\Exceptions\ConflictException $e) {
	if ($e->apiCode !== 'held_funds_pending') {
		throw $e;
	}
	echo "You still hold their funds: release them in the dashboard first\n";
}
```
<!-- /docs:snippet -->

The pending invitations, and revoking one that hasn't been accepted (`InvitationListParams` also
filters by any other `status`):

An API key lists member invitations only: the team's are managed from the dashboard.

<!-- docs:snippet invitations-list -->
```php
$page = $client->invitations->list(new \QBitFlow\Params\InvitationListParams(status: \QBitFlow\Enums\InvitationStatus::PENDING));
foreach ($page->items as $invitation) {
	echo "{$invitation->uuid} {$invitation->email}, expires {$invitation->expiresAt->format('Y-m-d')}\n";
}
```
<!-- /docs:snippet -->

<!-- docs:snippet invitations-revoke -->
```php
$client->invitations->revoke($invitationUuid); // a pending invitation only (204)
echo "Revoked {$invitationUuid}: its link no longer works\n";
```
<!-- /docs:snippet -->

- `members->list()` returns one page (the snippet's `iterate()` walks them all);
  `invitations->iterate()` walks every invitation. `invitations->revoke()` answers 204 (void).
- A member's `acceptedCurrencies` are the currencies a checkout of theirs can be paid in now; none
  means their checkouts answer `409 merchant_not_ready`.
- A seller whose account already belongs to another organization accepts like anyone else: they
  keep their other memberships, and `member.joined` comes as for any member. Test-mode sellers
  are real accounts that accept from a real inbox.

## Wallets

Wallets are added and removed in the dashboard, by their owner only. The SDK reads them.

```php
use QBitFlow\Params\WalletListParams;

foreach ($client->wallets->list(new WalletListParams(withBalances: true)) as $wallet) {
	echo "{$wallet->currency->chain} {$wallet->address}\n";
	foreach ($wallet->tokenWallets as $tokenWallet) {
		if ($tokenWallet->balance !== null) { // read on-chain; null when the chain couldn't be read
			$balance = $tokenWallet->balance;
			echo "  {$tokenWallet->currency->symbol}: {$balance->amount} (" . ($balance->amountUsd ?? '?') . " USD)\n";
		}
	}
}
```

The wallet lists are bounded: they return a plain array. `wallets->listForMember($userUuid)` reads
a member's wallets (organization key), and `wallets->listAcceptedCurrencies()` the currencies a
space's checkouts accept now: none means its checkouts answer `409 merchant_not_ready`.

## Accounting export

Every payment, bill, refund and fee between two dates (`YYYY-MM-DD`, both included), as JSON rows
or as CSV text:

<!-- docs:snippet accounting-export -->
```php
// The API exports at most 92 days at a time (both included): the range helpers split any range
// into windows, request them in order and join the results (the CSV header once).
$events = $client->accounting->exportJsonRange('2026-01-01', '2026-12-31');
foreach ($events as $event) {
	echo "{$event->type} {$event->txUuid} {$event->txTimeUtc->format('Y-m-d')} {$event->grossAmountUsd} USD {$event->productName}\n";
}

$csv = $client->accounting->exportCsvRange('2026-01-01', '2026-12-31'); // one header line, then the rows
```
<!-- /docs:snippet -->

The SDK checks the dates and `from <= to` before sending. **The API allows at most 92 days per
export, both included** and answers `400 validation_failed` on `to` beyond (`details['max']` 92):
`exportJsonRange()` and `exportCsvRange()` take any range, split it into windows of at most 92
days (`[from, from+91d]`, the next starting the day after, on calendar dates), request them in
order and join them (the CSV header once): 2026-01-01 to 2026-12-31 is 4 requests (`[01-01,
04-02] [04-03, 07-03] [07-04, 10-03] [10-04, 12-31]`). `exportJson()` and `exportCsv()` make one
request for a window of at most 92 days. Rows are typed `payment`, `bill`, `refund`,
`organizationFee` or `referralFee`, and a value a row doesn't have (a fee that doesn't apply) is
`null`, never `"0"`.

- **Dates** are the transactions' (the day their block was mined, in UTC: `txTimeUtc`), so
  consecutive exports never miss nor repeat an event. Rows are ordered by `txTimeUtc`, `type`,
  then `txUuid` (`pay@…`, `sub-hist@…`, `refund@…`; a refund's `relatedTxUuid` and
  `relatedReference` name what it refunds).
- **Amounts** are in the currency's units (`"7.5"` USDC, not `7500000`), as decimal strings, next
  to `currencyDecimals`. `currency` names what was paid as markets do (`USDC`, or the chain's
  native currency: `ETH`, `SOL`), and `currencyPriceUsd` is the USD price the amounts were
  converted at; the USD amounts (`grossAmountUsd`, `netAmountUsd`…) are decimal strings too, and
  each amount has its `…MinUnits` twin. `grossAmount = platformFee + organizationFee + netAmount`
  exactly; a refund's net is the refunded amount, negative.
- **Network fees are never part of the net.** `networkFeesPaidBy` (`Enums\NetworkFeesPayer`) says
  whose wallet paid them: `payer` (a payment or a bill: on top of the amount, in its currency),
  `merchant` (a refund your space sent) or `organization` (a refund of a payment the organization
  held for its member). Book `networkFees` (in `networkFeesCurrency`, the chain's native currency
  on a refund) apart for `merchant` and `organization`.
- **CSV columns** follow `AccountingEvent`'s property order (what happened, the USD amounts, the
  same amounts in the currency, the network fees, the rates, the on-chain proof, the ids): read
  them by header name, not position. Amounts are plain numbers, an absent value an empty cell, and
  a cell starting with `= + - @`, a tab or a CR gets a leading `'`.

## Webhooks

QBitFlow posts an **event** to your endpoints when something happens: a payment confirmed, a
subscription billed, a member joined.

### 1. Create an endpoint and store its secret

<!-- docs:snippet webhook-endpoint-create -->
```php
$endpoint = $client->webhooks->endpoints->create(new \QBitFlow\Params\CreateWebhookEndpointParams(
	url: 'https://shop.example.com/webhooks/qbitflow',
	events: [ // null: every type, including the ones added later
		\QBitFlow\Enums\EventType::PAYMENT_COMPLETED,
		\QBitFlow\Enums\EventType::CHECKOUT_EXPIRED,
		\QBitFlow\Enums\EventType::SUBSCRIPTION_STATUS_CHANGED,
	],
));
// The whsec_… secret is returned only this once: put it in your secret store now.
echo "Endpoint {$endpoint->uuid}: QBITFLOW_WEBHOOK_SECRET={$endpoint->secret}\n";
```
<!-- /docs:snippet -->

`description` adds a note for the dashboard; `events` takes at most 20 types, never
`webhook.test`. Up to 10 endpoints per space and mode; live endpoints need `https` and a public
host. An organization endpoint also receives its members' events unless created with
`includeMembers: false`. `endpoints->list()` (a `Page`; `iterate()` walks them), `get()`,
`update()` (`PATCH`: `enabled: false` pauses it, `true` enables it again, also after it was
disabled for failing; `description: ''` clears the note; `events: []` receives every type) and
`delete()` (204) manage them. An endpoint's secret is shown and rotated in the dashboard only.

### 2. Handle the deliveries with a router

A `QBitFlow\Webhooks\WebhookRouter` does the whole job: it verifies the `QBitFlow-Signature`
header over the **raw body**, parses the event, runs the handlers registered for its type with
the typed data, and tells you what to answer. No client (and no API key) is needed;
`$client->webhooks->router($secret)` builds the same router. [The webhook endpoint
recipe](#the-webhook-endpoint) shows one in plain PHP, Laravel, Slim, Mezzio and Symfony. Besides
`on()` for each type (`checkout.expired`'s data is a `PaymentSessionData` or a
`SubscriptionSessionData`), a router takes catch-all handlers:

```php
use QBitFlow\Enums\EventType;
use QBitFlow\Events\Event;
use QBitFlow\Models\PaymentSessionData;
use QBitFlow\Models\SubscriptionSessionData;

$router
	->on(EventType::CHECKOUT_EXPIRED, function (PaymentSessionData|SubscriptionSessionData $session): void {
		release($session->reference); // your code
	})
	->onUnknown(fn (Event $event) => error_log("a type this SDK does not know: {$event->type}"))
	->onAny(fn (Event $event) => storeForAudit($event->id, $event->type));

$result = $router->handleGlobals(); // plain PHP; or handleRequest($psr7Request), psr15(), handle($rawBody, $header)
```

| Delivery | `WebhookResult::$status` | Body the adapters send |
|---|---|---|
| handled, or no handler for its type (unknown types included) | 200 | `{"received":true}` |
| bad, missing or stale signature (no handler runs) | 400 | `{"error":"invalid signature"}` |
| not a v2 event: not JSON, another payload version, data not fitting its type | 400 | `{"error":"invalid event"}` |
| a handler threw: the handlers after it are skipped, QBitFlow retries | 500 | `{"error":"internal error"}` |
| adapters: not a POST / a body over 1 MiB / an unreadable body | 405 (`Allow: POST`) / 413 / 400 | `method not allowed` / `body too large` / `cannot read the body` |

Per event the handlers run in this order: those of its type (several per type, in registration
order), then `onUnknown()` (types this SDK does not know only), then `onAny()`. `on()` refuses a
type this SDK does not know (a `ValidationException`: catch typos early), and so does an empty
secret. `onError()` sees every result carrying an error (the 400s, with a null event when parsing
failed, and the 500s); the bodies never echo the secret nor a stack trace. `$router->dispatch($event)`
runs the handlers for an event you verified yourself. The [recipes](#the-webhook-endpoint) show
the router in Laravel, Slim, Mezzio and Symfony, and `Webhook::sign()` for your tests.

#### The lower level: verify and parse yourself

<!-- docs:snippet webhook-verify -->
```php
$rawBody = (string) file_get_contents('php://input'); // the bytes as received: never re-serialize
try {
	$event = \QBitFlow\Webhooks\Webhook::constructEvent(
		$rawBody,
		$_SERVER['HTTP_QBITFLOW_SIGNATURE'] ?? '',
		(string) getenv('QBITFLOW_WEBHOOK_SECRET'),
	);
} catch (\QBitFlow\Exceptions\WebhookSignatureException $e) {
	http_response_code(400); // $e->reason: noMatchingSignature, timestampOutsideTolerance, …
	exit;
} catch (\QBitFlow\Exceptions\ValidationException) {
	http_response_code(400); // not a v2 event (unsupported payload version), or not JSON
	exit;
}

if ($event instanceof \QBitFlow\Events\PaymentCompletedEvent) {
	// Deliveries are at least once: skip an $event->id you already processed.
	error_log("fulfil order {$event->data->reference} ({$event->data->uuid})");
}
http_response_code(200); // to every type, the ignored ones too
```
<!-- /docs:snippet -->

`Webhook::verify()` checks a signature without parsing (it throws, or returns nothing),
`Webhook::parseEvent()` parses a body already verified, and `Webhook::verifyRequest($psr7Request,
$secret)` reads a PSR-7 request's body (at most 1 MiB) and verifies it. The client exposes the
same functions as `$client->webhooks->verify()`, `constructEvent()` and `parseEvent()`.

- **At least once.** The same event can arrive more than once: **deduplicate on `$event->id`**
  (also in the `QBitFlow-Event-Id` header, `Webhook::EVENT_ID_HEADER`) and make the handler
  idempotent. `QBitFlow-Event-Type` carries the type.
- **Answer 2xx fast**, within 30 seconds, **including to the types you ignore**: anything else is
  retried (for 3 days in live mode), and an endpoint failing for 3 days is disabled. Store the
  event, answer, then process it in the background (a queue).
- **Secret rotation** needs nothing on your side: for 24 hours after a rotation the header carries
  two `v1=` signatures, the new secret's and the previous one's, and either secret verifies. Switch
  your secret within the day.
- **Timestamps** more than 5 minutes from your clock are refused (replays): the `tolerance`
  argument (seconds) changes it, `now` (a closure) sets the clock in tests.
- **Members' events** carry the member in `$event->userUuid`: read their resources with
  `$client->onBehalfOf($event->userUuid)`.
- **No fixed source IPs**: verify the signature, never allow-list addresses.
- **v2 only.** Every endpoint receives the API v2 event envelope; the SDK checks it, and a body in
  another payload version is a `ValidationException` ("unsupported payload version").
- Not holding the secret? `$client->webhooks->verifyRemote($endpointUuid, $rawBody, $header)` has
  the API check it (a `WebhookSignatureException` with reason `invalidSignature` when it does not
  match), then `Webhook::parseEvent()` parses the body.

| `type` | Event class | `$event->data` |
|---|---|---|
| `payment.completed` | `PaymentCompletedEvent` | `PaymentCompleted`: the `Payment` + `managementPageLink` |
| `subscription.created` | `SubscriptionCreatedEvent` | the `Subscription` (`active` or `trial`), its `managementPageLink` included |
| `subscription.billed` | `SubscriptionBilledEvent` | `SubscriptionBilled`: `bill` (the `Bill` paid, until `bill->periodEnd`) and `subscription` (as the bill leaves it) |
| `subscription.statusChanged` | `SubscriptionStatusChangedEvent` | `SubscriptionStatusChanged`: the `Subscription` + `previousStatus` |
| `subscription.actionRequiredChanged` | `SubscriptionActionRequiredChangedEvent` | `SubscriptionActionRequiredChanged`: the `Subscription` + `previousActionRequired` |
| `subscription.billingFailed` | `SubscriptionBillingFailedEvent` | `SubscriptionBillingFailed`: the `Subscription` + `reason`, `billUuid`, `amountUsd` (a string), `attempt`, `remainingAttempts`, `nextAttemptAt` |
| `subscription.upcomingBill` | `SubscriptionUpcomingBillEvent` | `SubscriptionUpcomingBill`: the `Subscription` + `billingAt`, `amountUsd` (a string), `trialEnding`, `balanceSufficient`, `allowanceSufficient` |
| `refund.requested` / `.approved` / `.rejected` | `RefundRequestedEvent` / `RefundApprovedEvent` / `RefundRejectedEvent` | the `Refund` (`refund.approved` and `refund.rejected` were `refund.completed` and `refund.denied`) |
| `member.joined` | `MemberJoinedEvent` | `MemberJoined`: the `Member` + `invitationUuid` |
| `member.removed` | `MemberRemovedEvent` | the `Member` |
| `heldFunds.released` | `HeldFundsReleasedEvent` | `HeldFundsReleased`: the transfer paid to the member + the `ledgers` it settled |
| `checkout.expired` | `CheckoutExpiredEvent` | `PaymentSessionData`, or `SubscriptionSessionData` for a subscription session |
| `webhook.test` | `WebhookTestEvent` | `WebhookTest`: `endpointUuid`, `message` (the dashboard's test) |
| any other | `UnknownEvent` | the raw `data` array |

Every event also has `id`, `type`, `createdAt`, `test`, `userUuid` and `rawData`;
`$event->decodeData(Subscription::fromArray(...))` reads the data as another model. Webhook data
never carries what only API reads return (`customer`, `refund`/`refundable`, `dunning`,
`approval`, a refund's `customerUuid` and `productName`). In Laravel, the [route, middleware and events](#laravel) do all of this.

### The event log

Every event of the space, newest first, each exactly as it was delivered (kept at least 90 days;
the cursor is opaque):

<!-- docs:snippet events-list -->
```php
$page = $client->webhooks->events->list(new \QBitFlow\Params\EventListParams(
	type: \QBitFlow\Enums\EventType::PAYMENT_COMPLETED,
	limit: 20,
));
foreach ($page->items as $event) { // newest first, each exactly as delivered
	echo "{$event->id} {$event->type} {$event->createdAt->format(DATE_ATOM)}\n";
}
if ($page->hasMore) {
	echo "More: pass {$page->nextCursor} as cursor\n"; // opaque, never an event id
}
```
<!-- /docs:snippet -->

`iterate()` walks every page, and `get()` reads an event with its deliveries:

```php
use QBitFlow\Enums\EventType;
use QBitFlow\Params\EventListParams;

foreach ($client->webhooks->events->iterate(new EventListParams(type: EventType::PAYMENT_COMPLETED)) as $event) {
	$detail = $client->webhooks->events->get($event->id);
	foreach ($detail->deliveries as $delivery) {
		echo "{$event->id} {$delivery->url} ", $delivery->delivered ? 'delivered' : 'failing', ' ', count($delivery->attempts), "\n";
	}
	break; // the first one is enough here: breaking stops the fetching
}
```

Each attempt (`DeliveryAttempt`) has `delivered` (the endpoint answered a 2xx), `statusCode` (null
when the endpoint didn't answer) and `error`. An attempt with `skipped: true` was never posted: the
endpoint is a member's and the organization's `members.webhooks` policy was off for them (no
`statusCode`; not retried, and not counted against the endpoint's health).

## Currencies

<!-- docs:snippet currencies-list -->
```php
// One bounded list: the live currencies; test: the test networks'; main: the chains' native coins only.
foreach ($client->currencies->list() as $currency) {
	echo "{$currency->id}: {$currency->code} ({$currency->name} on {$currency->chain}), {$currency->decimals} decimals\n";
}
```
<!-- /docs:snippet -->

`list()` returns the currency catalog (a plain array): `new CurrencyListParams(test: true)` lists
the test networks' currencies (whatever the key's mode), `main: true` only the chains' native coins
(ETH, BASE, SOL). `get($id)` reads one by id, e.g. a token's `mainCurrencyId`. A `Currency` has its
`code` (`BASE:USDC`, `ETH`), `chain`, `symbol`, `decimals` and, for a token, its `address`. These
routes are public and **limited to 60 requests a minute per IP: cache the list** at start-up
instead of reading it per request. Payments, bills, subscriptions and wallets already carry their
`currency` object.

## Pagination and iterators

Paginated lists return a `QBitFlow\Page`: `items`, `hasMore`, and `nextCursor` (null when
`hasMore` is false, else the value to pass back as the params' `cursor`, verbatim: it is opaque,
never build or parse it). A full last page answers `hasMore` false: there is never an extra empty
page.

<!-- docs:snippet customers-list -->
```php
$page = $client->customers->list(new \QBitFlow\Params\CustomerListParams(limit: 20));
foreach ($page->items as $customer) {
	echo "{$customer->uuid} {$customer->email}\n";
}
if ($page->hasMore) {
	$cursor = $page->nextCursor; // pass it back, verbatim, as `cursor` for the next page
}
```
<!-- /docs:snippet -->

Walking the pages by hand:

```php
use QBitFlow\Params\CustomerListParams;

$params = new CustomerListParams(limit: 100);
do {
	$page = $client->customers->list($params);
	foreach ($page->items as $customer) {
		echo $customer->email, "\n";
	}
	$params = $params->withCursor($page->nextCursor);
} while ($page->hasMore);
```

Each paginated list has an `iterate*()` twin returning a `Generator`: it fetches one page at a
time, only as you consume it, keeps your filters and page size, stops after the page whose
`hasMore` is false (and, defensively, on a page claiming more without a cursor, a cursor that did
not move, or an empty page), and stops when you `break`. An error is thrown from the `foreach`,
after the items before it.

<!-- docs:snippet pagination-iterate -->
```php
// The next page is fetched when the loop needs it; it stops after the page whose hasMore is false.
$count = 0;
foreach ($client->payments->iterate(new \QBitFlow\Params\PaymentListParams(limit: 50)) as $payment) {
	$count++;
	echo "{$payment->uuid} {$payment->amountUsd} USD\n";
}
echo "{$count} payments\n";
```
<!-- /docs:snippet -->

| Method | Iterator | Page size: default / max |
|---|---|---|
| `products->list()`, `customers->list()` | `iterate()` | 20 / 100 |
| `payments->list()`, `transactions->list()`, `failures->list()` | `iterate()` | 20 / 100 |
| `subscriptions->list()`, `subscriptions->listBills()` | `iterate()`, `iterateBills()` | 20 / 100 |
| `refunds->list()` | `iterate()` | 20 / 100 |
| `members->list()`, `invitations->list()` | `iterate()` | 20 / 100 |
| `webhooks->endpoints->list()`, `webhooks->events->list()` | `iterate()` | 20 / 100 |

The bounded lists answer one page and return a plain array of its items: `currencies->list()`,
`wallets->list()`, `wallets->listForMember()`, `wallets->listAcceptedCurrencies()`,
`members->listHeldFunds()` and `accounting->exportJson()` (a page that ever claims more is
followed, so nothing is cut short). `getByReference()` (products, customers, payments,
subscriptions) runs its list's `?reference=` filter and returns the one item with exactly that
reference, else a `NotFoundException` (status 404, code `not_found`).

## Errors

Every exception the SDK throws extends `QBitFlow\Exceptions\QBitFlowException` (and implements
`QBitFlow\Exceptions\ExceptionInterface`); every error below extends `ApiException`:

| Exception | When | Codes and fields worth knowing |
|---|---|---|
| `ValidationException` | 400 `validation_failed`, or input the SDK refused **before sending** (`status` 0) | `fieldErrors`: each failing input by its wire name, dotted when nested (`frequency.unit`) |
| `BadRequestException` | any other 400 | `foreign_key_violation` (`details['field']`), `test_mode_only`, `sender_is_receiver` |
| `AuthenticationException` | 401 | a missing, unknown, expired or revoked key (a removed member's keys too) |
| `PermissionDeniedException` | 403 | `forbidden` (the key's role or a bad `On-Behalf-Of`; no details), `policy_disabled` (a member's key while the organization's member policy is off: `details['policy']`, and `details['field']` when the policy governs a request field, e.g. `successUrl` / `cancelUrl` under `members.checkoutRedirects`) |
| `NotFoundException` | 404 | unknown, deleted, or outside the request's space |
| `ConflictException` | 409 | `unique_violation` (`details['field']`), `tx_already_sent`, `merchant_not_ready` (`details['reason']`), `held_funds_pending`, `already_joined`, `payment_not_due`, `idempotency_key_in_use`, `conflict` |
| `GoneException` | 410 | `merchant_closed`: a removed member's or a closed organization's space |
| `IdempotencyException` | 422 `idempotency_key_reused` | an `Idempotency-Key` reused for another request: a bug, never retried |
| `RateLimitException` | 429 | `retryAfter` (seconds), `limit`, `periodSeconds` |
| `ServerException` | 5xx (503 `network_unavailable`, 504 `timeout`), an unexpected 3xx (redirects are never followed), a 2xx whose body is not the expected JSON | |
| `NetworkException` | no response: DNS, connection, TLS, timeout | `getPrevious()`: the PSR-18 client's exception |
| `WebhookSignatureException` | a webhook signature refused, locally or by `verifyRemote()` | `reason` |
| `ApiException` | any other HTTP error | 413 `request_too_large`; 405 `method_not_allowed` (a route called with a method it doesn't take, never sent by the SDK; the `Allow` header lists the methods) |

Each carries `status` (`getStatus()`; `0` without a response), **`apiCode`** (`getApiCode()`:
branch on it, never on the message), `errorMessage`, `details` (an array, never null),
`requestId`, `fieldErrors` (`FieldError` with `field` and `message`) and `rawBody`. PHP's
`getCode()` is an integer, so it returns the HTTP status; the API's string code is `apiCode`.
`getMessage()` reads `"<message> (status <status>, code <code>, request <requestId>)"`, followed by
`"; <field>: <message>"` for each field error.

<!-- docs:snippet errors-handling -->
```php
try {
	$payment = $client->payments->getByReference('order-1042');
	echo "order-1042 was paid by {$payment->uuid}\n";
} catch (\QBitFlow\Exceptions\ValidationException $e) {
	foreach ($e->fieldErrors as $fieldError) { // refused before sending (status 0), or a 400
		echo "{$fieldError->field}: {$fieldError->message}\n";
	}
} catch (\QBitFlow\Exceptions\NotFoundException) {
	echo "No payment for order-1042 yet\n";
} catch (\QBitFlow\Exceptions\ApiException $e) {
	// Branch on apiCode, never on the message; quote the request id to support.
	printf("QBitFlow error %d %s (request %s), retryable: %s\n",
		$e->status, $e->apiCode, $e->requestId, $e->isRetryable() ? 'yes' : 'no');
}
```
<!-- /docs:snippet -->

Catch the other kinds the same way, before `ApiException`: a `ConflictException`'s
`$e->details['field']` names the field a `unique_violation` is about, and a `RateLimitException`
says when to retry (`$e->retryAfter`, seconds).

**Client-side validation.** Each params class has a `validate()` method, run before every
request: names and texts (lengths in characters, no markup characters), references
(`A-Z a-z 0-9 . _ : @ -`, 1 to 100, never `.` or `..` alone; the reference filters and the
`getByReference()` argument too), emails, phone numbers,
absolute `http(s)` URLs, USD amounts (decimal strings: above 0, at most 1,000,000, at most 2
decimals, compared exactly), percents (decimal strings, at most 2 decimals), metadata (at most 50
keys of 1 to 40 of `A-Z a-z 0-9 _ - . :`, values of at most 500 characters without control or
bidirectional characters), durations (a frequency between 1 unit and 1 year), UUIDs and
transaction ids, cursors (at most 200 characters), dates, the checkout's product choice, enum list
filters, and exclusive filters. A failure is a `ValidationException` with `status` 0 and nothing
is sent. What depends on the key's mode or on stored data is left to the API: the `"5"` USD
test-mode cap, `https`-only live URLs, the live subscription price minimum (above `"0.25"`), the
frequency minimum (1 hour live, 5 minutes test), the 92-day export window, the merged metadata key
count, uniqueness.

## Retries and idempotency

| | |
|---|---|
| Retried methods | every read (GET), and the 6 creates: `checkoutSessions->createPayment()`, `checkoutSessions->createSubscription()`, `products->create()`, `customers->create()`, `webhooks->endpoints->create()`, `invitations->create()` |
| Never retried | every other write: updates (`subscriptions->update()` included), deletes, `checkoutSessions->expire()`, `subscriptions->cancel()`, `subscriptions->testBill()`, `members->trust()`, `members->remove()`, `invitations->revoke()`, `webhooks->verifyRemote()` |
| Retried on | a network error or timeout, a 5xx, a 429, a 409 `idempotency_key_in_use` |
| Not retried on | any other 4xx (422 `idempotency_key_reused` included), a 3xx, an unusable response |
| Attempts | 3 retries by default (`maxRetries`; `0` disables them) |
| Back-off | 1 s, 2 s, 4 s…; a 429 waits for its `Retry-After` if longer. A wait above 60 s is not made: the `RateLimitException` is thrown at once |

`$e->isRetryable()` says whether an exception is of a transient kind. `timeout` bounds each
attempt; a retried call may take longer in total.

**Idempotency keys.** Each call of a create sends a fresh `Idempotency-Key` (a UUID v4) and reuses it
on every retry of that call: a create retried after a timeout answers the first result instead of
opening a second checkout. To retry **across processes** (a queue re-running a job after a crash),
pass your own stable key:

<!-- docs:snippet retries-idempotency -->
```php
// Reads and creates are retried on network errors, 5xx and 429 (3 retries by default).
$client = \QBitFlow\QBitFlow::fromEnv(maxRetries: 5);

$orderId = 'order-1042';
$session = $client->checkoutSessions->createPayment(
	new \QBitFlow\Params\CreatePaymentSessionParams(
		productName: 'T-shirt',
		description: 'Blue, size M',
		priceUsd: '4.99',
		reference: $orderId,
		successUrl: 'https://shop.example.com/orders/success?uuid=' . \QBitFlow\Placeholders::UUID,
		cancelUrl: 'https://shop.example.com/orders/cancel',
	),
	// The same key returns the same session, even from another process after a crash.
	new \QBitFlow\RequestOptions(idempotencyKey: 'checkout-' . $orderId),
);
echo "Pay at {$session->link}\n";
```
<!-- /docs:snippet -->

The same key with other params is an `IdempotencyException` (422 `idempotency_key_reused`).
`RequestOptions(requestId: 'job-7781')` also sends `X-Request-Id`, echoed in errors. A key is 1 to 255 printable ASCII characters without spaces, and only successful answers are kept
(24 hours): after a 4xx, the same key runs the request again. A `409 idempotency_key_in_use` (the
first request still running) is retried automatically. Other methods ignore the option. Retry
the other writes yourself only after reading the resource's state (a 504 may have done the work).

## Configuration

```php
use GuzzleHttp\Client as GuzzleClient;
use QBitFlow\QBitFlow;

$client = new QBitFlow(
	apiKey: getenv('QBITFLOW_API_KEY'),
	timeout: 10.0,  // seconds per attempt
	maxRetries: 5,
	httpClient: new GuzzleClient(['timeout' => 10, 'http_errors' => false, 'allow_redirects' => false]), // proxies, middleware…
);
```

| Constructor argument | Default | |
|---|---|---|
| `apiKey` | required | non-blank, starting with `sk_`, printable ASCII without spaces |
| `baseUrl` | `https://api.qbitflow.app/v2` (`QBitFlow::DEFAULT_BASE_URL`) | an absolute `http(s)` URL without credentials, query or fragment; a trailing `/` is stripped |
| `timeout` | 30.0 (`DEFAULT_TIMEOUT`) | seconds per attempt, positive: the whole attempt, through the last byte of the answer; applied to the HTTP client the SDK builds (set it on your own client) |
| `maxRetries` | 3 (`DEFAULT_MAX_RETRIES`) | `0` disables retries; negative is refused |
| `onBehalfOf` | none | every request acts in that member's space |
| `httpClient`, `requestFactory`, `streamFactory` | auto-detected (Guzzle, Symfony, Nyholm, …) | your PSR-18 client and PSR-17 factories; the client must not follow redirects (a redirect to another host would carry your `X-API-Key` there: Guzzle 7's PSR-18 calls never follow one, a Symfony client needs `max_redirects: 0`) and should not throw on HTTP errors |

| `RequestOptions` (every method, last argument) | |
|---|---|
| `onBehalfOf` | acts in that member's space for this call; `''` forces the organization's (the header is omitted, never sent blank) |
| `idempotencyKey` | the 6 creates only: your own `Idempotency-Key` |
| `requestId` | sends `X-Request-Id` (1 to 128 of `A-Z a-z 0-9 - _ . :`) |

| Webhook arguments (`new WebhookRouter()`, `Webhook::verify()`, `verifyRequest()`, `constructEvent()`) | Default |
|---|---|
| `tolerance` (seconds; 0 or less keeps the default) | 300 (`Webhook::DEFAULT_TOLERANCE`) |
| `now` (`Closure(): int\|DateTimeInterface`; not on the router) | `time()` |

`QBitFlow::fromEnv()` builds the client from `QBITFLOW_API_KEY`, `QBITFLOW_BASE_URL` and
`QBITFLOW_ON_BEHALF_OF` (blank = unset); it takes the constructor's named arguments, which win
over the environment.

A bad argument makes the constructor throw a `ValidationException`. A client's configuration never
changes; `$client->onBehalfOf()` derives clients that share it. Every request sends
`User-Agent: qbitflow-php/3.0.0` (`QBitFlow::VERSION`).

## Laravel

Laravel **10, 11, 12 and 13** are supported (Composer refuses Laravel 9 and earlier). The package
is auto-discovered: a singleton `QBitFlow` client (also the `QBitFlow` facade), the
`config/qbitflow.php` file, the `qbitflow.webhook` middleware, the `Route::qbitflowWebhooks()`
macro, one Laravel event per webhook type, and two Artisan commands.

```bash
php artisan qbitflow:install   # publishes the config, adds the .env keys
php artisan qbitflow:verify    # calls me(): role, organization, space, mode
```

```dotenv
QBITFLOW_API_KEY=sk_…
QBITFLOW_WEBHOOK_SECRET=whsec_…   # your webhook endpoint's secret
# QBITFLOW_BASE_URL=https://…     # only for a non-production server
```

```php
use QBitFlow\Laravel\Facades\QBitFlow;
use QBitFlow\Params\CreatePaymentSessionParams;

$session = QBitFlow::checkoutSessions()->createPayment(new CreatePaymentSessionParams(
	productName: 'Order 1042',
	priceUsd: '19.99',
	reference: 'order-1042',
	successUrl: url('/orders/1042?session=' . \QBitFlow\Placeholders::UUID),
));
// Or inject QBitFlow\QBitFlow in your controllers and jobs.
```

**Webhooks.** One line in `routes/api.php` (or `routes/web.php`, excluded from CSRF protection):

```php
Route::qbitflowWebhooks('webhooks/qbitflow'); // POST, verified, answered 200
```

The middleware verifies the `QBitFlow-Signature` with `QBITFLOW_WEBHOOK_SECRET` (400
`{"error":"invalid signature"}`, or `{"error":"invalid event"}` for a body that is not a v2
event, 413 over 1 MiB);
the controller then dispatches, through a [`WebhookRouter`](#2-handle-the-deliveries-with-a-router),
the Laravel event of the webhook's type, followed by `WebhookReceived` for every delivery
(unknown types included). A listener that throws makes it answer 500 `{"error":"internal error"}`
(reported to your exception handler; QBitFlow retries). Handle them in queued listeners,
deduplicating on `$event->event->id`:

| Webhook | Laravel event (`QBitFlow\Laravel\Events\…`) |
|---|---|
| `payment.completed` | `PaymentCompleted` |
| `subscription.created` / `.billed` / `.statusChanged` / `.actionRequiredChanged` / `.billingFailed` / `.upcomingBill` | `SubscriptionCreated`, `SubscriptionBilled`, `SubscriptionStatusChanged`, `SubscriptionActionRequiredChanged`, `SubscriptionBillingFailed`, `SubscriptionUpcomingBill` |
| `refund.requested` / `.approved` / `.rejected` | `RefundRequested`, `RefundApproved`, `RefundRejected` |
| `member.joined` / `member.removed` | `MemberJoined`, `MemberRemoved` |
| `heldFunds.released` | `HeldFundsReleased` |
| `checkout.expired` | `CheckoutExpired` |
| `webhook.test` | `WebhookTestReceived` |
| every delivery | `WebhookReceived` |

Each typed event has `event` (the SDK's `…Event`) and `data` (its typed data):

```php
use Illuminate\Contracts\Queue\ShouldQueue;
use QBitFlow\Laravel\Events\PaymentCompleted;

final class FulfilPaidOrder implements ShouldQueue
{
	public function handle(PaymentCompleted $event): void
	{
		// $event->event->id: deduplicate; $event->event->test: test mode
		Order::where('reference', $event->data->reference)->firstOrFail()->markPaid($event->data->uuid);
	}
}
```

To verify on a route of your own, use the `qbitflow.webhook` middleware: the verified event is on
`$request->attributes->get('qbitflow.event')`. Or resolve a `QBitFlow\Webhooks\WebhookRouter`
from the container (a fresh one per resolution, on `QBITFLOW_WEBHOOK_SECRET` and
`QBITFLOW_WEBHOOK_TOLERANCE`) and register your own handlers ([recipe](#the-webhook-endpoint)). A PSR-18 client bound in the container
(`Psr\Http\Client\ClientInterface`) is used by the singleton client (proxies, logging, fakes in
tests).

## Migrating from 2.x

3.0.0 is a rewrite for API v2: base URL `/v2`, a new constructor with named arguments, typed
params classes, REST routes (`PATCH` updates, `DELETE` answering 204), money as decimal strings,
UUID ids, members and invitations instead of users and claims, a new webhook signature and typed
events (`refund.approved` / `refund.rejected`), paginated lists with `hasMore`, a new exception
taxonomy, and a rebuilt Laravel integration.
**[MIGRATION-v3.md](https://github.com/QBitFlow/qbitflow-php-sdk/blob/main/MIGRATION-v3.md)** maps every 2.x method and class to its replacement, with
before/after code for the common tasks, and ends with an upgrade checklist.

## Examples

Runnable scripts in [`examples/`](https://github.com/QBitFlow/qbitflow-php-sdk/tree/main/examples) (`QBITFLOW_API_KEY=sk_… php examples/<name>.php`):

| Example | Shows |
|---|---|
| [`client-setup.php`](https://github.com/QBitFlow/qbitflow-php-sdk/blob/main/examples/client-setup.php) | a client from the API key and `me()` (default server), and from the environment |
| [`checkout.php`](https://github.com/QBitFlow/qbitflow-php-sdk/blob/main/examples/checkout.php) | a payment checkout, one with fees (a shipping line and the processing fee), its status, waiting for it (`--wait`), expiry |
| [`catalog.php`](https://github.com/QBitFlow/qbitflow-php-sdk/blob/main/examples/catalog.php) | create and list products, one page of customers, a checkout for a product by its reference |
| [`payments.php`](https://github.com/QBitFlow/qbitflow-php-sdk/blob/main/examples/payments.php) | list payments with a filter, get one by id and by reference, the iterator, the transactions feed (payments and bills), exact amounts |
| [`subscriptions.php`](https://github.com/QBitFlow/qbitflow-php-sdk/blob/main/examples/subscriptions.php) | a subscription checkout with a trial, past-due subscriptions, access, bills, metadata, a test bill (`--bill`), cancel at period end (`--cancel`) |
| [`refunds.php`](https://github.com/QBitFlow/qbitflow-php-sdk/blob/main/examples/refunds.php) | list the pending refunds (customers' requests), read one (refunds are read only) |
| [`marketplace.php`](https://github.com/QBitFlow/qbitflow-php-sdk/blob/main/examples/marketplace.php) | invite a seller, invitations, members, `onBehalfOf`, wallets, held funds, fee, trust (`--trust`), remove (`--remove`) |
| [`webhooks.php`](https://github.com/QBitFlow/qbitflow-php-sdk/blob/main/examples/webhooks.php) | create a webhook endpoint (`--create`), the event log |
| [`webhook-handler.php`](https://github.com/QBitFlow/qbitflow-php-sdk/blob/main/examples/webhook-handler.php) | a plain-PHP receiver with the webhook router: typed handlers, the right answers (`php -S`, `QBITFLOW_WEBHOOK_SECRET`) |
| [`webhook-verify.php`](https://github.com/QBitFlow/qbitflow-php-sdk/blob/main/examples/webhook-verify.php) | the lower level: `Webhook::constructEvent()` on the raw body (`php -S`, `QBITFLOW_WEBHOOK_SECRET`) |
| [`errors-and-retries.php`](https://github.com/QBitFlow/qbitflow-php-sdk/blob/main/examples/errors-and-retries.php) | exception types, `isRetryable()`, retries and idempotency keys across processes |
| [`accounting.php`](https://github.com/QBitFlow/qbitflow-php-sdk/blob/main/examples/accounting.php) | a year of accounting events as JSON and CSV |
| [`currencies.php`](https://github.com/QBitFlow/qbitflow-php-sdk/blob/main/examples/currencies.php) | the currencies customers can pay with |
| [`laravel/`](https://github.com/QBitFlow/qbitflow-php-sdk/tree/main/examples/laravel) | the webhook route, a checkout controller with the facade, queued listeners |

## Testing

```bash
composer install
composer check   # composer validate --strict, php -l, PHPStan, the Unit suite
```

The `Unit` suite runs against a mock PSR-18 client: nothing touches the network. Two of its tests
use files QBitFlow's maintainers keep outside this repository (shared cross-SDK test vectors, the
website snippet check) and skip themselves without them; [CONTRIBUTING.md](https://github.com/QBitFlow/qbitflow-php-sdk/blob/main/CONTRIBUTING.md) lists
the gates every contribution passes. The live checks are a separate suite and need an API key
**and** an explicit base URL:

```bash
QBITFLOW_API_KEY=sk_… QBITFLOW_BASE_URL=https://… vendor/bin/phpunit --testsuite Integration
```

The read-only checks only read; the write checks also need `QBITFLOW_LIVE_WRITES=1` and a
test-mode key (or `QBITFLOW_ALLOW_LIVE_MODE_WRITES=1`). To test your own code, inject a fake
PSR-18 client: `new QBitFlow(apiKey: 'sk_test', httpClient: $fakeClient)`.

## License

This project is licensed under the MPL-2.0 License: see the [LICENSE](https://github.com/QBitFlow/qbitflow-php-sdk/blob/main/LICENSE) file. See also
[COMPLIANCE.md](https://github.com/QBitFlow/qbitflow-php-sdk/blob/main/COMPLIANCE.md) and the [trademark policy](https://github.com/QBitFlow/qbitflow-php-sdk/blob/main/TRADEMARKS.md).

## Support

- [Documentation](https://qbitflow.app/docs)
- [Email Support](mailto:support@qbitflow.app)
- [Issue Tracker](https://github.com/QBitFlow/qbitflow-php-sdk/issues)

## Changelog

See [CHANGELOG.md](https://github.com/QBitFlow/qbitflow-php-sdk/blob/main/CHANGELOG.md) for version history.

## Security

For security issues, please email security@qbitflow.app instead of using the issue tracker (see
[SECURITY.md](https://github.com/QBitFlow/qbitflow-php-sdk/blob/main/SECURITY.md)).
