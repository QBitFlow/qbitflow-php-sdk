# Changelog

All notable changes to this project will be documented in this file.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this
project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [3.0.0] - 2026-10-08

The SDK for **QBitFlow API v2**, aligned with the API docs `v2` @ `58460e9` and with the
behaviour contract the Go, JavaScript, Python and PHP SDKs 3.0.0 share (same services, methods,
errors, retries and webhook verification). A major release: the client, most names and the
Laravel integration change. [MIGRATION-v3.md](MIGRATION-v3.md) maps every 2.x method and class
to its replacement.

This entry describes the changes since 2.1.0, the last published release (2.5.0 was prepared
but never published; its changes are part of 3.0.0). Requires PHP **8.2** or later, as before.

### ⚠️ Breaking

-   **API v2**: the default base URL is `https://api.qbitflow.app/v2`
    (`QBitFlow::DEFAULT_BASE_URL`). API v1 runs next to it, against the same data, for a
    transition period. The client no longer reads `QBITFLOW_BASE_URL` from the environment: pass
    `baseUrl:` (the Laravel config still maps it).
-   **The client.** `new QBitFlow(apiKey: …, baseUrl:, timeout:, maxRetries:, onBehalfOf:,
    httpClient:, requestFactory:, streamFactory:)`: use named arguments (`onBehalfOf` comes
    before `httpClient`). The key must be non-blank and start with `sk_`, `baseUrl` an absolute
    http(s) URL, `timeout` positive (now **per attempt**), `maxRetries` not negative: a
    `ValidationException` otherwise, and nothing is sent. `QBitFlow::fromArray()`, `getApiKey()`
    and the `Config` class are removed (`QBitFlow::DEFAULT_TIMEOUT`, `DEFAULT_MAX_RETRIES`).
-   **`onBehalfOf` takes a member's user UUID**: `$client->onBehalfOf(string $userUuid)` returns
    a client for every service (checked at once: a non-UUID or the nil UUID is a
    `ValidationException`; `''` gives the organization level). The per-service `onBehalfOf(int)`
    methods are removed. `RequestOptions(onBehalfOf: …)` acts for one call.
-   **`RequestOptions` last on every method** (`onBehalfOf`, `idempotencyKey`, `requestId`).
-   **Params classes.** Every create, update and list takes a `final readonly`
    `QBitFlow\Params\…` class built with named arguments (`CreatePaymentSessionParams`,
    `UpdateCustomerParams`, `PaymentListParams`, …) instead of a DTO or an array; each has
    `validate()` (run before every request) and `toArray()`.
-   **Models.** Responses are `readonly` `QBitFlow\Models\…` classes (was `QBitFlow\Dto\…`)
    whose properties are the API's JSON keys: `customerUUID` → `customerUuid`,
    `transactionHash` → `txHash`, `TxAmountsUSD` → `TxAmountsUsd`, `TxAmountsFull` →
    `TxAmounts`, `LinkResponse` → `CheckoutSession`, `TransactionStatus` →
    `CheckoutSessionStatus`, `SubscriptionHistory` → `Bill`, `RefundEntry` → `Refund`, `User` →
    `Member`. Optional fields are nullable (`?T`, `null` when absent); fields the API always
    sends get their zero value when absent.
-   **Enums are strings.** Enum properties are plain `string`s, and `QBitFlow\Enums\…` are
    classes of string constants (was PHP backed `enum`s): compare with `===`. Values follow the
    API's lowerCamelCase (`pastDue`, `trialExpired`, `subscriptionHistory`);
    `TransactionType::ONE_TIME_PAYMENT` → `PAYMENT`, `EXECUTE_SUBSCRIPTION_PAYMENT` →
    `EXECUTE_SUBSCRIPTION`; `UserRole` → `Role`; `RefundStatus::REFUSED` → `REJECTED`,
    `FAILED` removed.
-   **Ids are UUID strings.** Products are named by `uuid` (was the numeric `id`), and
    `productId` is `productUuid` everywhere; people are named by their user UUID (`userId` →
    `userUuid`). `organizationId` is gone from every model. Currencies keep numeric ids.
-   **Users and claims become invitations, members and the trust layer**: `users` →
    `invitations` and `members` (a person exists once they accept an invitation); `claims` →
    `members->trust()` and the held-funds reads; `apiKeys` is removed (`$client->me()`
    describes the current key).
-   **Checkout sessions have their own service.** `oneTimePayments->createSession()` and
    `subscriptions->createSession()` → `checkoutSessions->createPayment()` /
    `createSubscription()`; `transactionStatus->get($uuid, $type)` →
    `checkoutSessions->getStatus($uuid)`, with four statuses (`created`, `waitingConfirmation`,
    `completed`, `expired`): a failed attempt is `created` with `lastAttempt` set, never final.
    `oneTimePayments` becomes `payments`.
-   **Subscriptions.** `status` (was `subscriptionStatus`) takes `trial`, `trialExpired`,
    `active`, `pastDue`, `paused`, `stopped`, `cancelled`; `low_on_funds` and `pending` became
    `actionRequired` values (`topUpAllowance`, `raiseMaximum`, `confirmTrial`), and the
    `stopped` bool the `stopped` status. `frequency` is a `Duration` (was seconds),
    `nextBillingDate` nullable. `forceCancel()` → `cancel($uuid, ?CancelSubscriptionParams)`
    (a POST, never retried, returns a `SubscriptionCancellation` with `pending` for an HTTP
    202); `executeTestBilling()` returns the bill's `BillingState`; `getPaymentHistory()` →
    `listBills()` / `iterateBills()`, or `getPublicHistory()`; `get()` returns cancelled
    subscriptions too.
-   **Fee rates are percents**: `feeBps` → `feePercent`, `organizationFeeBps` →
    `organizationFeePercent` (`150` bps is `1.5`).
-   **Exceptions.** One class per condition, all extending `ApiException`, itself a
    `QBitFlowException` implementing the marker `ExceptionInterface`: `ValidationException`
    (400 `validation_failed` and client-side checks), `BadRequestException` (other 400s, which
    2.x reported as validation errors), `AuthenticationException` (was
    `UnauthorizedException`), `PermissionDeniedException` (was `ForbiddenException`),
    `NotFoundException`, `ConflictException`, `GoneException`, `IdempotencyException` (422, which
    2.x reported as a validation error), `RateLimitException`, `ServerException`,
    `NetworkException`, `WebhookSignatureException`. Every exception carries `status`,
    `apiCode` (the API's string code: `Exception::getCode()` stays the integer status),
    `errorMessage`, `details`, `requestId`, `fieldErrors` and `rawBody`, with getters;
    `getStatusCode()`, `getFields()` and `getResponse()` are removed.
-   **Webhooks** use API v2's scheme: the `QBitFlow-Signature: t=…,v1=…` header, an
    HMAC-SHA256 of `t + "." + rawBody` over the raw body (no canonical JSON), and the event
    envelope `{id, type, version, createdAt, test, userUuid, data}`. Endpoints must be on payload
    version v2: v1 bodies are not parsed. `WebhookVerifier` → `QBitFlow\Webhooks\Webhook`;
    `webhooks->verify($payload, $signature, $timestamp): bool` →
    `webhooks->verifyRemote($endpointUuid, $rawBody, $signatureHeader): void`.
-   **Pagination**: `getAll…($limit, $cursor)` returning a `CursorData` → `list(?…ListParams)`
    returning a `QBitFlow\Page`, with filters.
-   **Laravel.** `Route::qbitflowTransactionWebhook()` and `Route::qbitflowSubscriptionWebhook()`
    → one `Route::qbitflowWebhooks()`; the invokable `WebhookController` dispatches one Laravel
    event per webhook type (`PaymentCompleted`, `SubscriptionCreated`, `SubscriptionBilled`,
    `SubscriptionStatusChanged`, `SubscriptionActionRequiredChanged`,
    `SubscriptionBillingFailed`, `SubscriptionUpcomingBill`, `RefundRequested`,
    `RefundCompleted`, `RefundDenied`, `MemberJoined`, `MemberRemoved`, `HeldFundsReleased`,
    `CheckoutExpired`, `WebhookTestReceived`) then `WebhookReceived` for every delivery;
    `TransactionWebhookReceived` and the 2.x payloads of `SubscriptionBilled` /
    `SubscriptionStatusChanged` are gone. The `qbitflow.webhook` middleware verifies locally with
    `QBITFLOW_WEBHOOK_SECRET` (required; no API fallback), answers 400 to a bad signature or a v1
    body, and no longer short-circuits the dashboard's test (it is a `webhook.test` event,
    dispatched as `WebhookTestReceived`). `qbitflow:verify` calls `me()`.

### Added

-   `$client->me()`: what the key is (role, space, mode), the recommended start-up check;
    `$client->onBehalfOf()`: a client acting in a member's space, sharing the transport.
-   `checkoutSessions`: `createPayment`, `createSubscription` (with `expiresInMinutes` and the
    `{{UUID}}` / `{{TRANSACTION_TYPE}}` redirect placeholders), `getStatus`, `expire`.
-   `payments->list()` / `listCombined()` with filters (customer, product, dates, `refunded`,
    `includeMembers` / `userUuid`, `source`, `subscriptionUuid`); `payments->get()` with
    `ReadParams`. `failures->list()`: the failed payment attempts.
-   `subscriptions->list()` (status, reference and the shared filters), `listBills()`,
    `getBill()`; `Subscription::$currentPeriodEnd` (grant access while now is before it),
    `actionRequired`, `priceUsd`, `cancellationReason`, `dunning`.
-   `refunds->initiate()`: a merchant's refund of a payment or a bill, signed in the dashboard;
    refund list filters (`includeMembers`, `userUuid`, `held`).
-   `members` (`list`, `get`, `update`, `remove`, `trust`, `listHeldFunds`, `getHeldFunds`,
    `getOwnHeldFunds`) and `invitations` (`create`, `list`, `revoke`) for marketplaces.
-   `wallets` (`list` with balances, `listForMember`, `listSupportedCurrencies`),
    `currencies->get()`, product filters and subscription products, customer filters.
-   `webhooks->endpoints` (`list`, `create` with the secret shown once, `get`, `update`,
    `delete`) and `webhooks->events` (the event log: `list`, `get` with its deliveries).
-   Webhook verification without a client: `Webhook::verify()`, `Webhook::verifyRequest()` (a
    PSR-7 request, 1 MiB at most), `Webhook::constructEvent()`, `Webhook::parseEvent()`, with a
    tolerance and an injectable clock; every `v1=` signature is checked, so a secret rotation
    needs nothing on the receiver's side. The header constants `Webhook::SIGNATURE_HEADER`,
    `EVENT_ID_HEADER`, `EVENT_TYPE_HEADER`, `VERSION_HEADER`.
-   Typed events: `Events\Event::fromArray()` returns one `final readonly` subclass per type
    (`PaymentCompletedEvent`, `SubscriptionBilledEvent`, `CheckoutExpiredEvent`, … 15 types)
    with a typed `data`, or an `UnknownEvent` keeping the raw data; `Event::decodeData()` for
    another shape.
-   A `Generator` next to every paginated list: `iterate()`, `iterateCombined()`,
    `iterateBills()`, `iterateInactive()`.
-   Retries: reads and the 7 creates are retried on network errors, timeouts, 5xx, 429 (after
    `Retry-After`, at most 60 s) and `409 idempotency_key_in_use`, 3 times by default with a
    1 s · 2ⁿ back-off; `maxRetries: 0` disables them; `QBitFlowException::isRetryable()`.
-   An `Idempotency-Key` on each create, generated per call and reused by its retries;
    `RequestOptions(idempotencyKey: …)` for retries across processes.
-   `RequestOptions(requestId: …)` (`X-Request-Id`) and the request id on every exception.
-   Laravel: `webhook_secret` and `webhook_tolerance` config keys (`QBITFLOW_WEBHOOK_SECRET`,
    `QBITFLOW_WEBHOOK_TOLERANCE`); the verified event on `$request->attributes`
    (`qbitflow.event`); facade methods for every service and `me()`.
-   `User-Agent: qbitflow-php/3.0.0` and `QBitFlow::VERSION`.
-   Examples: `checkout.php`, `subscriptions.php`, `marketplace.php`, `webhook-handler.php`,
    `errors-and-retries.php`, `laravel/`; `MIGRATION-v3.md`.

### Changed

-   Responses decode leniently: a field the API leaves out or sends as `null` gets its zero
    value; a value of the wrong JSON type (an empty list where an object is expected included) is
    a `ServerException`. Unknown enum values are kept as is, decimal strings are never parsed,
    timestamps keep the offset the API sends.
-   Redirects are never followed: a 3xx is a `ServerException`.
-   A 2xx with an empty or non-JSON body where a result is expected is a `ServerException`.
-   A request body JSON cannot represent (NaN, ±INF, invalid UTF-8) is a `ValidationException`
    naming the field, and nothing is sent.
-   Update params send an explicit `''` for the clearable fields
    (`UpdateCustomerParams::$phoneNumber` / `$address`, `UpdateProductParams::$description`,
    `UpdateWebhookEndpointParams::$description`): `''` clears them, `null` leaves them.
-   Exception messages read `<message> (status <status>, code <code>, request <requestId>)`,
    followed by each field error.

### Removed

-   `users`, `claims`, `apiKeys`, `transactionStatus`, `oneTimePayments->getSession()`,
    `subscriptions->getSession()`, `oneTimePayments->getCustomerForTransaction()` (read
    `Payment::$customer`, or `customers->get($payment->customerUuid)`),
    `refunds->getByTransaction()` (read `Payment::$refund` / `Bill::$refund`),
    `accounting->export($from, $to, $format)` (use `exportJson()` / `exportCsv()`).
-   The v1 webhook verifier: `WebhookVerifier` (`verify`, `computeSignature`, `canonicalJson`,
    `extractHeaders`), the `X-Webhook-*` header constants, `TEST_WEBHOOK_ID`,
    `webhooks->isTestWebhook()` and its header getters, and the v1 payloads
    `SessionWebhookResponse` and `SubscriptionStatusTransition`.
-   `Dto\Session\SessionCheckout`, `OneTimePaymentSession`, `SubscriptionSession`,
    `PaygSubscriptionSession`, `StatusLinkResponse`, `Dto\StatusResponse`, `SuccessResponse`,
    `Organization`, `ApiKey`, `ClaimFunds`, `ClaimRequestResponse`, `Support\Dto`,
    `Support\CursorData`, `Support\Validate`, `Config`, the enums `ExportFormat`,
    `SubscriptionWebhookType`, `TransactionShortType`, `TransactionStatusValue`.

### Fixed

-   **Field validation errors were read from the wrong key** (a top-level `errors` list instead
    of the API's `details.errors`), so every field of a 400 came back empty. `fieldErrors` now
    names each failing input by the name sent, dotted when nested (`frequency.unit`).
-   A `.` or `..` path segment (a reference) is escaped, so it can no longer address another
    route.

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
