# Changelog

All notable changes to this project will be documented in this file.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this
project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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
