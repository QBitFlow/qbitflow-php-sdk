# QBitFlow PHP SDK

[![Packagist](https://img.shields.io/packagist/v/qbitflow/qbitflow-php.svg)](https://packagist.org/packages/qbitflow/qbitflow-php)
[![PHP Version](https://img.shields.io/badge/PHP-8.2+-777BB4?style=flat&logo=php)](https://www.php.net/)
[![License: MPL-2.0](https://img.shields.io/badge/License-MPL_2.0-brightgreen.svg)](https://opensource.org/licenses/MPL-2.0)

The official PHP SDK for [QBitFlow](https://qbitflow.app), non-custodial crypto payments: hosted
checkouts, one-time payments, subscriptions, refunds, marketplaces with commissions and held
funds, accounting exports and signed webhooks. Customers pay from their own wallets, on Ethereum,
Base and Solana, straight to yours.

- **API v2**, every integrator route: 13 services, 60 routes.
- **Typed end to end**: readonly params classes with named arguments, readonly models named
  exactly as the API's JSON, typed webhook events.
- **Typed exceptions**, each carrying the API's code, request id and field errors.
- **Safe retries**: reads and creates are retried on network errors, 5xx and 429, and every create
  sends an `Idempotency-Key`, so a retry never charges or creates twice.
- **Iterators**: `foreach ($client->payments->iterate() as $payment)` walks every page lazily.
- **Webhooks** verified locally (`QBitFlow-Signature`, secret rotation included) and parsed into
  typed events, with or without a client.
- **PSR-18 / PSR-17**: any HTTP client; **Laravel** service provider, facade, middleware, events
  and Artisan commands included.

> Coming from 2.x? Read [MIGRATION-v3.md](MIGRATION-v3.md): 3.0.0 targets API v2 and changes the
> client, the models and most names.

## Contents

- [Installation](#installation)
- [Quick start](#quick-start)
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

**Requires PHP 8.2 or later**, a [PSR-18](https://www.php-fig.org/psr/psr-18/) HTTP client and
[PSR-17](https://www.php-fig.org/psr/psr-17/) factories. Guzzle (already in every Laravel
application) is detected and used automatically; otherwise install one:

```bash
composer require guzzlehttp/guzzle        # or: symfony/http-client nyholm/psr7
```

## Quick start

```php
use QBitFlow\Params\CreatePaymentSessionParams;
use QBitFlow\QBitFlow;

// One client per API key, shared by the whole application.
$client = new QBitFlow(apiKey: getenv('QBITFLOW_API_KEY'));

// The recommended start-up check: what is this key, and which mode is it in?
$me = $client->me(); // an AuthenticationException for an unknown or revoked key
printf("%s, role %s, test mode %s\n", $me->space?->organizationName, $me->role, $me->space?->test ? 'yes' : 'no');

// A hosted checkout for a one-time payment of 4.99 USD.
$session = $client->checkoutSessions->createPayment(new CreatePaymentSessionParams(
	productName: 'Premium access',
	price: 4.99,
	reference: 'order-1042',
	successUrl: 'https://shop.example.com/thanks?session={{UUID}}',
	cancelUrl: 'https://shop.example.com/cart',
));
echo "Send the customer to {$session->link}\n";
```

Then fulfil the order when the [`payment.completed` webhook](#webhooks) arrives for
`$session->uuid`, never on the customer's redirect to your success page.

### Conventions

- **Services are properties** of the client (`$client->products`, `$client->webhooks->endpoints`),
  methods are camelCase (`createPayment`, `listCombined`). Every method takes an optional
  [`RequestOptions`](#configuration) last.
- **Params are readonly classes** built with named arguments (`new CreateProductParams(name: …,
  price: …)`). Optional params arguments are nullable: `null` means "no filter" or "the defaults".
- **Ids are strings.** Resources are named by UUIDs; transactions by prefixed ids (`pay@…` a
  payment, `sub@…` a subscription, `sub-hist@…` a bill, `refund@…` a refund) that you pass back
  verbatim. Only currencies keep numeric ids (`int`).
- **Models are readonly classes** whose properties are the API's JSON keys (`$payment->txHash`,
  `$payment->metadata->txAmounts->usd->merchant`). A field the API always sends is non-nullable
  (its zero value when absent: `''`, `0`, `false`, `[]`, Go's zero time `0001-01-01T00:00:00Z`,
  see `QBitFlow\Support\Time::isZero()`); an optional field is nullable (`null` when absent).
- **Amounts:** USD amounts are `float` (`amount`, `amountUsd`); exact amounts in a token's
  smallest unit, and a few USD prices, are decimal strings (`amountMinUnits`, `priceUsd`,
  `allowance`), never rounded. Fee rates are percents: `feePercent: 1.5` is 1.5 %.
- **Enums** are plain strings with a constants class (`QBitFlow\Enums\SubscriptionStatus::ACTIVE`).
  A value this SDK does not know yet is kept as is: give every `match` a `default` arm.
- **Times** are `DateTimeImmutable`, with the offset the API sent and microseconds.

## Authentication and acting for a member

Every request sends your API key in `X-API-Key`. Keys are created in the QBitFlow dashboard; each
belongs to one **space** (your organization's, or one of its members') and one **mode** (test or
live). The constructor only checks the key's shape (non-blank, starting with `sk_`) and sends
nothing: call `me()` to check it online.

```php
$me = $client->me();
if ($me->space === null || ! $me->space->test) {
	throw new RuntimeException('this job must run with a test-mode key');
}
echo "acting as {$me->role} in {$me->space->organizationName}\n"; // admin: an organization key
```

Keep the key in a secret store or an environment variable, never in code. Keys issued before API v2
(`sk_<digits>_…`) still work; rotate them in the dashboard to the `sk_<uuid>_…` format.

### `On-Behalf-Of`: acting in a member's space

A marketplace's **organization key** can act in any of its members' spaces: create their products
and checkouts, read their payments. Name the member by their **user UUID** (`Member::$userUuid`,
also in the `member.joined` webhook). A non-member, an owner or admin of the team, or a member of
the other mode answers 404.

```php
use QBitFlow\RequestOptions;

$memberUuid = '0192f1c2-7b3a-7c4d-9e5f-6a7b8c9d0e1f'; // Member::$userUuid

// A client acting in the member's space. It shares the transport and settings of $client.
$seller = $client->onBehalfOf($memberUuid);
echo count($seller->products->list()), " products in the seller's space\n";

// One request only: the request option wins over the client's.
$page = $client->payments->list(null, new RequestOptions(onBehalfOf: $memberUuid));
echo count($page->items), " payments of the seller\n";

// '' forces the organization's own space for one request of the seller's client.
$own = $seller->products->list(null, new RequestOptions(onBehalfOf: ''));
echo count($own), " products of the organization\n";
```

`onBehalfOf` is also a constructor argument: `new QBitFlow(apiKey: $key, onBehalfOf: $memberUuid)`.
A value that is not a UUID (or the nil UUID) is a `ValidationException`, thrown at once by
`onBehalfOf()` or the constructor, and nothing is sent.

## Checkout sessions

A checkout session is a hosted payment page. Create it, send your customer to its `link`, and act
on the webhook. The session's id (`pay@…` or `sub@…`) is also the id of the payment or the
subscription it creates once the customer's transaction is confirmed.

Name the product with **exactly one** of `productUuid`, `productReference`, or an inline product
(`productName` + `price`, `description` optional):

```php
use QBitFlow\Params\CreatePaymentSessionParams;

$session = $client->checkoutSessions->createPayment(new CreatePaymentSessionParams(
	productUuid: '0192f1c2-1111-7c4d-9e5f-6a7b8c9d0e1f', // or productReference: 'tshirt-blue-m'
	reference: 'order-1043',                              // your order id: unique per space
	customerReference: 'crm-42',                          // kept on the payment
	successUrl: 'https://shop.example.com/orders/1043?session={{UUID}}&type={{TRANSACTION_TYPE}}',
	cancelUrl: 'https://shop.example.com/cart',
	expiresInMinutes: 30,                                 // 10 to 1440; null = the default
));
echo "pay at {$session->link} - session {$session->uuid}\n";
```

A subscription checkout takes the same fields plus its terms, each optional over a subscription
product's:

```php
use QBitFlow\Models\Duration;
use QBitFlow\Params\CreateSubscriptionSessionParams;

$session = $client->checkoutSessions->createSubscription(new CreateSubscriptionSessionParams(
	productName: 'Pro plan',
	price: 4.99, // USD per period
	frequency: Duration::months(1),
	trialPeriod: Duration::days(14),
	minPeriods: 3, // the customer commits to 3 periods
	successUrl: 'https://app.example.com/billing?subscription={{UUID}}',
));
echo "subscribe at {$session->link}\n";
```

- **Redirect placeholders.** In `successUrl` and `cancelUrl`, QBitFlow replaces `{{UUID}}` with the
  session's id and `{{TRANSACTION_TYPE}}` with `payment` or `createSubscription`. In live mode both
  URLs must be `https`. A redirect proves nothing (anyone can open the URL): fulfil on the
  webhook, or on `getStatus()`.
- **Errors to expect:** `409 merchant_not_ready` (`details['reason']`) when the space's wallets
  accept no currency; `409 unique_violation` when another payment or open session holds the
  `reference`; `400 validation_failed` above 5 USD in test mode (`details['max']`); `404` for an
  unknown product or customer.

### Status

```php
use QBitFlow\Enums\CheckoutSessionStatusValue;

$status = $client->checkoutSessions->getStatus($session->uuid);
match ($status->status) {
	CheckoutSessionStatusValue::COMPLETED => print("paid, tx {$status->txHash}\n"),
	CheckoutSessionStatusValue::EXPIRED => print("expired unpaid: {$status->message}\n"),
	CheckoutSessionStatusValue::WAITING_CONFIRMATION => print("sent, waiting for the network\n"),
	default => print($status->lastAttempt !== null // created, or a status this SDK does not know
		? "last attempt failed: {$status->lastAttempt->code}\n" // the customer may try again
		: "waiting for the customer\n"),
};
```

| `status` | Meaning | Final |
|---|---|---|
| `created` | Waiting for the customer. With `lastAttempt` set, their last attempt failed (`lastAttempt->code` says why) | no |
| `waitingConfirmation` | A transaction was sent; waiting for the network. It may last: the transaction can still land | no |
| `completed` | Confirmed and recorded: the `Payment` or `Subscription` exists, with the session's id | yes |
| `expired` | Expired unpaid (`checkout.expired` was sent). Read some days later, an expired session is a 404 | yes |

**Never cancel an order on `lastAttempt`:** a failed attempt is not final, and the customer can pay
from the same checkout until it expires. Release what the order holds on `checkout.expired`.

### Expire

End a session early (an order cancelled on your side). It answers its status, and
`checkout.expired` follows. Once the customer paid or is paying it is a `409 tx_already_sent`.

```php
$expired = $client->checkoutSessions->expire($session->uuid);
echo $expired->status, "\n"; // expired
```

## Products and customers

Products are optional (a checkout can name an inline product) and give you a reusable catalog
with payment links. A subscription product carries its terms.

```php
use QBitFlow\Models\Duration;
use QBitFlow\Params\CreateProductParams;
use QBitFlow\Params\ProductListParams;
use QBitFlow\Params\SubscriptionTermsParams;
use QBitFlow\Params\UpdateProductParams;

$product = $client->products->create(new CreateProductParams(
	name: 'Pro plan',
	price: 4.99,
	description: 'Everything, billed monthly',
	reference: 'pro-monthly', // unique per space; generated when null
	subscription: new SubscriptionTermsParams(frequency: Duration::months(1)),
));

// Only the arguments given change. A new price applies to new checkouts and subscribers only.
$product = $client->products->update($product->uuid, new UpdateProductParams(price: 5.99, isActive: false));

$all = $client->products->list(new ProductListParams(includeHidden: true, subscription: true));
echo $product->paymentLink, ' ', count($all), "\n";
```

`products->get()`, `getByReference()` and `delete()` complete the set. Deleting a product does not
stop its subscriptions: cancel them with `subscriptions->cancel()` if the product is gone for good.

```php
use QBitFlow\Params\CreateCustomerParams;
use QBitFlow\Params\UpdateCustomerParams;

$customer = $client->customers->create(new CreateCustomerParams(
	name: 'Ada',
	email: 'ada@example.com',
	lastName: 'Lovelace',
	reference: 'crm-42',
));

// '' clears phoneNumber or address; null (the default) leaves them unchanged.
$customer = $client->customers->update($customer->uuid, new UpdateCustomerParams(phoneNumber: ''));

$byEmail = $client->customers->getByEmail('ada@example.com');
var_dump($customer->uuid === $byEmail->uuid);
```

`customers->get()`, `getByReference()`, `list()` / `iterate()` (by `email` or `verified`) and
`delete()` complete the set. A checkout given `customerUuid` or `customerReference` asks the
customer nothing; without either, the checkout asks what your `checkout.customerDetails` setting
says (the full details by default).

## Payments and failures

A `Payment` exists once its transaction is confirmed, with its checkout session's `pay@…` id.

```php
use QBitFlow\Enums\FailureCategory;
use QBitFlow\Params\FailureListParams;
use QBitFlow\Params\PaymentListParams;

$page = $client->payments->list(new PaymentListParams(
	createdAfter: new DateTimeImmutable('-1 month'),
	includeMembers: true, // organization key: the members' payments too
	limit: 50,
));
foreach ($page->items as $p) {
	printf("%s %s: %.2f USD (%s), merchant got %.2f USD\n",
		$p->uuid, $p->reference ?? '-', $p->amount, $p->explorerUrl ?? '', $p->metadata->txAmounts->usd->merchant);
}

$payment = $client->payments->get('pay@0192f1c2-2222-7c4d-9e5f-6a7b8c9d0e1f');
$byRef = $client->payments->getByReference('order-1042');
echo $payment->txHash, ' ', $byRef->uuid, ' ', $payment->refundable ? 'refundable' : 'not refundable', "\n";
```

- **Filters** (`PaymentListParams`): `customerUuid`, `productUuid`, `createdAfter` / `createdBefore`
  (both excluded), `refunded`, and, with an organization key acting for itself, `includeMembers`
  (every member's rows, each naming its `userUuid`) or `userUuid` (one member's). The last two
  exclude each other.
- **Reading a member's row** from the organization's space:
  `$client->payments->get($id, new ReadParams(includeMembers: true))`, or `onBehalfOf` the member.
- **The combined feed** of one-time payments and subscription bills, newest first:
  `payments->listCombined()` / `iterateCombined()`, with the same filters plus `source`
  (`CombinedPaymentSource::PAYMENT`, `::SUBSCRIPTION_HISTORY`) and `subscriptionUuid`.
- **Failures** are the failed attempts to pay a checkout or a bill. They never moved money and
  are not final: the customer can try again.

```php
foreach ($client->failures->iterate(new FailureListParams(category: FailureCategory::INSUFFICIENT_BALANCE)) as $f) {
	printf("%s attempt %d: %s (%.2f USD)\n", $f->txUuid, $f->attempt, $f->code, $f->attemptedUsd);
}
```

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
| `stopped` | Cancelled during its period: never billed again, `cancelled` at `nextBillingDate` |
| `cancelled` | Over (`cancellationReason` says why). Final |

**Access rule: grant access while `now < currentPeriodEnd`, whatever the status.** A `stopped` or
`paused` subscription has paid for its period; a `pastDue` one's period has ended.

```php
use QBitFlow\Models\Subscription;

function hasAccess(Subscription $subscription): bool
{
	return $subscription->currentPeriodEnd !== null && new DateTimeImmutable() < $subscription->currentPeriodEnd;
}
```

`actionRequired` says what the customer must do (`topUpAllowance`, `raiseMaximum`,
`confirmTrial`; `null`: nothing): point them to the `managementPageLink` the subscription webhooks
carry.

```php
use QBitFlow\Enums\SubscriptionStatus;
use QBitFlow\Params\SubscriptionListParams;

$page = $client->subscriptions->list(new SubscriptionListParams(status: SubscriptionStatus::PAST_DUE));
foreach ($page->items as $sub) {
	if ($sub->dunning !== null) {
		echo "{$sub->uuid}: {$sub->dunning->remainingAttempts} attempts left\n";
	}
}

$sub = $client->subscriptions->get('sub@0192f1c2-3333-7c4d-9e5f-6a7b8c9d0e1f'); // cancelled ones too
echo "{$sub->status}, {$sub->priceUsd} USD per period\n";

// Every bill, newest first: the iterator fetches the pages lazily.
foreach ($client->subscriptions->iterateBills($sub->uuid) as $bill) {
	printf("%s: %.2f USD, paid until %s\n", $bill->uuid, $bill->amount, $bill->periodEnd?->format('Y-m-d') ?? '?');
}
```

- `subscriptions->getByReference()` reads one by your checkout's `reference`; `getBill()` one bill
  (`sub-hist@…`).
- `subscriptions->getPublicHistory()` returns the 10 latest bills as the customer's page shows
  them. It is a public route: the fields only the merchant sees (`metadata`, `customerUuid`,
  `customerReference`, `userUuid`, `paidMinUnits`, `refund`, …) are empty there. Use
  `listBills()` for full bills.
- Before its checkout completes, `subscriptions->get()` is a 404: read the checkout session instead.

### Cancel

`cancel()` cancels without the customer signing. By default it is immediate (`cancelled`, reason
`merchant`); `immediate: false` stops it now and cancels it at the end of the period paid for.

```php
use QBitFlow\Params\CancelSubscriptionParams;

$result = $client->subscriptions->cancel($sub->uuid, new CancelSubscriptionParams(immediate: false));
if ($result->pending) {
	// HTTP 202: the on-chain cancellation is still confirming and the status is not updated yet.
	// It goes on regardless; subscription.statusChanged tells the end.
	echo "cancellation confirming\n";
} else {
	echo "now {$result->subscription->status}\n"; // stopped
}
```

`cancel()` answers 200 when done and 202 (`pending`) while confirming on-chain. It is never retried
automatically (409 `subscription_already_stopped_or_inactive`, 404 once cancelled).

### Test billing

In test mode a subscription is billed only when you ask, with live's statuses and webhooks:

```php
$state = $client->subscriptions->executeTestBilling($sub->uuid); // 409 payment_not_due before nextBillingDate
echo "{$state->stage} {$state->outcome} {$state->failureCode}\n";
```

Walk the timeline once in test mode: a 5-minute frequency, pay from a test wallet, trigger the
bill, then empty the wallet and trigger it again to see `subscription.billingFailed` and
`pastDue`.

## Refunds

```php
use QBitFlow\Params\RefundListParams;

// Refunds waiting for an answer. From the organization's space the members' are included by
// default: includeMembers: false leaves them out.
foreach ($client->refunds->list(new RefundListParams(includeMembers: false)) as $r) {
	echo "{$r->uuid} {$r->txUuid} {$r->initiatedBy} {$r->reason} {$r->amountUsd}\n";
}

// Answered refunds (approved or rejected), page by page.
foreach ($client->refunds->iterateInactive() as $r) {
	echo "{$r->uuid} {$r->status} {$r->explorerUrl}\n";
}
```

`refunds->initiate()` starts a refund of a payment (`pay@…`) or a bill (`sub-hist@…`) of the space.
**It creates a pending refund: no money moves until you sign the transfer in the dashboard**,
from the wallet that was paid. `refund.completed` tells you when it is sent.

```php
use QBitFlow\Exceptions\ConflictException;
use QBitFlow\Params\InitiateRefundParams;

try {
	$refund = $client->refunds->initiate(new InitiateRefundParams(
		txUuid: 'pay@0192f1c2-2222-7c4d-9e5f-6a7b8c9d0e1f',
		refundPercent: 50.0, // of everything the customer paid, network fee included; null = 100
		reason: 'Damaged in transit',
		merchantMessage: 'Sorry about that: half of your payment is on its way back.',
	));
	echo "{$refund->uuid} {$refund->status}\n"; // pending
} catch (ConflictException $e) {
	if ($e->apiCode !== 'refund_already_exists') {
		throw $e;
	}
	echo 'already refunded: ', $e->details['refundUuid'] ?? '', "\n"; // one refund per transaction
}
```

A refund is `pending`, `approved` or `rejected`; `initiatedBy` is `customer` (a request you answer
in the dashboard) or `merchant`. A held seller's payment already released to them can no longer be
refunded (`409 held_funds_released`).

## Marketplaces

A marketplace is an organization whose sellers are its **members**. You invite them, sell for
them with your organization key and `On-Behalf-Of`, take a commission on their payments, and may
hold their funds until you trust them. QBitFlow stays non-custodial: money goes from the customer's
wallet to the seller's (and your commission to yours) in one transaction.

**1. Invite the seller.** The SDK always invites members (`role: user`); the team is invited from
the dashboard.

```php
use QBitFlow\Params\CreateInvitationParams;

$created = $client->invitations->create(new CreateInvitationParams(
	email: 'seller@example.com',
	trustLayer: true,            // hold their payments until members->trust()
	organizationFeePercent: 5.0, // your commission: 0 to 50 %, at most 2 decimals
	redirectUrl: 'https://market.example.com/welcome',
)); // 409 already_joined for a member; 429 beyond 50 invitations an hour
echo "{$created->invitation->uuid} {$created->link}\n"; // the link is also emailed
```

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
use QBitFlow\Params\SupportedCurrenciesParams;

$currencies = $client->wallets->listSupportedCurrencies(new SupportedCurrenciesParams(userUuid: $memberUuid));
if ($currencies === []) {
	throw new RuntimeException('the seller cannot be paid yet: their checkouts would answer 409 merchant_not_ready');
}

$seller = $client->onBehalfOf($memberUuid);
$session = $seller->checkoutSessions->createPayment(new CreatePaymentSessionParams(
	productName: 'Handmade mug',
	price: 4.5,
	successUrl: 'https://market.example.com/orders/{{UUID}}',
)); // 403 policy_disabled when your policies don't let members do this
echo $session->link, "\n";
```

**4. Hear of every sale.** An organization webhook endpoint receives every seller's events by
default; the event's `userUuid` names the seller. Use it as `onBehalfOf` for follow-up reads.

**5. Commission and held funds.** Each payment records your fee in `metadata->organizationFee` and
`metadata->txAmounts`. While you hold a seller's funds (`trustLayer` true, `Member::$trustedAt`
null), their payments go to your wallet and the net is owed to them:

```php
use QBitFlow\Params\UpdateMemberParams;

$held = $client->members->getHeldFunds($memberUuid);
printf("owed to the seller: %.2f USD over %d lines\n", $held->totalAmount, count($held->ledgers));

// Their new payments go to their own wallets from now on. What is held stays held until you
// release it from the dashboard (heldFunds.released tells you).
$member = $client->members->trust($memberUuid);
echo 'trusted since ', $member->trustedAt?->format(DATE_ATOM), "\n";

// Change the commission (a checkout already created keeps its fee).
$client->members->update($memberUuid, new UpdateMemberParams(organizationFeePercent: 7.5));
```

- `members->list()` / `iterate()` / `get()`, `members->listHeldFunds()` (every member owed), and
  `$seller->members->getOwnHeldFunds()` (the seller's side) complete the reads.
- `members->remove()` ends a seller's membership in the key's mode: their keys stop working and
  their checkouts close. It is a `409 held_funds_pending` while you hold their live funds: release
  them first.
- `invitations->list()` / `iterate()` (by `status`) and `invitations->revoke()` manage the invitations.
- Today a seller whose account already belongs to another organization cannot accept (no
  `member.joined` comes), and test-mode sellers are real accounts that accept from a real inbox.

## Wallets

Wallets are added and removed in the dashboard, by their owner only. The SDK reads them.

```php
use QBitFlow\Params\WalletListParams;

foreach ($client->wallets->list(new WalletListParams(withBalances: true)) as $wallet) {
	echo "{$wallet->currency->symbol} {$wallet->publicKey}\n";
	foreach ($wallet->tokenWallets as $tokenWallet) {
		if ($tokenWallet->balance !== null) {
			printf("  %s: %s (%.2f USD)\n", $tokenWallet->token->symbol, $tokenWallet->balance->balance, $tokenWallet->balance->balanceUsd);
		}
	}
}
```

`wallets->listForMember($userUuid)` reads a member's wallets (organization key), and
`wallets->listSupportedCurrencies()` the currencies a space's checkouts accept: none means its
checkouts answer `409 merchant_not_ready`.

## Accounting export

Every payment, bill, refund and fee between two dates (`YYYY-MM-DD`, both included), as JSON rows
or as CSV text:

```php
foreach ($client->accounting->exportJson('2026-09-01', '2026-09-30') as $e) {
	echo "{$e->type} {$e->paymentUuid} {$e->txTimeUtc->format(DATE_ATOM)} {$e->tokenSymbol} {$e->grossAmount} {$e->netAmount}\n";
}

file_put_contents('qbitflow-2026-09.csv', $client->accounting->exportCsv('2026-09-01', '2026-09-30'));
```

The SDK checks the dates and `from <= to` before sending. **The API allows at most 95 days per
export** and answers 400 beyond: split longer ranges. Rows are typed `payment`,
`subscriptionHistory`, `refund`, `organizationFee` or `referralFee`; amounts in a token's smallest
unit are decimal strings, and the empty fields of a row are `null`.

## Webhooks

QBitFlow posts an **event** to your endpoints when something happens: a payment confirmed, a
subscription billed, a member joined.

### 1. Create an endpoint and store its secret

```php
use QBitFlow\Enums\EventType;
use QBitFlow\Params\CreateWebhookEndpointParams;

$created = $client->webhooks->endpoints->create(new CreateWebhookEndpointParams(
	url: 'https://shop.example.com/webhooks/qbitflow',
	events: [ // null: every type, including the ones added later
		EventType::PAYMENT_COMPLETED,
		EventType::CHECKOUT_EXPIRED,
		EventType::SUBSCRIPTION_STATUS_CHANGED,
	],
	description: 'Order fulfilment',
));
// The whsec_… secret is shown only this once: put it in your secret store now.
echo "{$created->uuid} {$created->secret}\n";
```

Up to 10 endpoints per space and mode; live endpoints need `https` and a public host. An
organization endpoint also receives its members' events unless created with
`includeMembers: false`. `endpoints->list()`, `get()`, `update()` (`enabled: false` pauses it,
`true` enables it again) and `delete()` manage them. An endpoint's secret is shown and rotated in
the dashboard only.

### 2. Verify and handle the deliveries

Verify the `QBitFlow-Signature` header over the **raw body**, then act on the typed event. No
client (and no API key) is needed: `QBitFlow\Webhooks\Webhook` works on its own.

```php
use QBitFlow\Events\CheckoutExpiredEvent;
use QBitFlow\Events\PaymentCompletedEvent;
use QBitFlow\Events\SubscriptionStatusChangedEvent;
use QBitFlow\Exceptions\ValidationException;
use QBitFlow\Exceptions\WebhookSignatureException;
use QBitFlow\Webhooks\Webhook;

$rawBody = file_get_contents('php://input'); // the bytes as received: never re-serialize
try {
	$event = Webhook::constructEvent($rawBody, $_SERVER['HTTP_QBITFLOW_SIGNATURE'] ?? '', getenv('QBITFLOW_WEBHOOK_SECRET'));
} catch (WebhookSignatureException $e) {
	http_response_code(400); // $e->reason: noMatchingSignature, timestampOutsideTolerance, …
	exit;
} catch (ValidationException $e) {
	http_response_code(400); // not a v2 event: an endpoint still on payload version v1
	exit;
}

if (alreadyProcessed($event->id)) { // your database: deliveries are at least once
	http_response_code(200);
	exit;
}

if ($event instanceof PaymentCompletedEvent) {
	fulfil($event->data->reference, $event->data->uuid, $event->data->amount);
} elseif ($event instanceof CheckoutExpiredEvent) {
	release($event->data->reference);
} elseif ($event instanceof SubscriptionStatusChangedEvent) {
	$access = $event->data->currentPeriodEnd !== null && new DateTimeImmutable() < $event->data->currentPeriodEnd;
	syncAccess($event->data->uuid, $event->data->previousStatus, $event->data->status, $access);
}
// Any other type, or one added after this SDK (an UnknownEvent): acknowledge it.
http_response_code(200);
```

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
- **v2 only.** An endpoint migrated from API v1 receives v1 bodies, which `parseEvent()` refuses:
  move it to v2 in the dashboard, or with `$client->webhooks->endpoints->update($uuid,
  new UpdateWebhookEndpointParams(payloadVersion: WebhookPayloadVersion::V2))`.
- Not holding the secret? `$client->webhooks->verifyRemote($endpointUuid, $rawBody, $header)` has
  the API check it (a `WebhookSignatureException` with reason `invalidSignature` when it does not
  match), then `Webhook::parseEvent()` parses the body.

| `type` | Event class | `$event->data` |
|---|---|---|
| `payment.completed` | `PaymentCompletedEvent` | `PaymentCompleted`: the `Payment` + `managementPageLink` |
| `subscription.created` | `SubscriptionCreatedEvent` | `SubscriptionCreated`: the `Subscription` (`active` or `trial`) + `managementPageLink` |
| `subscription.billed` | `SubscriptionBilledEvent` | `SubscriptionBilled`: the `Bill` (paid until `periodEnd`) + `subscriptionReference`, `subscriptionStatus` |
| `subscription.statusChanged` | `SubscriptionStatusChangedEvent` | `SubscriptionStatusChanged`: the `Subscription` + `previousStatus` |
| `subscription.actionRequiredChanged` | `SubscriptionActionRequiredChangedEvent` | `SubscriptionActionRequiredChanged`: the `Subscription` + `previousActionRequired` |
| `subscription.billingFailed` | `SubscriptionBillingFailedEvent` | `SubscriptionBillingFailed`: the `Subscription` + `reason`, `billUuid`, `amountUsd` (a string), `attempt`, `remainingAttempts`, `nextAttemptAt` |
| `subscription.upcomingBill` | `SubscriptionUpcomingBillEvent` | `SubscriptionUpcomingBill`: the `Subscription` + `billingDate`, `amountUsd` (a float), `trialEnding`, `balanceSufficient`, `allowanceSufficient` |
| `refund.requested` / `.completed` / `.denied` | `RefundRequestedEvent` / `RefundCompletedEvent` / `RefundDeniedEvent` | the `Refund` |
| `member.joined` | `MemberJoinedEvent` | `MemberJoined`: the `Member` + `invitationUuid` |
| `member.removed` | `MemberRemovedEvent` | the `Member` |
| `heldFunds.released` | `HeldFundsReleasedEvent` | `HeldFundsReleased`: the transfer paid to the member + the `ledgers` it settled |
| `checkout.expired` | `CheckoutExpiredEvent` | `PaymentSessionData`, or `SubscriptionSessionData` for a subscription session |
| `webhook.test` | `WebhookTestEvent` | `WebhookTest`: `endpointUuid`, `message` (the dashboard's test) |
| any other | `UnknownEvent` | the raw `data` array |

Every event also has `id`, `type`, `version`, `createdAt`, `test`, `userUuid` and `rawData`;
`$event->decodeData(Subscription::fromArray(...))` reads the data as another model. Webhook data
never carries what only API reads return (`customer`, `refund`/`refundable`, `dunning`,
`approval`, `productName`). In Laravel, the [middleware and events](#laravel) do all of this.

### The event log

Every event of the space, newest first, with each one's deliveries:

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

## Currencies

```php
use QBitFlow\Params\CurrencyListParams;

$byId = [];
foreach ($client->currencies->listAvailable(new CurrencyListParams(test: true)) as $currency) {
	$byId[$currency->id] = $currency; // e.g. 6-decimal USDC
}
$usdc = $client->currencies->get(array_key_first($byId)); // resolve one id
echo "{$usdc->symbol} {$usdc->decimals}\n";
```

`listAvailable()` lists every currency checkouts can take, `listMain()` the chains' native coins,
and `get()` one by id, to resolve the `currencyId`, `availableCurrencyIds` and
`acceptedCurrencyIds` fields. These routes are public and **limited to 60 requests a minute per
IP: cache the list** at start-up instead of reading it per request. Payments, bills and
subscriptions already carry their `currency`.

## Pagination and iterators

Paginated lists return a `QBitFlow\Page`: `items`, and `nextCursor` (null on the last page, else
the value to pass back as the params' `cursor`, verbatim).

```php
use QBitFlow\Params\CustomerListParams;

$params = new CustomerListParams(limit: 100);
do {
	$page = $client->customers->list($params);
	foreach ($page->items as $customer) {
		echo $customer->email, "\n";
	}
	$params = $params->withCursor($page->nextCursor);
} while ($page->hasMore());
```

Each paginated list has an `iterate*()` twin returning a `Generator`: it fetches one page at a
time, only as you consume it, keeps your filters and page size, and stops when you `break`. An
error is thrown from the `foreach`, after the items before it.

```php
foreach ($client->customers->iterate(new CustomerListParams(verified: true)) as $customer) {
	echo "{$customer->uuid} {$customer->email}\n";
}
```

| Method | Iterator | Page size: default / max |
|---|---|---|
| `customers->list()` | `iterate()` | 10 / 100 |
| `payments->list()`, `payments->listCombined()` | `iterate()`, `iterateCombined()` | 10 / 50 |
| `failures->list()` | `iterate()` | 10 / 50 |
| `subscriptions->list()`, `subscriptions->listBills()` | `iterate()`, `iterateBills()` | 20 / 100 |
| `refunds->listInactive()` | `iterateInactive()` | 10 / 50 |
| `members->list()`, `invitations->list()` | `iterate()` | 20 / 100 |
| `webhooks->events->list()` (cursor `evt_…`) | `iterate()` | 20 / 100 |

The other lists are short and return a plain array: `products->list()`, `refunds->list()`,
`wallets->*`, `currencies->*`, `webhooks->endpoints->list()` (at most 10),
`members->listHeldFunds()` and `subscriptions->getPublicHistory()` (the 10 latest bills).

## Errors

Every exception the SDK throws extends `QBitFlow\Exceptions\QBitFlowException` (and implements
`QBitFlow\Exceptions\ExceptionInterface`); every error below extends `ApiException`:

| Exception | When | Codes and fields worth knowing |
|---|---|---|
| `ValidationException` | 400 `validation_failed`, or input the SDK refused **before sending** (`status` 0) | `fieldErrors`: each failing input by its wire name, dotted when nested (`frequency.unit`) |
| `BadRequestException` | any other 400 | `bad_request`, `foreign_key_violation` (`details['field']`) |
| `AuthenticationException` | 401 | a missing, unknown, expired or revoked key (a removed member's keys too) |
| `PermissionDeniedException` | 403 | `forbidden`, `policy_disabled` (`details['policy']`), `plan_required` |
| `NotFoundException` | 404 | unknown, deleted, or outside the request's space |
| `ConflictException` | 409 | `unique_violation` (`details['field']`), `tx_already_sent`, `merchant_not_ready` (`details['reason']`), `refund_already_exists` (`details['refundUuid']`), `held_funds_released`, `held_funds_pending`, `already_joined`, `payment_not_due`, `idempotency_key_in_use`, `conflict` |
| `GoneException` | 410 | `merchant_closed`: a removed member's or a closed organization's space |
| `IdempotencyException` | 422 `idempotency_key_reused` | an `Idempotency-Key` reused for another request: a bug, never retried |
| `RateLimitException` | 429 | `retryAfter` (seconds), `limit`, `periodSeconds` |
| `ServerException` | 5xx (503 `network_unavailable`, 504 `timeout`), an unexpected 3xx (redirects are never followed), a 2xx whose body is not the expected JSON | |
| `NetworkException` | no response: DNS, connection, TLS, timeout | `getPrevious()`: the PSR-18 client's exception |
| `WebhookSignatureException` | a webhook signature refused, locally or by `verifyRemote()` | `reason` |
| `ApiException` | any other HTTP error (e.g. 413 `request_too_large`) | |

Each carries `status` (`getStatus()`; `0` without a response), **`apiCode`** (`getApiCode()`:
branch on it, never on the message), `errorMessage`, `details` (an array, never null),
`requestId`, `fieldErrors` (`FieldError` with `field` and `message`) and `rawBody`. PHP's
`getCode()` is an integer, so it returns the HTTP status; the API's string code is `apiCode`.
`getMessage()` reads `"<message> (status <status>, code <code>, request <requestId>)"`, followed by
`"; <field>: <message>"` for each field error.

```php
use QBitFlow\Exceptions\ApiException;
use QBitFlow\Exceptions\ConflictException;
use QBitFlow\Exceptions\RateLimitException;
use QBitFlow\Exceptions\ValidationException;
use QBitFlow\Params\CreateCustomerParams;

try {
	$client->customers->create(new CreateCustomerParams(name: 'Ada', email: 'ada@example.com'));
	echo "created\n";
} catch (ValidationException $e) {
	foreach ($e->fieldErrors as $fieldError) {
		echo "{$fieldError->field}: {$fieldError->message}\n"; // show it next to the form field
	}
} catch (ConflictException $e) {
	echo 'taken: ', $e->details['field'] ?? '?', "\n"; // unique_violation: email or reference
} catch (RateLimitException $e) {
	echo "slow down, retry in {$e->retryAfter} s\n";
} catch (ApiException $e) {
	// Quote the request id to support.
	printf("QBitFlow error %d %s (request %s)\n", $e->status, $e->apiCode, $e->requestId);
}
```

**Client-side validation.** Each params class has a `validate()` method, run before every
request: names and texts (lengths in characters, no markup characters), references
(`A-Z a-z 0-9 . _ : @ -`, 1 to 100), emails, phone numbers, absolute `http(s)` URLs, prices above
0, percents (at most 2 decimals), durations (a frequency between 1 unit and 1 year), UUIDs and
transaction ids, dates, the checkout's product choice, and exclusive filters. A failure is a
`ValidationException` with `status` 0 and nothing is sent. What depends on the key's mode or on
stored data is left to the API: the 5 USD test-mode cap, `https`-only live URLs, the frequency
minimum (1 hour live, 5 minutes test), the 95-day export window, uniqueness.

## Retries and idempotency

| | |
|---|---|
| Retried methods | every read (GET), and the 7 creates: `checkoutSessions->createPayment()`, `checkoutSessions->createSubscription()`, `products->create()`, `customers->create()`, `webhooks->endpoints->create()`, `invitations->create()`, `refunds->initiate()` |
| Never retried | every other write: updates, deletes, `checkoutSessions->expire()`, `subscriptions->cancel()`, `subscriptions->executeTestBilling()`, `members->trust()`, `members->remove()`, `invitations->revoke()`, `webhooks->verifyRemote()` |
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

```php
use QBitFlow\Exceptions\IdempotencyException;
use QBitFlow\Params\CreatePaymentSessionParams;
use QBitFlow\RequestOptions;

$orderId = 'order-1044';
try {
	$session = $client->checkoutSessions->createPayment(
		new CreatePaymentSessionParams(productName: 'T-shirt', price: 4.99, reference: $orderId),
		new RequestOptions(
			idempotencyKey: 'checkout-' . $orderId, // the same key returns the same session
			requestId: 'job-7781',                  // sent as X-Request-Id, echoed in errors
		),
	);
	echo $session->link, "\n";
} catch (IdempotencyException) {
	echo "this key was already used with other params\n"; // 422 idempotency_key_reused
}
```

A key is 1 to 255 printable ASCII characters without spaces, and only successful answers are kept
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
| `apiKey` | required | non-blank, starting with `sk_` |
| `baseUrl` | `https://api.qbitflow.app/v2` (`QBitFlow::DEFAULT_BASE_URL`) | an absolute `http(s)` URL; a trailing `/` is stripped |
| `timeout` | 30.0 (`DEFAULT_TIMEOUT`) | seconds per attempt, positive; applied to the HTTP client the SDK builds (set it on your own client) |
| `maxRetries` | 3 (`DEFAULT_MAX_RETRIES`) | `0` disables retries; negative is refused |
| `onBehalfOf` | none | every request acts in that member's space |
| `httpClient`, `requestFactory`, `streamFactory` | auto-detected (Guzzle, Symfony, Nyholm, …) | your PSR-18 client and PSR-17 factories; the client must not follow redirects and should not throw on HTTP errors |

| `RequestOptions` (every method, last argument) | |
|---|---|
| `onBehalfOf` | acts in that member's space for this call; `''` forces the organization's |
| `idempotencyKey` | the 7 creates only: your own `Idempotency-Key` |
| `requestId` | sends `X-Request-Id` (1 to 128 of `A-Z a-z 0-9 - _ . :`) |

| Webhook arguments (`Webhook::verify()`, `verifyRequest()`, `constructEvent()`) | Default |
|---|---|
| `tolerance` (seconds; 0 or less keeps the default) | 300 (`Webhook::DEFAULT_TOLERANCE`) |
| `now` (`Closure(): int\|DateTimeInterface`) | `time()` |

A bad argument makes the constructor throw a `ValidationException`. A client's configuration never
changes; `$client->onBehalfOf()` derives clients that share it. Every request sends
`User-Agent: qbitflow-php/3.0.0` (`QBitFlow::VERSION`).

## Laravel

The package is auto-discovered: a singleton `QBitFlow` client (also the `QBitFlow` facade), the
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
	price: 19.99,
	reference: 'order-1042',
	successUrl: url('/orders/1042?session={{UUID}}'),
));
// Or inject QBitFlow\QBitFlow in your controllers and jobs.
```

**Webhooks.** One line in `routes/api.php` (or `routes/web.php`, excluded from CSRF protection):

```php
Route::qbitflowWebhooks('webhooks/qbitflow'); // POST, verified, answered 200
```

The middleware verifies the `QBitFlow-Signature` with `QBITFLOW_WEBHOOK_SECRET` (400 for a bad
signature or a v1 body); the controller then dispatches the Laravel event of the webhook's type,
followed by `WebhookReceived` for every delivery (unknown types included). Handle them in queued
listeners, deduplicating on `$event->event->id`:

| Webhook | Laravel event (`QBitFlow\Laravel\Events\…`) |
|---|---|
| `payment.completed` | `PaymentCompleted` |
| `subscription.created` / `.billed` / `.statusChanged` / `.actionRequiredChanged` / `.billingFailed` / `.upcomingBill` | `SubscriptionCreated`, `SubscriptionBilled`, `SubscriptionStatusChanged`, `SubscriptionActionRequiredChanged`, `SubscriptionBillingFailed`, `SubscriptionUpcomingBill` |
| `refund.requested` / `.completed` / `.denied` | `RefundRequested`, `RefundCompleted`, `RefundDenied` |
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
`$request->attributes->get('qbitflow.event')`. A PSR-18 client bound in the container
(`Psr\Http\Client\ClientInterface`) is used by the singleton client (proxies, logging, fakes in
tests).

## Migrating from 2.x

3.0.0 is a rewrite for API v2: base URL `/v2`, a new constructor with named arguments, typed
params classes, UUID ids, members and invitations instead of users and claims, a new webhook
signature and typed events, a new exception taxonomy, and a rebuilt Laravel integration.
**[MIGRATION-v3.md](MIGRATION-v3.md)** maps every 2.x method and class to its replacement, with
before/after code for the common tasks.

## Examples

Runnable scripts in [`examples/`](examples) (`QBITFLOW_API_KEY=sk_… php examples/<name>.php`):

| Example | Shows |
|---|---|
| [`checkout.php`](examples/checkout.php) | a payment checkout, its status, expiry |
| [`subscriptions.php`](examples/subscriptions.php) | a subscription checkout with a trial, past-due subscriptions, bills, cancel at period end |
| [`marketplace.php`](examples/marketplace.php) | invite a seller, sell `onBehalfOf`, held funds, trust |
| [`webhook-handler.php`](examples/webhook-handler.php) | a verified plain-PHP receiver with deduplication and typed events |
| [`errors-and-retries.php`](examples/errors-and-retries.php) | exception types, `isRetryable()`, idempotency keys across processes |
| [`laravel/`](examples/laravel) | the webhook route, a checkout controller with the facade, queued listeners |

## Testing

```bash
composer validate --strict && vendor/bin/phpunit
```

The `Unit` suite runs against a mock PSR-18 client: nothing touches the network. It includes the
cross-SDK conformance vectors when they are available (`QBITFLOW_VECTORS_DIR`). The live checks
are a separate suite and need an API key **and** an explicit base URL:

```bash
QBITFLOW_API_KEY=sk_… QBITFLOW_BASE_URL=https://… vendor/bin/phpunit --testsuite Integration
```

The read-only checks only read; the write checks also need `QBITFLOW_LIVE_WRITES=1` and a
test-mode key (or `QBITFLOW_ALLOW_LIVE_MODE_WRITES=1`). To test your own code, inject a fake
PSR-18 client: `new QBitFlow(apiKey: 'sk_test', httpClient: $fakeClient)`.

## License

This project is licensed under the MPL-2.0 License: see the [LICENSE](LICENSE) file. See also
[COMPLIANCE.md](COMPLIANCE.md) and the [trademark policy](TRADEMARKS.md).

## Support

- [Documentation](https://qbitflow.app/docs)
- [Email Support](mailto:support@qbitflow.app)
- [Issue Tracker](https://github.com/qbitflow/qbitflow-php-sdk/issues)

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for version history.

## Security

For security issues, please email security@qbitflow.app instead of using the issue tracker (see
[SECURITY.md](SECURITY.md)).
