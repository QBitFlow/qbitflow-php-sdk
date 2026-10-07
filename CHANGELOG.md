# Changelog

All notable changes to this project will be documented in this file.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this
project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [2.5.0] - 2026-09-23

Aligns the SDK with docs revision `5e7d5a5` and with the behaviour of the live API, and
brings the Go, JavaScript, Python and PHP SDKs to one contract: the same response types and
decoding policy, one retry policy, one error taxonomy, one client-side validation rule set,
and byte-identical webhook canonicalisation.

The headline fixes: **local webhook verification rejected genuine webhooks** whose payload
carried a decimal amount of 19 or more digits (any payment of 1 ETH or more, or of a few
dollars in an 18-decimal token), and **every `SubscriptionStatusChanged` event arrived
without a subscription UUID**.

> **⚠️ Breaking changes in a minor release.** They are listed under **Removed** and
> **Changed (breaking)** below. Semver-aware resolvers treat `2.5.0` as a safe upgrade
> from any `2.x`, so `^2` / `~2.1` constraints will pick it up automatically — review
> before updating, or pin.

See **[Upgrading from 2.1](#upgrading-from-21)** at the end of this entry for a line-by-line
migration guide.

### Removed

-   **`PaygSubscriptionSession`** and its arm of `SessionCheckout::discriminate()`. The API
    has no pay-as-you-go routes, so the type could never be produced. The PAYG cases of
    `TransactionType` and `TransactionShortType` are kept — a transaction can still carry
    them.
-   **`LinkResponse::$expiresAt`** — the API's `LinkResponse` is exactly `{link, uuid}`.
-   **`StatusLinkResponse`, `StatusResponse`, `StatusResponseError`** — types with no route
    behind them. `executeTestBilling()` now returns a `SuccessResponse` (the API answers a
    plain `{message}`).
-   **`CombinedPayment::$organizationId` and `$userId`** — the combined feed does not carry
    them.
-   **`SubscriptionStatusTransition::$subscriptionUUID` / `$subscriptionReference`** — the
    identity lives on the webhook envelope, not in `data` (see the fix below).

### Changed (breaking)

-   **Response types follow the API's Go models exactly.** A field is nullable only when the
    API can send `null` for it (a Go pointer); everything else is non-nullable:
    -   Fields the API always sends are required — including the organization/user
        identity and fee fields the older docs marked "authenticated only", which the API
        always returns to an API key (public routes honour credentials too):
        `organizationId`/`userId` on `Payment`, `Subscription`, `SubscriptionHistory`,
        `RefundEntry` and `Customer::$organizationId`; `metadata` on `Payment` and
        `SubscriptionHistory`; `organizationId`/`feeBps` on sessions.
    -   Optional fields the API omits when empty read as their zero value, never `null`:
        `Customer::$phoneNumber`/`$address`/`$reference` and `$userId`,
        `Payment::$productId`, `TransactionStatus::$message`, `RefundEntry::$merchantMessage`
        and `$txHash`, `TxMetadata::$mainCurrencyPriceUSD`,
        `TxAmountsUSD::$organization`/`$referral`, every optional session field
        (`reference`, `productId`, `productReference`, `successUrl`, `cancelUrl`,
        `organizationFeeBps`, `userId`, `userName`, `customerReference`, `trialPeriod`,
        `minPeriods`), `AccountingEvent::$networkFeesUsd`/`$networkFees`.
    -   `Product::$reference`, `Currency::$address`, every `amountMinUnits`,
        `ReferralFee::$deadline` and `Subscription::$lastBillingDate` are always set.
    -   **Nullable** (Go pointers): `customerUUID` on `Payment`, `Subscription`,
        `SubscriptionHistory` and sessions (it was a non-null string); `Payment::$reference`,
        `Subscription::$reference`, `Subscription::$minimumCancellationDate`,
        `SessionWebhookResponse::$status`, `TransactionStatus::$settlementDetails`,
        `RefundEntry::$respondedAt`/`$metadata`, `CombinedPayment::$productId`/
        `$subscriptionUUID`/`$metadata`, `User::$claimedAt`, `ApiKey::$expiresAt`,
        `Currency::$mainCurrencyId`/`$mainCurrency`, `PaymentMetadata::$organizationFee`/
        `$referralFee`.
    -   **`currency` is a required `Currency` object** on `Payment`, `CombinedPayment`,
        `Subscription` and `SubscriptionHistory`: the API sends the expanded currency on all
        four.
    -   Constructor parameters were reordered so required ones come first — only code that
        constructs these DTOs positionally is affected.
-   **One decoding policy, shared by all four SDKs.** A field that is absent or `null` where
    the type is non-nullable decodes to its zero value (`''`, `0`, `0.0`, `false`, `[]`, a
    zero-valued nested object, or Go's zero time `0001-01-01T00:00:00Z` for a timestamp) —
    never an error, and never a 1970 date. A field of the wrong JSON type (a string where a
    number belongs, a list where an object belongs, an unparseable timestamp) raises a
    `ServerException` carrying the HTTP status, instead of being silently coerced. An
    integer beyond PHP's range is reported the same way instead of being rounded (bodies are
    decoded with `JSON_BIGINT_AS_STRING`). `null` lists read as `[]`; a paginated body that
    is not `{items, nextCursor}` is a `ServerException` instead of an empty page. A timestamp
    the API always sends is never `null`: test for "never set" with the new `Support\Time`.
-   **Unknown enum values are preserved, not guessed.** Response fields backed by an enum are
    typed `<Enum>|string`: a known value hydrates to the member, an unknown one arrives as
    the raw string, an absent one as `''`. (Previously an unknown subscription status read
    as `ACTIVE` and an unknown accounting row as a `PAYMENT`.) Applies to
    `Subscription::$subscriptionStatus`, `SubscriptionStatusTransition`,
    `TransactionStatus::$status`, `RefundEntry::$status`, `User::$role`, `ApiKey::$role`,
    `CombinedPayment::$source`, `AccountingEvent::$type` (both documented spellings of a
    billing row, `subscriptionHistory` and `subHistory`, are recognised),
    `SessionWebhookResponse::$txType` and `SessionCheckout::$txType`. The new
    `Support\Enums` helper renders and compares the union.
-   **Retries are GET-only.** `POST`, `PUT` and `DELETE` are sent exactly once, and so are
    the three GET routes that perform an action — `forceCancel()`, `executeTestBilling()`
    and `triggerTestClaimFunds()`. Other GETs are retried on any transport-level failure —
    every PSR-18 client exception, including the connection resets and truncated responses
    Guzzle reports as request exceptions — and on `5xx`, with exponential backoff (1s, 2s,
    4s); `maxRetries: 0` disables retries. `4xx`, `429` and `3xx` are never retried. A `3xx`
    is reported immediately as a `ServerException`, and the client the SDK builds never
    follows redirects (following one would replay your `X-API-Key` to another host).
-   **Error taxonomy.** A `409` raises the new `ConflictException`. Any other unmapped `4xx`
    raises the base `QBitFlowException` with its status code, not `ValidationException`
    (reserved for `400`/`422` and local validation). `ServerException` also covers an empty
    (non-`204`) or non-JSON `2xx` and a `2xx` body of the wrong shape. Every exception
    carries `getStatusCode()`, `getResponse()` and `getFields()`; `Retry-After` is read in
    both its delta-seconds and HTTP-date forms. A request body JSON cannot encode (`NAN`,
    `INF`, invalid UTF-8) is a `ValidationException`, never a bare `JsonException`.
-   **Client-side validation mirrors the live API**, with one shared helper:
    -   names (`alphanumspace`): letters, *decimal* digits (Unicode Nd — `²`, `½` and Roman
        numerals are rejected), spaces and `- _ ' .`, 2–100 characters; a name of only
        spaces is accepted, as the API accepts it;
    -   product text (`producttext`): no markup or control characters, not blank after a
        Unicode whitespace trim, 2–100 (name) / 2–500 (description) characters;
    -   prices — product create/update and inline session products — must be finite and
        greater than 0 (the API refuses an inline session price of 0);
    -   `customerUUID` on a session must be a bare UUID (a prefixed or malformed id is a
        400); every empty optional session string is left off the request;
    -   redirect URLs: absolute `http(s)`, scheme case-insensitive (`HTTPS://` is accepted);
    -   durations fit a `uint32`; a billing `frequency` must be at least 1, a `trialPeriod`
        and `minPeriods` may be 0 (`minPeriods: 0` is left off the request);
    -   `UpdateProductDto` rejects an empty name or description (the API does too);
    -   the accounting export checks real `YYYY-MM-DD` dates and `from <= to` locally, and
        leaves the maximum window length to the API;
    -   `CreateUserDto` rejects `UserRole::OWNER` and `UserRole::HANDLE` (the API binds
        `oneof=admin user`); `fromArray()` with an unknown role raises
        `ValidationException`; `Duration::fromArray()` requires a unit.
-   **`webhooks->verify()` returns `false` only for the API's 400.** 401, 403, 409, 5xx and
    network failures are rethrown as their own typed exceptions, so a bad API key no longer
    looks like a forged signature. The raw payload is now forwarded byte for byte.
-   **`onBehalfOf(0)` acts at organization level** — it returns a copy *without* the
    `On-Behalf-Of` header (it used to throw); a negative id raises `ValidationException`.
-   **Laravel:** `SubscriptionStatusChanged` and `SubscriptionBilled` carry
    `$subscriptionUUID` from the envelope; their `$subscriptionReference` / `reference()`
    (and `TransactionWebhookReceived::reference()`) are `string`, `''` when none was set.
    The service provider no longer declares `provides()`.
-   **Exception constructors take an optional trailing `$fields`.**

### Added

-   **Client-level On-Behalf-Of.** `$client->onBehalfOf($userId)` returns a copy of the
    client whose every service sends `On-Behalf-Of`, sharing its configuration and HTTP
    client. The per-service `onBehalfOf()` stays.
-   **`transactionStatus->get()` accepts the raw type string** as well as a
    `TransactionType` member, for a type this SDK has no enum case for yet.
-   **`Dto\SubscriptionWebhook`**, the typed subscription-webhook envelope:
    `subscriptionUUID`, `subscriptionReference`, `type`, and `data` hydrated as a
    `SubscriptionStatusTransition` or a `SubscriptionHistory` by `type` (raw array for an
    unknown type).
-   **`Support\Time`** — `Time::isZero()` / `Time::zero()` for Go's zero time — and
    `Subscription::hasBeenBilled()` / `hasNextBilling()`.
-   **Local webhook verification in Laravel.** `config/qbitflow.php` gains `webhook_secret`
    (`QBITFLOW_WEBHOOK_SECRET`). When set, the `qbitflow.webhook` middleware and the route
    macros verify signatures with `WebhookVerifier` — no API round-trip, and no API key
    needed. `php artisan qbitflow:install` adds the placeholder.
-   **`ConflictException`** (HTTP `409`), **`FieldError`** and
    **`QBitFlowException::getFields()`**, **`UserRole::HANDLE`**,
    **`UserRole::isAssignableOnCreate()`**, **`SubscriptionSession::$upgradingFromTrial`**,
    **`SessionCheckout::isPayment()`**.
-   **Session discriminator prefers `txType`**, falling back to `frequency`.
-   **`QBitFlow::fromArray()`** accepts `requestFactory` and `streamFactory`; a blank
    `baseUrl` means the default.
-   **Golden webhook vectors** shared by all four SDKs are pinned in
    `tests/Unit/WebhookVerifierTest.php`, including decimal strings of 19+ digits, C0
    controls, lone surrogates, `-0` and UTF-8 key ordering.
-   **Opt-in integration suite** (`tests/Integration`): skipped unless `QBITFLOW_API_KEY` is
    set, and failing — never falling back to a default server — when the key is set without
    `QBITFLOW_BASE_URL`.

### Fixed

-   **Local webhook verification rejected genuine webhooks.** Canonicalisation turned any
    JSON string of 19 or more digits into a number, so a payload carrying a large decimal
    amount (`amountMinUnits`, `txAmounts.minUnits.*`) never matched the signature. It also
    depended on PHP's `serialize_precision` (a host set to 17 rejected every webhook carrying
    a float) and could not represent `-0`, lone surrogate escapes or invalid UTF-8 the way
    Go does. Canonical JSON is now produced by a strict parser of its own and is
    byte-identical to Go's `encoding/json` for all shared vectors.
-   **API webhook verification turned `{}` into `[]`.** The payload was decoded into PHP
    arrays and re-encoded, changing what the API re-canonicalised; it is now forwarded
    byte for byte.
-   **`SubscriptionStatusChanged` always reported an empty subscription UUID.** The event
    was hydrated from the webhook's `data`, but the identity lives on the envelope. The
    shipped examples read the same wrong field and are fixed too.
-   **Transient network failures on GETs were not retried** when Guzzle reported them as
    request exceptions (cURL 18, 55, 56 — a truncated response or a connection reset).
-   **Subscription sessions rejected inline ghost products.** The API accepts them.
-   **Only the first validation failure was reported**; `getFields()` now carries all.
-   **`producttext` missed the C1 control range** (U+007F–U+009F).
-   **Laravel:** with a webhook secret, the middleware no longer requires an API key; on the
    API path, a delivery whose body is not JSON gets a 400 instead of a 500.
-   **The Symfony HTTP client** built by the resolver now bounds the whole request
    (`max_duration`), not only idle time.
-   **The webhook-id header was defined twice with different casing**; both entry points now
    share `WebhookVerifier`'s constants (`X-Webhook-Id`).

### Documentation

-   README rewritten against the final types: a "Response Types" section, client-level
    `onBehalfOf`, the retry list including the test claim-funds
    trigger, the webhook argument-order warning, and the caveat that the API currently
    cannot route a reference containing `/` (it is escaped, but answers 404).
-   Examples run as written: `examples/client.php` exports the last month and always cleans
    up after itself; `examples/webhook-server.php` verifies locally without an API key and
    uses the typed webhook envelopes.
-   `.qbitflow-sync/last-commit.json` records docs revision `5e7d5a5`.

### Upgrading from 2.1

Every source-level break, with the change you need to make. If none of these appear in your
codebase, the upgrade is drop-in.

**1. Null checks on fields that are now always set.** Fields such as
`Customer::$phoneNumber`, `Product::$reference`, `Payment::$organizationId` or
`TransactionStatus::$message` are never `null` any more: an empty value is `''` / `0`.

```php
// before
if ($customer->phoneNumber !== null) { … }
// after
if ($customer->phoneNumber !== '') { … }
```

**2. `customerUUID` is nullable** on `Payment`, `Subscription` and `SubscriptionHistory`
(it was `''` when no customer was attached), and `SessionWebhookResponse::$status` is
nullable.

```php
$payment->customerUUID ?? 'no customer';
$event->payload->status?->txHash;
```

**3. `currency` is always a `Currency`** on payments, combined entries, subscriptions and
billing records — drop any `?->` on it. `CombinedPayment::$organizationId` / `$userId` are
gone.

**4. Enum-backed response fields are `<Enum>|string`.**

```php
// before
$subscription->subscriptionStatus->value;
// after
use QBitFlow\Support\Enums;
Enums::value($subscription->subscriptionStatus);                    // 'active', or e.g. 'paused'
$subscription->subscriptionStatus === SubscriptionStatus::ACTIVE;   // still works
```

Exhaustive `match ($status)` expressions need a `default` arm.

**5. Subscription webhook listeners — the UUID is on the event.**

```php
public function handle(SubscriptionStatusChanged $event): void
{
    $uuid = $event->subscriptionUUID;   // 'sub@01a0c5be-...'
    $ref  = $event->reference();        // your own reference, '' when none
}

// If you construct the events yourself (tests):
new SubscriptionBilled($history, $subscriptionUUID, $reference);
```

**6. Unset timestamps are Go's zero time, not 1970.** A timestamp the API always sends is
never `null`; one that was never set is `0001-01-01T00:00:00Z`.

```php
use QBitFlow\Support\Time;

if (! Time::isZero($subscription->lastBillingDate)) { … }   // or $subscription->hasBeenBilled()
```

**7. Malformed responses raise `ServerException`.** A field of the wrong JSON type used to
be coerced silently; it now fails the call with the HTTP status attached.

**8. `executeTestBilling()` returns `SuccessResponse`**, and a subscription that is not yet
due raises `ConflictException`.

**9. Error handling.**

```php
catch (ValidationException $e) { /* 400, 422, or local validation (status code null) */ }
catch (ConflictException $e)   { /* 409 */ }
catch (QBitFlowException $e)   { /* anything else; $e->getStatusCode() says which */ }
```

**10. Retries.** Nothing to change unless you relied on a `POST` being retried for you: it
is not, any more. If a create fails with `NetworkException` or `ServerException`, look the
resource up by your own `reference` before resending.

**11. `webhooks->verify()` throws on `401`/`403`** instead of returning `false`.

```php
try {
    if (! $client->webhooks->verify(payload: $raw, signature: $sig, timestamp: $ts)) {
        http_response_code(400);   // genuinely rejected signature
        exit;
    }
} catch (UnauthorizedException | ForbiddenException $e) {
    http_response_code(500);       // a credentials problem: non-2xx so QBitFlow retries
    exit;
}
```

**12. Stricter local validation.** Input the API rejects is now rejected before the
round-trip: a price of `0`, markup in product text, `&`/`,`/`(`/non-decimal digits in a name,
a malformed email, a prefixed `customerUUID`, a non-http(s) redirect URL, an inverted
accounting window, `UserRole::OWNER`/`HANDLE` on create.

**13. Removed types** (`PaygSubscriptionSession`, `StatusLinkResponse`, `StatusResponse`,
`StatusResponseError`) and `LinkResponse::$expiresAt`: remove the references;
`discriminate()` returns `OneTimePaymentSession` or `SubscriptionSession` only.

**14. Positional DTO construction** (tests, fixtures): constructor parameters were reordered
so required ones come first — switch to named arguments.

## [2.1.0] - 2026-09-21

Aligns the SDK with docs revision `c3c8831`, matching the 2.1.0 release of the Go,
JavaScript and Python SDKs.

> **Note:** this release carries breaking changes (listed below) despite the minor version
> bump.


### Added — local webhook verification

- **Verify webhooks without a network call.** Local verification needs your webhook secret
  (available from the QBitFlow dashboard) but no round-trip, so it is faster and keeps
  working when the API is unreachable. The existing API-side `verify` is unchanged and
  still available for callers who would rather not hold the secret.

  It performs the same three checks the server does: the timestamp is within a replay
  window (5 minutes by default, configurable to match your deployment), the HMAC-SHA256 of
  `<timestamp>.<canonical-json>` matches, and the comparison is constant-time so a timing
  side channel cannot be used to guess the signature.

  The signature covers a **canonical** rendering — object keys sorted at every level, no
  insignificant whitespace — rather than the bytes as they arrived, because proxies and
  frameworks routinely re-serialize a body and reorder keys. Signing raw bytes would reject
  payloads that are in fact untouched.

  New: `QBitFlow\Webhooks\WebhookVerifier` with `verify()`, `computeSignature()`,
  `canonicalJson()` and `extractHeaders()`. `extractHeaders()` accepts `$_SERVER` (including
  the `HTTP_X_WEBHOOK_*` spelling), `getallheaders()`, PSR-7 headers, or Laravel's
  `$request->headers->all()`.

- **Header extraction helpers.** Reading the QBitFlow headers is framework-dependent, so
  the SDK accepts anything header-shaped and does a case-insensitive lookup, returning the
  signature, timestamp, transaction id, and whether this is the dashboard's connectivity
  test (which you can acknowledge immediately without processing).

### Fixed — validation parity with the API

- **An empty optional field now counts as "not provided"**, matching the API. Session
  checkout's `productName`, `description`, `successUrl` and `cancelUrl` are all
  `binding:"omitempty,..."` on the server, which skips validation for an empty value. The
  SDK previously rejected an explicit empty string, so the common
  `successUrl: <env var> || ""` pattern failed locally on a request the API would have
  accepted. Non-empty values are validated exactly as before.

### Changed — examples

- The webhook example (`examples/webhook-server.php`) now uses `WebhookVerifier::extractHeaders()` and verifies locally when `QBITFLOW_WEBHOOK_SECRET` is set, falling back to the API otherwise. The local path also avoids the 503-on-QBitFlow-unreachable failure mode the remote path has.

### Added — client-side request validation

- Session checkout now mirrors the API's `producttext` rule (markup characters rejected,
  2-100 for an inline product name and 2-500 for its description) and requires redirect
  URLs to be absolute `http(s)`. Invalid input fails immediately instead of after a
  round-trip, and a `javascript:` redirect target is refused outright — the API's own `uri`
  rule is more permissive than this.

### Fixed

- Canonical JSON decodes objects as `stdClass` rather than associative arrays, so an empty
  object `{}` stays `{}` instead of re-encoding as `[]` and breaking the signature. Slashes
  and non-ASCII are left unescaped and `<`, `>`, `&` are escaped in **lowercase** hex, all to
  match Go's `encoding/json` exactly (PHP's `JSON_HEX_TAG` emits uppercase).

### ⚠️ Breaking changes

- **`UpdateProductDto` fields are now all optional and nullable.** Updates are partial:
  omitted fields are left untouched (`null` values are never serialized). Previously all
  three were required and replaced the current values outright.
- **`UpdateProductDto` now requires a price greater than 0** when supplied (was `>= 0`),
  matching the API, and enforces the 2-100 / 2-500 character bounds on name and description.
- **`UpdateCustomerDto::$reference` has been removed.** A customer reference is immutable
  and the API ignores it on update, so sending it silently did nothing.
- **`UpdateUserDto` fields are now all optional and `$password` has been removed.**
  Changing a password is a JWT-only, self-service operation that cannot be performed with
  an API key; the API silently ignores it and still returns `200`, so the field was
  misleading. `$organizationFeeBps` is now nullable and defaults to `null` rather than `0`,
  so a name-only update no longer transmits a fee of `0`. A non-admin caller sending the
  field is rejected with `403`.
- **Session `$txType` is now `TransactionType`** instead of `TransactionShortType`, on
  `SessionCheckout`, `SubscriptionSession` and `PaygSubscriptionSession`. A subscription
  session reports `createSubscription`, not the short `subscription` form.

### Added

- **`Product::$test`, `Product::$organizationId`, `Product::$userId`** and
  **`Customer::$test`** — returned by the API but previously absent from the DTOs (and now
  hydrated in `fromArray()`).
- Unit coverage for partial updates: an empty `UpdateUserDto` serializes to `[]`, an
  `UpdateProductDto` sends only the fields that are set, and a non-positive update price
  is rejected.

### Fixed

- **The test suite is now hermetic.** `Config::baseUrl()` honours `QBITFLOW_BASE_URL`, so
  any developer with that variable exported (as you would for integration work) saw 19 unit
  tests fail on path assertions. `phpunit.xml` now forces the variable empty for the suite.

## [2.0.0] - 2026-09-18

Initial release of the PHP SDK. It ships as `2.0.0` to line up with the Python and
JavaScript SDKs, which are synced to the same API documentation revision
(`9e63ae8`, 2026-09-13). The API base URL is unchanged (`/v1`).

### Added

- **Framework-agnostic client** — `QBitFlow\QBitFlow`, built on PSR-18 / PSR-17 so it runs
  in any PHP project. An HTTP client is discovered automatically; Guzzle is preferred when
  installed, because it is the one the SDK can hand a timeout to.
- **Twelve services**, each reachable as a property (`$client->products`) and as a method
  (`$client->products()`): `customers`, `products`, `users`, `apiKeys`, `webhooks`,
  `oneTimePayments`, `subscriptions`, `transactionStatus`, `refunds`, `accounting`,
  `claims`, `currencies`.
- **`onBehalfOf($userId)`** on every service — acts as a user in your organization with a
  single admin or owner key, returning a scoped copy so the base client is untouched. The
  header survives delegation to the internal session service.
- **Typed DTOs and enums throughout** — readonly classes for every request and response,
  with backed enums for `UserRole`, `TransactionType`, `TransactionShortType`,
  `TransactionStatusValue`, `SubscriptionStatus`, `RefundStatus`, `DurationUnit`,
  `AccountingEventType`, `ExportFormat`, `CombinedPaymentSource` and
  `SubscriptionWebhookType`. Unknown enum values from the API degrade to a default rather
  than failing the response.
- **Structured payment metadata** — `PaymentMetadata`, `TxMetadata`, `TxAmountsFull`,
  `NetworkFees`, `BlockData`, `OrganizationFee` and `ReferralFee`. Min-unit amounts stay
  decimal strings; USD amounts are floats.
- **Session discrimination** — `SessionCheckout::discriminate()` resolves a raw payload to
  `OneTimePaymentSession`, `SubscriptionSession` or `PaygSubscriptionSession` by inspecting
  `frequency`.
- **`Duration`** value object with named constructors (`Duration::months(1)`).
- **`CursorData`** pagination pages, countable and iterable, with `hasMore()`.
- **Accounting export** — `export()` matching the other SDKs, plus `exportJson()` and
  `exportCsv()` for a single definite return type.
- **Retries with backoff** — server (`5xx`) and network failures are retried; client errors
  never are. Exceptions carry the HTTP status, decoded body, and `Retry-After` on `429`.
- **Laravel integration** — auto-discovered `QBitFlowServiceProvider`, a `QBitFlow` facade,
  a publishable `config/qbitflow.php` driven by `QBITFLOW_*` environment variables, the
  `qbitflow.webhook` signature-verification middleware, and
  `Route::qbitflowTransactionWebhook()` / `Route::qbitflowSubscriptionWebhook()` macros
  that dispatch the typed `TransactionWebhookReceived`, `SubscriptionStatusChanged` and
  `SubscriptionBilled` events. In a Laravel application `composer require
  qbitflow/qbitflow-php` adds exactly one package: Guzzle, which arrives with
  `illuminate/http`, already satisfies the PSR requirements.
- **`php artisan qbitflow:install`** — publishes the config, adds a `QBITFLOW_API_KEY`
  placeholder to `.env` and `.env.example` when absent, and prints the next steps. It never
  overwrites an existing value, so it is safe to re-run.
- **`php artisan qbitflow:verify`** — calls `GET /user` and reports who the key belongs to,
  its role and the organization. Tells a rejected key apart from an unreachable API, and
  warns when a user-level key is in use (which makes `onBehalfOf()` fail with a 403).
- **Container-aware HTTP** — a PSR-18 client or PSR-17 factory bound in the Laravel
  container is used in preference to auto-detection, so an application can supply its own
  proxy, logging or retry middleware, and tests can inject a fake.
- **Install-time dependency enforcement** — `psr/http-client-implementation` and
  `psr/http-factory-implementation` are declared in `require`, so a project with no PSR-18
  implementation is rejected by Composer with a message naming what is missing, instead of
  failing on the first API call.

### Notes on parity

Naming follows the JavaScript SDK (`camelCase`), which is also PHP's convention. Where the
two existing SDKs disagree, the REST reference decided it:

- **`claims->triggerTestClaimFunds()`** sends the user ID as a path segment
  (`/user/claim/funds/test-trigger/{userID}`), as documented. The JavaScript SDK sends a
  `?userID=` query parameter, which does not match the route.
- **Subscription sessions require a stored product** (`productId` or `productReference`),
  as documented and as the Python SDK enforces. The JavaScript SDK's shared validator also
  accepts an inline product, which the endpoint does not.
- **`webhooks->verify()`** returns `false` only when the API rejects the signature, and
  rethrows network and server failures, following the Python SDK. The JavaScript SDK
  returns `false` for both, which makes an outage indistinguishable from a forgery.

The SDK also has **no runtime dependency on `php-http/discovery`**. That package declares
`provide` for the PSR virtual packages, which would have silently satisfied the
implementation requirements above and defeated them; its auto-install plugin is
`plugin-optional`, so it does nothing in a project that has not allow-listed plugins.
Implementations are detected directly instead — Guzzle, Symfony HTTP Client, Nyholm,
Laminas Diactoros and Slim PSR-7 — with `php-http/discovery` used as a fallback only if the
consuming project happens to have it installed. One fewer dependency, and no Composer
plugin in an integrator's tree.

Two intentional differences from both SDKs:

- **`timeout` is expressed in seconds**, not milliseconds, matching PHP HTTP clients.
- **Timestamps hydrate to `DateTimeImmutable`.** Decimal strings stay strings.

### Not included

- **WebSocket transaction status.** A long-lived socket does not fit a typical PHP request;
  use webhooks or poll `transactionStatus->get()`. The Python SDK omits it for the same
  reason.
- **Pay-as-you-go service.** PAYG session creation is disabled on the API, so no service is
  exposed. `PaygSubscriptionSession` still hydrates when reading an existing PAYG session.
- **API-key creation and deletion.** Both are JWT-only operations on the API and cannot be
  performed with an API key; manage keys from the dashboard. Read access is available.

[2.0.0]: https://github.com/qbitflow/qbitflow-php-sdk/releases/tag/v2.0.0
