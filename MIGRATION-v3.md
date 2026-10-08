# Migrating from 2.x to 3.0.0

3.0.0 is the PHP SDK for **QBitFlow API v2**. API v2 reorganises the platform around spaces
(an organization's, and one per member), invites people instead of provisioning them, names every
resource by a UUID and signs webhooks with a new scheme. The SDK follows: a new client
constructor, one service per API area, typed params classes, read-only models, a new exception
taxonomy, and a rebuilt Laravel integration.

API v1 keeps running next to v2, **against the same data**, for some weeks after v2 ships: your
2.x integration keeps working meanwhile, so you can move one part at a time. 2.x talks to `/v1`
only; 3.0.0 talks to `/v2` only.

This guide maps everything 2.1.0 exposed (the last 2.x release) to 3.0.0. The
[README](README.md) documents 3.0.0 in full; [CHANGELOG.md](CHANGELOG.md) lists every change.

## Checklist

1. [Install and requirements](#1-install-and-requirements): `qbitflow/qbitflow-php` `^3.0`, PHP 8.2.
2. [Base URL and API keys](#2-base-url-and-api-keys): `/v2`; `sk_…` keys; rotate pre-v2 keys.
3. [The client](#3-the-client): named arguments, checked at construction, `me()`.
4. [Request options](#4-request-options): `RequestOptions` as the last argument of every method.
5. [Method map](#5-method-map): every 2.x method and its replacement.
6. [Ids are UUID strings](#6-ids-are-uuid-strings): products, members, `onBehalfOf`.
7. [Users and claims become invitations, members and the trust layer](#7-users-and-claims-become-invitations-members-and-the-trust-layer).
8. [Models](#8-models): `Dto\*` becomes `Models\*`, params classes, enums as string constants.
9. [Enum values and statuses](#9-enum-values-and-statuses): subscription and checkout statuses.
10. [Exceptions](#10-exceptions): one class per condition, `apiCode`, field errors that work.
11. [Webhooks](#11-webhooks): `QBitFlow-Signature` over the raw body, typed events, v2 endpoints.
12. [Laravel](#12-laravel): one webhook route, one Laravel event per type, a webhook secret.
13. [Pagination](#13-pagination): `Page` and `iterate*()` generators.
14. [Before and after](#14-before-and-after-five-common-tasks): five common tasks.

## 1. Install and requirements

```bash
composer require qbitflow/qbitflow-php:^3.0
```

The package name and the root namespace (`QBitFlow\`) do not change. PHP **8.2** or later, a
PSR-18 client and PSR-17 factories (Guzzle, already in every Laravel application, covers both),
as before.

## 2. Base URL and API keys

- The default base URL is `https://api.qbitflow.app/v2` (`QBitFlow::DEFAULT_BASE_URL`). If you
  set one, it must point at v2.
- **The `QBITFLOW_BASE_URL` environment variable is no longer read by the client** (2.x's
  `Config::baseUrl()` fell back to it). Pass `baseUrl:` explicitly; the Laravel config still maps
  `QBITFLOW_BASE_URL` to `qbitflow.base_url`.
- API v2 issues keys shaped `sk_<uuid>_<test|live>_<secret>`. **Keys issued before**
  (`sk_<digits>_…`) **still authenticate**: rotate them in the dashboard when you move to v2.
  Treat a key as opaque.
- The constructor refuses a blank key, or one not starting with `sk_`, with a
  `ValidationException` (2.x sent it and got a 401). It sends nothing: call `$client->me()` to
  check the key online, and assert its mode (`$me->space->test`) at start-up.

## 3. The client

| 2.x | 3.0.0 |
|---|---|
| `new QBitFlow($apiKey, $baseUrl, $timeout, $maxRetries, $httpClient, $requestFactory, $streamFactory)` | `new QBitFlow(apiKey: …, baseUrl: null, timeout: null, maxRetries: null, onBehalfOf: null, httpClient: null, requestFactory: null, streamFactory: null)`: use named arguments (`onBehalfOf` now sits before `httpClient`) |
| `QBitFlow::fromArray([...])` | removed: use the constructor's named arguments |
| `Config::DEFAULT_BASE_URL`, `DEFAULT_TIMEOUT`, `DEFAULT_MAX_RETRIES` | `QBitFlow::DEFAULT_BASE_URL` (`/v2`), `QBitFlow::DEFAULT_TIMEOUT` (`30.0`), `QBitFlow::DEFAULT_MAX_RETRIES` (`3`); `Config` is gone |
| `$timeout` (the whole request) | `timeout:` seconds **per attempt** (applied to the HTTP client the SDK builds; an injected client keeps its own) |
| `$maxRetries` (GET only) | `maxRetries:` covers reads and the 7 idempotent creates; `0` disables retries; a negative value is refused |
| — | `onBehalfOf:` a member's userUuid, sent as `On-Behalf-Of` on every request (checked at construction) |
| `$client->onBehalfOf(int $userId)`, `$client->products->onBehalfOf(int $userId)` (each service) | `$client->onBehalfOf(string $userUuid)` returns a new client for every service (a non-UUID or the nil UUID is a `ValidationException` at once); `''` returns one at the organization level; or `new RequestOptions(onBehalfOf: …)` for one call |
| `$client->users->get()` | `$client->me()`: what the key is (role, space, mode) |
| `$client->getApiKey()` | removed |
| `$client->getBaseUrl()` | `$client->getBaseUrl()`; new `$client->getOnBehalfOf()` |
| `$client->oneTimePayments`, `->transactionStatus`, `->users`, `->apiKeys`, `->claims` | see § 5: `checkoutSessions`, `payments`, `failures`, `members`, `invitations`, `wallets` |
| Service accessor methods (`$client->products()`, …) | still there, for the Laravel facade; the properties read better in code |
| Redirects followed? Guzzle was configured not to | a 3xx is a `ServerException`; an injected client must not follow redirects either |

Reads and creates are now retried automatically (§ [Retries](README.md#retries-and-idempotency)):
drop any retry loop you wrapped around them, or set `maxRetries: 0`.

## 4. Request options

Every method takes an optional `QBitFlow\RequestOptions` as its last argument:

```php
$payment = $client->payments->get('pay@0192f1c2-2222-7c4d-9e5f-6a7b8c9d0e1f', null, new RequestOptions(
	onBehalfOf: '0192f1c2-7b3a-7c4d-9e5f-6a7b8c9d0e1f', // a member's space, this call only ('' = the organization's)
	requestId: 'support-ticket-981',                   // X-Request-Id, echoed in errors
));
echo $payment->amount, PHP_EOL;
```

`idempotencyKey:` sets the `Idempotency-Key` of one of the 7 creates (see the README); other
methods ignore it.

## 5. Method map

Every 3.0.0 method takes an optional `?RequestOptions $options` last (omitted below). Params are
the `QBitFlow\Params\…` classes, built with named arguments; `?…Params` may be `null` (or left
out) when every field is optional. 2.x accepted a DTO **or an array** for its create/update
methods; 3.0.0 takes the params class only.

### Checkout sessions and status (2.x `oneTimePayments`, `subscriptions`, `transactionStatus`)

| 2.x | 3.0.0 | Notes |
|---|---|---|
| `oneTimePayments->createSession(CreatePaymentSessionDto\|array)` | `checkoutSessions->createPayment(CreatePaymentSessionParams)` | `productId: int` → `productUuid: string`; `customerUUID` → `customerUuid`; new `expiresInMinutes`; returns a `Models\CheckoutSession` (`uuid`, `link`, `expiresAt`) |
| `subscriptions->createSession(CreateSubscriptionSessionDto\|array)` | `checkoutSessions->createSubscription(CreateSubscriptionSessionParams)` | `frequency`, `trialPeriod` (`Models\Duration`) and `minPeriods` are optional over a subscription product's terms |
| `transactionStatus->get($uuid, TransactionType $type)` | `checkoutSessions->getStatus($uuid)` | no type argument; four statuses (§ 9) |
| `oneTimePayments->getSession($uuid)`, `subscriptions->getSession($uuid)` | removed | `getStatus()` for the state; the session's data comes with `checkout.expired` |
| — | `checkoutSessions->expire($uuid)` | new: end an unpaid session |

### Payments and failures (2.x `oneTimePayments`)

| 2.x | 3.0.0 | Notes |
|---|---|---|
| `oneTimePayments->get($uuid)` | `payments->get($uuid, ?ReadParams)` | `pay@…` or the bare UUID; `ReadParams(includeMembers: true)` reads a member's payment from the organization |
| `oneTimePayments->getByReference($ref)` | `payments->getByReference($ref)` | the reference is sent escaped |
| `oneTimePayments->getAll($limit, $cursor)` | `payments->list(?PaymentListParams)`, `payments->iterate()` | filters: `customerUuid`, `productUuid`, `createdAfter` / `createdBefore`, `refunded`, `includeMembers` / `userUuid` |
| `oneTimePayments->getAllCombined($limit, $cursor)` | `payments->listCombined(?CombinedPaymentListParams)`, `payments->iterateCombined()` | + `source`, `subscriptionUuid` |
| `oneTimePayments->getCustomerForTransaction($txUuid)` | removed | `$payment->customer` (a `CustomerSummary`), or `customers->get($payment->customerUuid)` |
| — | `failures->list(?FailureListParams)`, `failures->iterate()` | new: the failed payment attempts |

### Subscriptions

| 2.x | 3.0.0 | Notes |
|---|---|---|
| `subscriptions->get($uuid)` | `subscriptions->get($uuid, ?ReadParams)` | cancelled subscriptions are returned too: a 404 no longer means "cancelled" |
| `subscriptions->getByReference($ref)` | `subscriptions->getByReference($ref)` | |
| `subscriptions->getPaymentHistory($uuid)` | `subscriptions->listBills($uuid, ?BillListParams)` / `iterateBills()`, or `getPublicHistory($uuid)` | `listBills` pages every full bill; `getPublicHistory` is the public route (10 latest, merchant-only fields empty) |
| `subscriptions->forceCancel($uuid)` (a GET, returned a `SuccessResponse`) | `subscriptions->cancel($uuid, ?CancelSubscriptionParams)` | a POST, never retried; returns a `SubscriptionCancellation` (`subscription`, `pending`: HTTP 202, still confirming on-chain); `immediate: false` cancels at the end of the paid period |
| `subscriptions->executeTestBilling($uuid)` (returned a `StatusLinkResponse`) | `subscriptions->executeTestBilling($uuid)` | a POST; returns the bill's `BillingState`; `409 payment_not_due` before `nextBillingDate` |
| — | `subscriptions->list()`, `iterate()`, `getBill($billUuid, ?ReadParams)` | new |

### Refunds

| 2.x | 3.0.0 | Notes |
|---|---|---|
| `refunds->getAll()` | `refunds->list(?RefundListParams)` | the refunds awaiting an answer; from the organization's space the members' are included by default (`includeMembers: false` leaves them out) |
| `refunds->getAllInactive($limit, $cursor)` | `refunds->listInactive(?RefundListParams)`, `refunds->iterateInactive()` | |
| `refunds->getByTransaction($txUuid)` | removed | `$payment->refund` / `$bill->refund` (a `RefundSummary`), or the lists |
| — | `refunds->initiate(InitiateRefundParams)` | new: a pending refund you sign in the dashboard |

### Customers and products

| 2.x | 3.0.0 | Notes |
|---|---|---|
| `customers->create(CreateCustomerDto\|array)` | `customers->create(CreateCustomerParams)` | `lastName` is optional now |
| `customers->get`, `getByEmail`, `getByReference` | same names | |
| `customers->delete($uuid)` (returned a `SuccessResponse`) | `customers->delete($uuid)`: `void` | |
| `customers->update($uuid, UpdateCustomerDto\|array)` | `customers->update($uuid, UpdateCustomerParams)` | `null` leaves a field unchanged; `phoneNumber: ''` and `address: ''` now **clear** them |
| `customers->getAll($limit, $cursor)` | `customers->list(?CustomerListParams)`, `customers->iterate()` | filters `email`, `verified` |
| `products->create(CreateProductDto\|array)` | `products->create(CreateProductParams)` | `description` optional; `subscription: new SubscriptionTermsParams(…)` makes a subscription product |
| `products->get(int $id)` | `products->get(string $uuid)` | |
| `products->getAll()` | `products->list(?ProductListParams)` | `includeHidden`, `subscription` |
| `products->getByReference($ref)` | `products->getByReference($ref)` | |
| `products->update(int $id, UpdateProductDto\|array)` | `products->update(string $uuid, UpdateProductParams)` | + `isActive`, `subscription`, `removeSubscription`; `description: ''` clears it |
| `products->delete(int $id)` (returned a `SuccessResponse`) | `products->delete(string $uuid)`: `void` | |

### Users, claims and API keys (→ § 7)

| 2.x | 3.0.0 | Notes |
|---|---|---|
| `users->create(CreateUserDto\|array)` | `invitations->create(CreateInvitationParams)` | not a rename: the person exists once they accept (`member.joined`) |
| `users->getAll()` | `members->list(?MemberListParams)`, `members->iterate()` | |
| `users->getById(int $id)` | `members->get($userUuid)` | |
| `users->getByEmail($email)` | removed | iterate `members` and match `$member->email` |
| `users->update(int $id, UpdateUserDto\|array)` | `members->update($userUuid, UpdateMemberParams)` | only the organization fee (`organizationFeePercent`): names and emails are the person's own |
| `users->delete(int $id)` | `members->remove($userUuid)`: `void` | ends the membership (`409 held_funds_pending` while you hold their funds) |
| `users->get()` | `$client->me()` | what the key is: its role, space and mode |
| `claims->createRequest($userId)`, `claims->getRequestByUser($userId)` | `invitations->create()`, `invitations->list()` | the invitation link replaces the claim link |
| `claims->getFunds()` | `members->listHeldFunds()`, `members->getHeldFunds($userUuid)`, `members->getOwnHeldFunds()` | |
| `claims->triggerTestClaimFunds($userId)` | removed | release held funds in the dashboard; `members->trust($userUuid)` pays new payments directly |
| `apiKeys->getAll()`, `apiKeys->getForUser($userId)` | removed | keys are managed in the dashboard; `$client->me()` describes the current one |
| — | `invitations->revoke()`, `invitations->iterate()`, `members->trust()` | new |

### Wallets, accounting, currencies

| 2.x | 3.0.0 | Notes |
|---|---|---|
| — | `wallets->list(?WalletListParams)`, `wallets->listForMember($userUuid)`, `wallets->listSupportedCurrencies(?SupportedCurrenciesParams)` | new |
| `accounting->export($from, $to, ExportFormat\|string $format)` | `accounting->exportJson($from, $to)` or `accounting->exportCsv($from, $to)` | dates `YYYY-MM-DD`, checked (and `from <= to`) before sending; the API allows 95 days at most |
| `accounting->exportJson($from, $to)` | `accounting->exportJson($from, $to)` | returns `list<Models\AccountingEvent>` |
| `accounting->exportCsv($from, $to)` | `accounting->exportCsv($from, $to)` | CSV text; errors are still typed exceptions |
| `currencies->getAllAvailable(bool $test)` | `currencies->listAvailable(new CurrencyListParams(test: $test))` | |
| `currencies->getAllMain(bool $test)` | `currencies->listMain(new CurrencyListParams(test: $test))` | |
| — | `currencies->get(int $id)` | new |

### Webhooks (→ § 11)

| 2.x | 3.0.0 | Notes |
|---|---|---|
| `WebhookVerifier::verify($secret, $timestamp, $signature, $payload, $maxAge, $now, $skipTimestampCheck)` | `Webhook::verify($rawBody, $signatureHeader, $secret, $tolerance = 300, ?Closure $now = null)` | `QBitFlow\Webhooks\Webhook`; throws a `WebhookSignatureException` (`reason`); the timestamp check can no longer be skipped |
| `WebhookVerifier::computeSignature($secret, $timestamp, $payload)`, `WebhookVerifier::canonicalJson()` | removed | the signature covers the raw body |
| `WebhookVerifier::extractHeaders($headers)` | removed | read `Webhook::SIGNATURE_HEADER` from the request, or `Webhook::verifyRequest($psr7Request, $secret)` |
| `webhooks->verify($payload, $signature, $timestamp): bool` | `webhooks->verifyRemote($endpointUuid, $rawBody, $signatureHeader): void` | returns on a valid signature, throws a `WebhookSignatureException` (`invalidSignature`) otherwise |
| — | `Webhook::constructEvent()`, `Webhook::parseEvent()` (also `$client->webhooks->verify()`, `constructEvent()`, `parseEvent()`) | verify and parse into a typed `Events\Event` |
| `HEADER_SIGNATURE`, `HEADER_TIMESTAMP`, `HEADER_WEBHOOK_ID`; `webhooks->signatureHeader()`, `timestampHeader()`, `webhookIdHeader()` | `Webhook::SIGNATURE_HEADER`, `EVENT_ID_HEADER`, `EVENT_TYPE_HEADER`, `VERSION_HEADER` | |
| `TEST_WEBHOOK_ID`, `webhooks->testWebhookId()`, `webhooks->isTestWebhook($id)` | `$event instanceof Events\WebhookTestEvent`; `$event->test` says test mode | the dashboard's test is a `webhook.test` event, signed like any other |
| `DEFAULT_MAX_TIMESTAMP_AGE_SECONDS` | `Webhook::DEFAULT_TOLERANCE` | |
| `Dto\Session\SessionWebhookResponse`, `Dto\SubscriptionStatusTransition` | `Events\Event` and its subclasses | § 11 |
| — | `webhooks->endpoints->…`, `webhooks->events->…` | new: endpoints and the event log |

### Helpers

| 2.x | 3.0.0 |
|---|---|
| `Support\CursorData` (`items`, `nextCursor`, `hasMore()`, `count()`, iteration) | `QBitFlow\Page` (`items`, `nextCursor`, `hasMore()`); iterate `$page->items`, or use the `iterate*()` methods |
| `CursorData::queryParams($limit, $cursor)` | removed: `limit:` and `cursor:` of the list params |
| `Support\Duration` (`new Duration(1, DurationUnit::MONTHS)`, `Duration::months(1)`, …) | `Models\Duration` (`new Duration(1, DurationUnit::MONTHS)`, `Duration::months(1)`, …); `value` 0 is allowed (no trial) and the unit is a string |
| `Support\Validate::productText()`, `Validate::redirectUrl()` | removed: each params class has a `validate()` method, run before every request |
| `Support\Dto` (`toArray()`, `jsonSerialize()`) | params classes have `toArray()` (the wire body); models are read-only values built by `fromArray()` |

## 6. Ids are UUID strings

Every id but a currency's is a UUID string now, and no resource names its organization:

- **Products:** `Product::$id` (int) → `Product::$uuid` (string); `productId` → `productUuid` on
  payments, bills, subscriptions, sessions and the accounting export; checkouts take
  `productUuid:`.
- **Members and `onBehalfOf`:** a member is named by their **user UUID** (`$member->userUuid`).
  `onBehalfOf(123)` becomes `onBehalfOf('0192f1c2-…')`; an integer is a `TypeError`, a non-UUID
  string a `ValidationException`. `userId` → `userUuid` everywhere (`null` for the organization's
  own rows).
- **`organizationId` is gone** from every model; `$client->me()->space->organizationUuid` names
  the organization.
- **Transactions** keep their prefixed ids (`pay@…`, `sub@…`, `sub-hist@…`, `refund@…`), as
  opaque strings. `txId` → `txUuid`, `transactionHash` → `txHash`.
- **Property names are the JSON keys**, lowerCamelCase with acronyms as words: `customerUUID` →
  `customerUuid`, `subscriptionUUID` → `subscriptionUuid`, `TxAmountsUSD` → `TxAmountsUsd`.

**Map the numeric ids you stored** before v1 is turned off: list your products
(`$client->products->list(new ProductListParams(includeHidden: true))`, in each space you act in)
and match them on `reference`, which did not change, and your members (`members->iterate()`, in
each mode) on `email`; store the UUIDs next to your old ids. The SDK does not expose the API's
temporary `legacyId` field.

## 7. Users and claims become invitations, members and the trust layer

v1 provisioned sellers (`users->create()`) and could sell for them at once, the seller claiming
the account and its funds later. **v2 creates no accounts: a seller sells only once they accepted
an invitation.**

1. `$client->invitations->create(new CreateInvitationParams(email: …, trustLayer: true, organizationFeePercent: 5.0, redirectUrl: …))`
   returns the invitation and its link (also emailed).
2. Wait for the `member.joined` webhook and store its `$event->data->userUuid` (match
   `invitationUuid`).
3. Sell with `$client->onBehalfOf($userUuid)`.

`trustLayer: true` (the v1 "claim" model) holds the seller's payments in your wallet until you
trust them: `members->getHeldFunds()` shows what you owe, `members->trust()` makes new payments go
to the seller directly, and the release of what is held is signed in the dashboard. Sellers v1
provisioned are already members; those who never claimed are members whose funds you hold
(`$member->trustedAt === null`). The [README](README.md#marketplaces) walks the flow.

## 8. Models

Fee rates are **percents** now, everywhere: `feeBps: 150` → `feePercent: 1.5`,
`organizationFeeBps: 250` → `organizationFeePercent: 2.5`.

**Requests** take `QBitFlow\Params\…` classes (`final readonly`, built with named arguments,
`validate()` and `toArray()`). **Responses** are `QBitFlow\Models\…` classes: `readonly`, their
properties named exactly as the JSON keys, built by `Model::fromArray()`.

| 2.x (`QBitFlow\Dto\…`) | 3.0.0 (`QBitFlow\Models\…`) | What changed |
|---|---|---|
| `Session\CreatePaymentSessionDto`, `Session\CreateSubscriptionSessionDto` | `Params\CreatePaymentSessionParams`, `Params\CreateSubscriptionSessionParams` | `productId` → `productUuid`, `customerUUID` → `customerUuid`; + `expiresInMinutes` |
| `CreateProductDto`, `UpdateProductDto`, `CreateCustomerDto`, `UpdateCustomerDto` | `Params\CreateProductParams`, `UpdateProductParams`, `CreateCustomerParams`, `UpdateCustomerParams` | § 5 |
| `CreateUserDto`, `UpdateUserDto` | `Params\CreateInvitationParams`, `Params\UpdateMemberParams` | § 7 |
| `Session\LinkResponse` | `CheckoutSession` | + `expiresAt` |
| `TransactionStatus` | `CheckoutSessionStatus` | `status` is a string (§ 9); `settlementDetails` removed; + `uuid`, `lastAttempt` |
| `Session\SessionCheckout`, `OneTimePaymentSession`, `SubscriptionSession`, `PaygSubscriptionSession`, `StatusLinkResponse` | removed | `PaymentSessionData` / `SubscriptionSessionData` in `checkout.expired` |
| `Payment` | `Payment` | `transactionHash` → `txHash`; `productId` → `productUuid`; `userId` → `userUuid`; `metadata` always set; + `chain`, `explorerUrl`, `customerReference`, `customer`, `refund`, `refundable`, `paidMinUnits`, `paidUsd`, `confirmedAt` |
| `CombinedPayment` | `CombinedPayment` | `source` `subscription_history` → `subscriptionHistory`; + `currency`, `refund`, `reference`, `subscriptionReference` |
| `Subscription` | `Subscription` | `subscriptionStatus` → `status` (a string); `frequency` is a `Duration` (was an `int` of seconds); `stopped` → the status `stopped`; `productId` → `productUuid`; `nextBillingDate` nullable (null once cancelled); + `currentPeriodEnd`, `actionRequired`, `priceUsd`, `maxAmountPerPeriod`, `cancelledAt`, `cancellationReason`, `customer`, `dunning` |
| `SubscriptionHistory` | `Bill` | the `Payment` renames; + `periodStart`, `periodEnd` |
| `RefundEntry` | `Refund` | `txId` → `txUuid`; + `initiatedBy`, `refundPercent`, `paidMinUnits`, `paidUsd`, `amountUsd`, `currencyId`, `held`, `chain`, `explorerUrl`, `customer` |
| `Customer` | `Customer` | + `verified` |
| `Product` | `Product` | `id` → `uuid`; + `subscription`, `paymentLink` |
| `User` | `Member` (or `Me`) | `id` → `userUuid`; `organizationFeeBps` → `organizationFeePercent`; `claimedAt` → `trustedAt`; `role` moves to `Me`; + `joinedAt`, `acceptedCurrencyIds`, `spaceUuid` |
| `AccountingEvent` | `AccountingEvent` | `paymentId` → `paymentUuid`; `relatedPaymentId` → `relatedPaymentUuid`; `productId` → `productUuid`; + `userUuid`, member and customer names |
| `Metadata\PaymentMetadata`, `OrganizationFee`, `ReferralFee`, `TxMetadata`, `BlockData`, `NetworkFees`, `TxAmountsMinUnits` | `PaymentMetadata`, `OrganizationFee`, `ReferralFee`, `TxMetadata`, `BlockData`, `NetworkFees`, `TxAmountsMinUnits` | `feeBps` → `feePercent`; `organizationId`, `referralId` removed |
| `Metadata\TxAmountsFull`, `Metadata\TxAmountsUSD` | `TxAmounts`, `TxAmountsUsd` | |
| `Currency` | `Currency` | `address` is a string (`''` for a native coin) |
| `Support\CursorData` | `QBitFlow\Page` | § 13 |
| `ApiKey`, `Organization`, `ClaimFunds`, `ClaimRequestResponse`, `StatusResponse`, `StatusResponseError`, `SuccessResponse`, `SubscriptionStatusTransition` | removed | |

**Typing rule.** A field the API always sends is non-nullable (an absent or `null` value gives
its zero value: `''`, `0`, `false`, `[]`, a zero-valued nested model); an optional or nullable
field is `?T`, `null` when absent. An unset required timestamp is Go's zero time
(`0001-01-01T00:00:00Z`): test it with `QBitFlow\Support\Time::isZero()`. Timestamps are
`DateTimeImmutable` with the offset the API sent; amounts in a token's smallest unit, and a few
USD prices (`priceUsd`), are decimal strings, never rounded.

**Enums** were PHP backed `enum`s (`SubscriptionStatus::ACTIVE->value`), and a value the SDK did
not know was kept as a string beside them (`Support\Enums::value()` / `is()` / `isUnknown()`
read the union). In 3.0.0 every enum property is a plain `string`, and
`QBitFlow\Enums\SubscriptionStatus` (and the others) are classes of string constants: compare
with `===` against `SubscriptionStatus::ACTIVE`. Unknown values are kept as is. `Support\Enums`
is gone.

## 9. Enum values and statuses

**Subscription statuses** (`Enums\SubscriptionStatus`)

| 2.x | 3.0.0 |
|---|---|
| `ACTIVE` (`active`) | `ACTIVE` (`active`) |
| `PAST_DUE` (`past_due`) | `PAST_DUE` (`pastDue`) |
| `TRIAL` (`trial`) | `TRIAL` (`trial`) |
| `TRIAL_EXPIRED` (`trial_expired`) | `TRIAL_EXPIRED` (`trialExpired`) |
| `CANCELLED` (`cancelled`) | `CANCELLED` (`cancelled`) |
| `LOW_ON_FUNDS` (`low_on_funds`) | no status: `$subscription->actionRequired === ActionRequired::TOP_UP_ALLOWANCE` |
| `PENDING` (`pending`) | no status: `$subscription->actionRequired === ActionRequired::RAISE_MAXIMUM` |
| `$subscription->stopped === true` | `STOPPED` (`stopped`): cancelled at `nextBillingDate` |
| — | `PAUSED` (`paused`): paused by the customer |

**Grant access while now < `currentPeriodEnd`**, whatever the status: a check like
`status === 'active'` cuts off paying customers (`stopped`, `paused`) and keeps serving `pastDue`
ones.

**Checkout statuses**: 2.x's seven `TransactionStatusValue` cases become four
`Enums\CheckoutSessionStatusValue` constants: `CREATED`, `WAITING_CONFIRMATION`, `COMPLETED`,
`EXPIRED`. `PENDING`, `FAILED` and `CANCELLED` are gone: **a failed attempt is `created` with
`lastAttempt` set** (its `code` says why). It is never final: the customer may still pay until
the session expires, so never cancel an order on it.

**Other values**

| 2.x | 3.0.0 |
|---|---|
| `TransactionType::ONE_TIME_PAYMENT` | `TransactionType::PAYMENT` (`payment`) |
| `TransactionType::EXECUTE_SUBSCRIPTION_PAYMENT` | `TransactionType::EXECUTE_SUBSCRIPTION` |
| `TransactionType::CREATE_PAYG_SUBSCRIPTION`, `CANCEL_PAYG_SUBSCRIPTION` (`createPAYGSubscription`…) | `CREATE_PAYG_SUBSCRIPTION`, `CANCEL_PAYG_SUBSCRIPTION` (`createPaygSubscription`…) |
| — | `TransactionType::FORCE_CANCEL_SUBSCRIPTION`, `RELEASE_HELD_FUNDS` |
| `RefundStatus::REFUSED` (`refused`), `RefundStatus::FAILED` | `RefundStatus::REJECTED` (`rejected`); `FAILED` removed |
| `CombinedPaymentSource::SUBSCRIPTION_HISTORY` (`subscription_history`) | `CombinedPaymentSource::SUBSCRIPTION_HISTORY` (`subscriptionHistory`) |
| `UserRole` (`ADMIN`, `USER`, `OWNER`) | `Role` (`OWNER`, `ADMIN`, `USER`, `HANDLE`) |
| `ExportFormat`, `SubscriptionWebhookType`, `TransactionShortType`, `TransactionStatusValue` | removed |

## 10. Exceptions

2.x threw `QBitFlowException` or one of `ValidationException`, `UnauthorizedException`,
`ForbiddenException`, `NotFoundException`, `RateLimitException`, `ServerException`,
`NetworkException` (the 400 and 422 statuses both as `ValidationException`). 3.0.0 has one class
per condition, all under `QBitFlow\Exceptions\ApiException` (itself a `QBitFlowException`, which
implements the marker `ExceptionInterface`):

| Status | 3.0.0 class |
|---|---|
| 400 `validation_failed`, and client-side checks (`status` 0) | `ValidationException` |
| other 400 | `BadRequestException` (2.x: `ValidationException`) |
| 401 | `AuthenticationException` (2.x: `UnauthorizedException`) |
| 403 | `PermissionDeniedException` (2.x: `ForbiddenException`) |
| 404 | `NotFoundException` |
| 409 | `ConflictException` |
| 410 | `GoneException` |
| 422 `idempotency_key_reused` | `IdempotencyException` (2.x: `ValidationException`) |
| 429 | `RateLimitException` (`retryAfter`, `limit`, `periodSeconds`) |
| 5xx, unexpected 3xx, unusable 2xx | `ServerException` |
| no response | `NetworkException` (`getPrevious()`: the PSR-18 exception) |
| bad webhook signature | `WebhookSignatureException` (`reason`) |
| any other HTTP error (413, 405…) | `ApiException` |

| 2.x | 3.0.0 |
|---|---|
| `$e->getStatusCode()` (`?int`) | `$e->status` / `$e->getStatus()` (`0` without a response); `getCode()` is the status too |
| — | `$e->apiCode` / `$e->getApiCode()`: the API's string `code` (`unique_violation`…). `Exception::getCode()` is an integer in PHP, hence the name |
| `$e->getMessage()` | `"<message> (status <status>, code <code>, request <requestId>)"` followed by `"; <field>: <message>"`; the bare message is `$e->errorMessage` |
| `$e->getFields()` (`list<FieldError>`) | `$e->fieldErrors` / `getFieldErrors()` (`FieldError` with `field` and `message`) |
| `$e->getResponse()` (the decoded body) | `$e->details` (the body's `details` object, never null) and `$e->rawBody` (the body as received) |
| — | `$e->requestId`: quote it to support |
| `RateLimitException::getRetryAfter()` (`?int`) | `$e->retryAfter` / `getRetryAfter()` (`int` seconds, `0` when unknown) |
| — | `$e->isRetryable()` |

- **Field errors work now.** 2.x read a validation error's fields from the wrong key (a
  top-level `errors` list), so every field came back empty. 3.0.0 reads `details.errors`:
  `fieldErrors` names each failing input by its wire name, dotted when nested
  (`frequency.unit`).
- **Branch on `apiCode`**, never on the message: `unique_violation` (`details['field']`),
  `merchant_not_ready`, `tx_already_sent`, `policy_disabled`…

## 11. Webhooks

The webhook scheme is new; 2.x verification code does not carry over.

| | 2.x | 3.0.0 |
|---|---|---|
| Header | `X-Webhook-Signature-256`, `X-Webhook-Timestamp`, `X-Webhook-ID` | `QBitFlow-Signature: t=<unix>,v1=<hex>[,v1=<hex>]`, `QBitFlow-Event-Id`, `QBitFlow-Event-Type` |
| Signed content | `timestamp.` + the body re-encoded as canonical JSON | `t.` + the **raw body**, exactly as received |
| Rotation | one secret | two `v1=` for 24 hours after a rotation; either secret verifies |
| Body | `SessionWebhookResponse` or a subscription transition | the event envelope `{id, type, version, createdAt, test, userUuid, data}` |
| Dashboard test | `X-Webhook-ID: test-webhook-id` | a `webhook.test` event |

- **Pass the raw body** (`file_get_contents('php://input')`, `$request->getContent()`), never a
  decoded array: `Webhook::verify()` takes a `string`.
- **Endpoints must be on payload version v2.** v1 webhook URLs were migrated as endpoints with
  `payloadVersion` `v1`, which still receive v1's bodies; 3.0.0 does not parse them
  (`parseEvent()` throws a `ValidationException`). Move each endpoint to v2 in the dashboard, or
  with `$client->webhooks->endpoints->update($uuid, new UpdateWebhookEndpointParams(payloadVersion: WebhookPayloadVersion::V2))`,
  when your receiver runs 3.0.0. Moving keeps the endpoint's secret.
- **Event types** replace the two 2.x payloads: v1's transaction webhook becomes
  `payment.completed` / `subscription.created`, its subscription webhook `subscription.billed` /
  `subscription.statusChanged`, plus 11 new types. `Webhook::parseEvent()` returns the subclass
  of the type (`Events\PaymentCompletedEvent`, …, `Events\UnknownEvent`) with a typed `data`.
- Deliveries are at least once: deduplicate on `$event->id`, and answer 2xx to the types you
  ignore.

## 12. Laravel

| 2.x | 3.0.0 |
|---|---|
| `Route::qbitflowTransactionWebhook($uri)`, `Route::qbitflowSubscriptionWebhook($uri)` (two URLs) | `Route::qbitflowWebhooks('webhooks/qbitflow')`: one route for every event type (name `qbitflow.webhooks`) |
| `WebhookController::transaction()`, `::subscription()` | `WebhookController` is invokable: it dispatches the event of the type, then `WebhookReceived` |
| `Events\TransactionWebhookReceived` (`payload`, `reference()`, `isSubscription()`) | `Events\PaymentCompleted` and `Events\SubscriptionCreated` (each with `event` and typed `data`) |
| `Events\SubscriptionBilled` (`billing`, `subscriptionUUID`, `reference()`) | `Events\SubscriptionBilled` (`event`, `data`: a `Models\SubscriptionBilled` with `subscriptionUuid`, `subscriptionReference`, `periodEnd`) |
| `Events\SubscriptionStatusChanged` (`transition`, `subscriptionUUID`, `reference()`) | `Events\SubscriptionStatusChanged` (`event`, `data`: the `Subscription` + `previousStatus`) |
| — | `SubscriptionActionRequiredChanged`, `SubscriptionBillingFailed`, `SubscriptionUpcomingBill`, `RefundRequested`, `RefundCompleted`, `RefundDenied`, `MemberJoined`, `MemberRemoved`, `HeldFundsReleased`, `CheckoutExpired`, `WebhookTestReceived`, and `WebhookReceived` for every delivery (unknown types included) |
| The middleware answered the dashboard probe itself (`isTestWebhook`) | the probe is verified like any delivery and dispatched as `WebhookTestReceived` |
| `VerifyQBitFlowWebhook` verified through the API (`webhooks->verify`) | it verifies locally with `QBITFLOW_WEBHOOK_SECRET` (`config('qbitflow.webhook_secret')`, required: a missing secret is a 500) and stores the event on `$request->attributes->get('qbitflow.event')`; a bad signature or a v1 body is a 400 |
| config `api_key`, `base_url`, `timeout`, `max_retries` | the same, plus `webhook_secret` (`QBITFLOW_WEBHOOK_SECRET`) and `webhook_tolerance` (`QBITFLOW_WEBHOOK_TOLERANCE`, 300) |
| `qbitflow:verify` (called `GET /user`, printed the user and the fee in bps) | calls `me()`: credential, role, organization, space, mode, member |
| `qbitflow:install` | also adds `QBITFLOW_WEBHOOK_SECRET` to `.env` and points to `Route::qbitflowWebhooks()` |
| Facade `QBitFlow::oneTimePayments()`, `users()`, `apiKeys()`, `claims()`, `transactionStatus()`, `getApiKey()` | `QBitFlow::checkoutSessions()`, `payments()`, `failures()`, `members()`, `invitations()`, `wallets()`, `me()`, `onBehalfOf()` |

## 13. Pagination

`getAll…(?int $limit, ?string $cursor)` returning a `CursorData` becomes
`list(new …ListParams(limit: …, cursor: …, filters…))` returning a `QBitFlow\Page` (`items`,
`nextCursor`, `hasMore()`). Pass `$page->nextCursor` back as `cursor:`, verbatim. Every paginated
list also has an `iterate*()` twin returning a `Generator` that walks every page lazily:

```php
foreach ($client->subscriptions->iterate(new SubscriptionListParams(limit: 100)) as $subscription) {
	echo $subscription->uuid, ' ', $subscription->status, PHP_EOL;
}
```

## 14. Before and after: five common tasks

### Create the client

```php
// 2.x
$client = new QBitFlow(getenv('QBITFLOW_API_KEY'), 'https://api.qbitflow.app/v1', 10.0);
$user = $client->users->get();
```

```php
$client = new QBitFlow(
	apiKey: (string) getenv('QBITFLOW_API_KEY'),
	timeout: 10.0, // per attempt; the base URL defaults to …/v2
);
$me = $client->me(); // check the key at start-up
echo $me->role, ' ', ($me->space?->test ?? false) ? 'test' : 'live', PHP_EOL;
```

### Open a payment checkout and read its outcome

```php
// 2.x
$session = $client->oneTimePayments->createSession(new CreatePaymentSessionDto(
	reference: 'order-1042',
	productId: 42,
	successUrl: 'https://shop.example.com/thanks',
));
$status = $client->transactionStatus->get($session->uuid, TransactionType::ONE_TIME_PAYMENT);
if ($status->status === TransactionStatusValue::COMPLETED) {
	$payment = $client->oneTimePayments->get($session->uuid);
}
```

```php
$session = $client->checkoutSessions->createPayment(new CreatePaymentSessionParams(
	productUuid: '0192f1c2-1111-7c4d-9e5f-6a7b8c9d0e1f',
	reference: 'order-1042',
	successUrl: 'https://shop.example.com/thanks?session={{UUID}}',
));
$status = $client->checkoutSessions->getStatus($session->uuid);
if ($status->status === CheckoutSessionStatusValue::COMPLETED) {
	$payment = $client->payments->get($session->uuid); // the payment has the session's id
	echo $payment->txHash, PHP_EOL;
}
```

### List a seller's payments

```php
// 2.x
$page = $client->oneTimePayments->onBehalfOf(123)->getAll(50);
while ($page->hasMore()) {
	$page = $client->oneTimePayments->onBehalfOf(123)->getAll(50, $page->nextCursor);
}
```

```php
$seller = $client->onBehalfOf('0192f1c2-7b3a-7c4d-9e5f-6a7b8c9d0e1f'); // $member->userUuid
foreach ($seller->payments->iterate(new PaymentListParams(limit: 50)) as $payment) {
	echo $payment->uuid, ' ', $payment->amount, PHP_EOL;
}
```

### Cancel a subscription

```php
// 2.x
$response = $client->subscriptions->forceCancel('sub@0192f1c2-3333-7c4d-9e5f-6a7b8c9d0e1f');
```

```php
$result = $client->subscriptions->cancel('sub@0192f1c2-3333-7c4d-9e5f-6a7b8c9d0e1f'); // immediate
echo $result->subscription->status, ' ', $result->pending ? 'confirming (HTTP 202)' : 'done', PHP_EOL;
```

### Receive a webhook

```php
// 2.x
$payload = file_get_contents('php://input');
WebhookVerifier::verify(
	getenv('QBITFLOW_WEBHOOK_SECRET'),
	$_SERVER['HTTP_X_WEBHOOK_TIMESTAMP'] ?? '',
	$_SERVER['HTTP_X_WEBHOOK_SIGNATURE_256'] ?? '',
	$payload,
);
$event = SessionWebhookResponse::fromArray(json_decode($payload, true));
```

```php
try {
	$event = Webhook::constructEvent(
		(string) file_get_contents('php://input'),
		$_SERVER['HTTP_QBITFLOW_SIGNATURE'] ?? '',
		(string) getenv('QBITFLOW_WEBHOOK_SECRET'),
	);
} catch (WebhookSignatureException | ValidationException $e) {
	http_response_code(400);
	exit;
}
if ($event instanceof PaymentCompletedEvent) { // deduplicate on $event->id first
	error_log('fulfil ' . $event->data->reference);
}
http_response_code(200); // to every type, the ignored ones too
```

### Handle an error

```php
// 2.x
try {
	$client->products->get(42);
} catch (NotFoundException $e) {
	echo 'not found: ', $e->getMessage();
} catch (ValidationException $e) {
	print_r($e->getFields()); // empty: the field details were lost
} catch (QBitFlowException $e) {
	echo 'error ', $e->getStatusCode();
}
```

```php
try {
	$client->products->get('0192f1c2-1111-7c4d-9e5f-6a7b8c9d0e1f');
} catch (NotFoundException $e) {
	echo 'not found, request ', $e->requestId, PHP_EOL;
} catch (ValidationException $e) {
	foreach ($e->fieldErrors as $fieldError) {
		echo $fieldError->field, ': ', $fieldError->message, PHP_EOL;
	}
} catch (ApiException $e) {
	echo 'error ', $e->status, ' ', $e->apiCode, PHP_EOL;
}
```

## Names from the unreleased 2.5.0

2.5.0 was prepared but never published; its changes are part of 3.0.0. If you built against that
branch: `QBitFlow::onBehalfOf(int $userId)` and the per-service `onBehalfOf()` →
`$client->onBehalfOf(string $userUuid)`; `QBitFlow::fromArray()` → the constructor's named
arguments; `ConflictException` and `FieldError` keep their names (`FieldError` is now a
`final readonly` class, and `getFields()` → `fieldErrors`); `ForbiddenException` →
`PermissionDeniedException`; `UnauthorizedException` → `AuthenticationException`;
`RateLimitException::getRetryAfter()` (`?int`) → `int` seconds, `0` when unknown;
`Support\Enums::value()` / `is()` / `isUnknown()` → plain `string` properties compared with the
`Enums\…` constants; `Support\Time` is kept; `Webhooks\CanonicalJson` and the canonical-JSON
`WebhookVerifier` → `Webhooks\Webhook`; `Dto\SubscriptionWebhook` → the `subscription.*` events;
`RefundStatus::REFUSED` → `RefundStatus::REJECTED` (`rejected`, the value the API sends); the
middleware's local verification with `QBITFLOW_WEBHOOK_SECRET` is now the only one (no API
fallback); `Config` (and its `QBITFLOW_BASE_URL` fallback) → the `QBitFlow::DEFAULT_*` constants
and an explicit `baseUrl:`.
