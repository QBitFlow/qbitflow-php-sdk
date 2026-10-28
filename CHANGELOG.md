# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog 1.1.0](https://keepachangelog.com/en/1.1.0/), and this
project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [3.0.1] - 2026-10-28

One `composer require qbitflow/qbitflow-php:^3.0` now installs a working SDK, in plain PHP and in
Laravel. No code you call changes.

### Fixed

-   **3.0.0 did not install in a project without an HTTP client.** It required the virtual
    packages `psr/http-client-implementation` and `psr/http-factory-implementation`, and Composer
    never picks a package to provide one: `composer require qbitflow/qbitflow-php` failed until
    Guzzle was added to the same command. The SDK now requires `guzzlehttp/guzzle`
    (`^7.15.2 || ^8.0.1`), which brings the PSR-18 client and, with `guzzlehttp/psr7`, the PSR-17
    factories; the two virtual requirements and the `suggest` entries for Guzzle and Nyholm are
    gone.
-   **The installation instructions.** Every install command is now
    `composer require qbitflow/qbitflow-php:^3.0`, and the README no longer claims that Composer
    refuses an older interpreter: on a PHP below the patched floors a bare
    `composer require qbitflow/qbitflow-php` silently installs 2.1.0, the SDK for the previous API.
    With `:^3.0` Composer stops and names the PHP version it needs. The floors themselves
    (`~8.2.35 || ~8.3.36 || ~8.4.27 || >=8.5.12`) do not change.

### Changed

-   **Guzzle 7 and 8.** A project keeps the Guzzle it has: Laravel 10, 11 and 12 stay on Guzzle 7,
    Laravel 13 and a new plain-PHP project get Guzzle 8. The client the SDK builds gets the same
    options on both (`timeout`, `connect_timeout`, no exception on HTTP errors, no redirects
    followed), and errors are classified the same way.
-   A PSR-18 client passed to the constructor (`httpClient:`) is used exactly as before: Guzzle is
    installed but not used.
-   `QBitFlow::VERSION` (and the `User-Agent`) is `3.0.1`.

### Security

-   The Guzzle floor, 7.15.2 / 8.0.1, leaves out the releases affected by Guzzle's 2026 advisories
    (CVE-2026-55568, CVE-2026-55767, CVE-2026-59883, CVE-2026-67339, CVE-2026-67353,
    CVE-2026-67354, CVE-2026-67355, CVE-2026-69245, CVE-2026-69246), and with them `guzzlehttp/psr7`
    before 2.13. If your lock file pins an older Guzzle 7, `composer require` asks for `-W`, and
    Guzzle is updated within 7.

## [3.0.0] - 2026-10-28

The SDK for **QBitFlow API v2**, and a major release: the client, the routes, most names, the
models, the exceptions, the webhooks and the Laravel integration change. This entry lists the
changes since 2.1.0. **[MIGRATION-v3.md](https://github.com/QBitFlow/qbitflow-php-sdk/blob/main/MIGRATION-v3.md)**
maps every 2.x method and class to its replacement, with before/after code. The Go, JavaScript,
Python and PHP SDKs 3.0.0 share the same services, methods, errors, retries, validation and
webhook verification.

Requires PHP **8.2.35, 8.3.36, 8.4.27 or 8.5.12** or later in the branch (see Security), and
optionally Laravel 10, 11, 12 or 13.

### Added

-   **API v2's integrator surface: 14 services, 53 public routes.** `$client->me()` (what the key
    is: role, space, mode, the member acted for: the recommended start-up check) and
    `$client->onBehalfOf($userUuid)` (a client acting in a member's space, sharing the
    transport); the services `products`, `customers`, `currencies`, `checkoutSessions`,
    `payments`, `transactions` (payments and bills in one feed), `failures` (failed payment
    attempts), `subscriptions`, `refunds` (read only), `members`, `invitations`, `wallets`,
    `accounting` and `webhooks` (with `webhooks->endpoints` and `webhooks->events`, the event
    log). Each is a property (`$client->products`) and a method of the same name (for the
    Laravel facade).
-   **Checkout sessions**: `checkoutSessions->createPayment()`, `createSubscription()` (with
    `expiresInMinutes`, `metadata`, `customerUuid` / `customerReference` and the `{{UUID}}` /
    `{{TRANSACTION_TYPE}}` redirect placeholders, `Placeholders::UUID` /
    `Placeholders::TRANSACTION_TYPE`), `get()` (four statuses: `created`, `waitingConfirmation`,
    `completed`, `expired`; a failed attempt is `created` with `lastAttempt`, never final) and
    `expire()`.
-   **Checkout fees**: `fees: new CheckoutFees(…)` on both session creates adds your lines
    (`FeeItem`: `label`, `amountUsd`, optional `description`; at most 10) and QBitFlow's
    processing fee (`processingFee: true`, grossed up so you keep the price and your lines) to
    the price. `Payment`, `Subscription` and `Transaction` carry `priceUsd` and `fees`
    (`FeeLine`, `Enums\FeeLineType`).
-   **Your own `metadata`** (`array<string,string>`) on products, customers, checkouts (copied
    onto the payment or subscription and into its webhooks) and subscriptions
    (`subscriptions->update()`), merged on updates (a key set to `''` deletes it).
-   **Lists and filters**: `products->list()` (`includeHidden`, `subscription`, `reference`),
    `customers->list()` (`email`, `reference`), `payments->list()` (customer, product, dates,
    `reference`, `refunded`, `includeMembers` / `userUuid`), `subscriptions->list()`,
    `listBills()` and `getBill()`, `refunds->list()` (`status`, a list sent repeated, `txUuid`,
    `held`, `includeMembers`, `userUuid`) and `refunds->get()`, `failures->list()`,
    `transactions->list()` (`type`, `subscriptionUuid`, `refunded`), `ReadParams` on the single
    reads (`includeMembers`).
-   **Pagination**: paginated lists return a `QBitFlow\Page` (`items`, `hasMore`,
    `nextCursor`), and each has a lazy `Generator` twin (`iterate()`, `iterateBills()`) that
    walks every page. Bounded lists (currencies, wallets, held funds, the JSON export) return a
    plain array.
-   **`getByReference()`** on products, customers, payments and subscriptions: the item whose
    reference is exactly the one asked for, else a `NotFoundException` (404, `not_found`).
-   **Subscriptions**: `Subscription::$currentPeriodEnd` and `hasAccess()`, `actionRequired`,
    `dunning`, `allowance` (`Allowance`), `cancellationReason`, `managementPageLink`;
    `cancel($uuid, ?CancelSubscriptionParams)` (`immediate`, returns a
    `SubscriptionCancellation` with `pending` for an HTTP 202) and `testBill()` (test mode).
-   **Marketplaces**: `members` (`list`, `iterate`, `get`, `update`, `remove`, `trust`,
    `listHeldFunds`, `getHeldFunds`, `getOwnHeldFunds`) and `invitations` (`create`, with
    `holdFunds` and `sendEmail`; `list` and `iterate`, the member invitations: the team's are
    managed from the dashboard; `revoke`); `Enums\MerchantNotReadyReason`
    for `409 merchant_not_ready`.
-   **Wallets and currencies**: `wallets->list()` (with balances), `listForMember()`,
    `listAcceptedCurrencies()`; `currencies->list()` and `get()`; the `Currency` object's `code`
    (`BASE:USDC`) and `chain`; `Models\TokenAmount`, the shape of every token amount.
-   **Accounting**: `accounting->exportJson()` / `exportCsv()` (at most 92 days, both included)
    and `exportJsonRange()` / `exportCsvRange()` for any range.
-   **Webhook endpoints and the event log**: `webhooks->endpoints` (`list`, `iterate`,
    `create` returning the secret once, `get`, `update` with `enabled`, `delete`) and
    `webhooks->events` (`list`, `iterate`, `get` with its deliveries).
-   **Webhooks without a client**: `Webhook::verify()`, `verifyRequest()` (a PSR-7 request),
    `constructEvent()`, `parseEvent()` and `sign()` (to test your handlers), with a tolerance and
    an injectable clock; the header constants `SIGNATURE_HEADER`, `EVENT_ID_HEADER`,
    `EVENT_TYPE_HEADER`. Typed events: one `final readonly` class per type (15 types, with a
    typed `data`), an `UnknownEvent` for a type added later, and `Event::decodeData()`.
-   **`WebhookRouter`** (also `$client->webhooks->router($secret)`): verifies, parses and runs
    your typed handlers (`on()`, `onUnknown()`, `onAny()`, `onError()`), and says what to answer
    (200 / 400 / 500, `WebhookResult`); adapters for plain PHP (`handleGlobals()`), PSR-7
    (`handleRequest()`) and PSR-15 (`psr15()`), and `dispatch()` for an event verified elsewhere.
    New dependency: `psr/http-server-handler` ^1.0.
-   **Typed exceptions** (see Changed), the API's request id on every one, and
    `QBitFlowException::isRetryable()`.
-   **Retries and idempotency**: reads and the 6 creates are retried on network errors,
    timeouts, 5xx, 429 (after `Retry-After`, at most 60 s) and `409 idempotency_key_in_use`, 3
    times by default with a 1 s · 2ⁿ back-off (`maxRetries: 0` disables them); every create sends
    an `Idempotency-Key`, reused by its retries.
-   **`RequestOptions`**, last on every method: `onBehalfOf`, `idempotencyKey` (your own, for
    retries across processes) and `requestId` (`X-Request-Id`).
-   **Client-side validation** mirroring the API's: each params class's `validate()` runs before
    every request (names, texts, references, emails, phones, URLs, USD amounts and percents as
    strings, metadata, durations, UUIDs and transaction ids, cursors, enum filters); a failure is
    a `ValidationException` naming each input, and nothing is sent.
-   **Helpers**: `QBitFlow::fromEnv()`, `checkoutSessions->waitForCompletion()`,
    `Support\Amount::format()` / `parse()` (exact min units ↔ decimal strings) and
    `Currency::formatAmount()`.
-   **Laravel**: `webhook_secret` and `webhook_tolerance` config keys, one Laravel event per
    webhook type plus `WebhookReceived`, the verified event on `$request->attributes`
    (`qbitflow.event`), a `WebhookRouter` bound in the container, facade methods for every
    service and `me()`; `qbitflow:verify` calls `me()`.
-   `User-Agent: qbitflow-php/3.0.0` (`QBitFlow::VERSION`); examples for every service, a Laravel
    example, `MIGRATION-v3.md`, `SECURITY.md` and `COMPLIANCE.md` rewritten.

### Changed

-   **Breaking: API v2.** The default base URL is `https://api.qbitflow.app/v2`, with its REST
    routes (plural collections, `PATCH` updates, `POST /<resource>/:uuid/<verb>` actions,
    lookups by reference as list filters, every `DELETE` answering 204 and returning `void`).
    The client no longer reads `QBITFLOW_BASE_URL` itself: pass `baseUrl:` or use
    `QBitFlow::fromEnv()` (the Laravel config still maps it).
-   **Breaking: the client.** `new QBitFlow(apiKey: …, baseUrl:, timeout:, maxRetries:,
    onBehalfOf:, httpClient:, requestFactory:, streamFactory:)` with named arguments; the key must
    be non-blank, start with `sk_` and be printable ASCII; `timeout` is per attempt. A bad
    argument is a `ValidationException`, and nothing is sent.
-   **Breaking: `onBehalfOf` takes a member's user UUID** (a string), on the client
    (`$client->onBehalfOf($userUuid)`) or per call (`RequestOptions`); the per-service
    `onBehalfOf(int)` methods are gone.
-   **Breaking: params classes** (`QBitFlow\Params\…`, readonly, named arguments) replace the
    DTOs and arrays of every create, update and list.
-   **Breaking: models** are readonly `QBitFlow\Models\…` classes (were `QBitFlow\Dto\…`) whose
    properties are the API's JSON keys (`customerUuid`, `txHash`); renamed among others:
    `LinkResponse` → `CreatedCheckoutSession`, `TransactionStatus` → `CheckoutSessionStatus`,
    `SubscriptionHistory` → `Bill`, `RefundEntry` → `Refund`, `User` → `Member`,
    `CombinedPayment` → `Transaction`, `CursorData` → `Page`; `name` → `firstName` on people;
    the fee split (`Payment::$metadata`, `PaymentMetadata`, `TxMetadata`, …) → `settlement`
    (`Settlement`, `Split`, `SettlementTransaction`, `TransferSettlement`).
-   **Breaking: money is a decimal string, never a float**, in requests and responses: USD
    amounts (`priceUsd`, `amountUsd`), token amounts in the token's units (`amount` `"10"` for
    10 USDC, next to `amountMinUnits`) and percents (`feePercent`, `organizationFeePercent`,
    `refundPercent`; were basis points `…Bps`).
-   **Breaking: ids are strings**: products and people by UUID (`productUuid`, `userUuid`),
    transactions by prefixed id (`pay@…`, `sub@…`, `sub-hist@…`, `refund@…`, the bare UUID
    accepted); currencies keep numeric ids.
-   **Breaking: enums are classes of string constants** (were PHP backed enums), the API's
    lowerCamelCase values kept as plain strings (an unknown value is kept, never refused):
    `UserRole` → `Role` (`ADMIN`, `USER`), `RefundStatus::REFUSED` → `REJECTED`,
    `TransactionType::ONE_TIME_PAYMENT` → `PAYMENT` (only `PAYMENT` and `CREATE_SUBSCRIPTION`
    remain), `CombinedPaymentSource` → `TransactionKind`, `AccountingEventType`
    `subscriptionHistory` → `bill`.
-   **Breaking: users and claims become invitations, members and held funds**; one-time payment
    and subscription sessions move to `checkoutSessions`; `oneTimePayments` becomes `payments`;
    `subscriptions->forceCancel()` → `cancel()`, `executeTestBillingCycle()` → `testBill()`,
    `getPaymentHistory()` → `listBills()`; `getAll…($limit, $cursor)` → `list(?…ListParams)`.
-   **Breaking: exceptions**, one class per condition, all `ApiException`s (a
    `QBitFlowException` implementing `ExceptionInterface`): `ValidationException`,
    `BadRequestException`, `AuthenticationException` (was `UnauthorizedException`),
    `PermissionDeniedException` (was `ForbiddenException`), `NotFoundException`,
    `ConflictException`, `GoneException`, `IdempotencyException`, `RateLimitException`,
    `ServerException`, `NetworkException`, `WebhookSignatureException`. Each carries `status`,
    `apiCode` (the API's string code; `getCode()` is the HTTP status), `errorMessage`,
    `details`, `requestId`, `fieldErrors` and `rawBody`.
-   **Breaking: webhooks** use API v2's scheme: the `QBitFlow-Signature: t=…,v1=…` header (an
    HMAC-SHA256 of `t.rawBody`, over the raw body), the v2 event envelope (`id`, `type`,
    `createdAt`, `test`, `userUuid`, `data`; another payload version is refused), and the events
    `refund.approved` / `refund.rejected` (were `refund.completed` / `refund.denied`);
    `subscription.billed`'s data is `{bill, subscription}`. `WebhookVerifier` →
    `QBitFlow\Webhooks\Webhook`; `webhooks->verify()` → `webhooks->verifyRemote($endpointUuid,
    $rawBody, $signatureHeader)`.
-   **Breaking: Laravel.** One `Route::qbitflowWebhooks()` (was a route per webhook kind); the
    `qbitflow.webhook` middleware verifies locally with `QBITFLOW_WEBHOOK_SECRET` (required);
    the controller dispatches one Laravel event per type, then `WebhookReceived`.
-   **Retries only where they are safe**: 2.x retried every request on network errors and 5xx;
    3.0.0 retries the reads and the 6 idempotent creates only, never another write.
-   `AccountingEvent` follows the API v2 export (amounts in the currency's units with their
    `…MinUnits` twins, `currency`, network fees apart from the net, `txUuid` / `reference`).
-   Responses decode leniently on absence (an absent or `null` field gets its zero value) and
    strictly on type (a JSON number where a decimal string is expected is a `ServerException`);
    a 3xx (redirects are never followed) and an unusable 2xx body are `ServerException`s.
-   A member's own key may send a checkout's `successUrl` and `cancelUrl` while the
    organization's `members.checkoutRedirects` policy allows it; with the policy off, the API
    answers `403 policy_disabled` with `details['policy']` and `details['field']`
    (`PermissionDeniedException`).

### Removed

-   **2.x services and methods with no v2 route for an API key**: `users`, `claims`, `apiKeys`,
    `transactionStatus`, the session getters, `oneTimePayments->getCustomerForTransaction()`
    (read `Payment::$customer`), `refunds->getByTransaction()` (`refunds->list()` with
    `txUuid`), `customers->getByEmail()` (`customers->list()` with `email`: an email may match
    several customers), the public bill history, `accounting->export($from, $to, $format)` (use
    `exportJson()` / `exportCsv()`), `QBitFlow::fromArray()`, `getApiKey()` and `Config`.
-   The v1 webhook verifier (`WebhookVerifier`, `canonicalJson`, the `X-Webhook-*` headers,
    `TEST_WEBHOOK_ID`, `webhooks->isTestWebhook()`), the v1 payloads
    (`SessionWebhookResponse`, `SubscriptionStatusTransition`) and the Laravel
    `TransactionWebhookReceived` event.
-   The v1 session and response DTOs (`SessionCheckout`, `OneTimePaymentSession`,
    `SubscriptionSession`, `PaygSubscriptionSession`, `StatusLinkResponse`, `StatusResponse`,
    `SuccessResponse`, `Organization`, `ApiKey`, `ClaimFunds`, `ClaimRequestResponse`), with
    their hosted-page fields (`organizationName`, `userName`); `Support\Dto`,
    `Support\CursorData`, `Support\Validate`; the enums `ExportFormat`, `SubscriptionWebhookType`,
    `TransactionShortType`, `TransactionStatusValue`.
-   `RefundStatus::FAILED`: the API never sends it (a failed approval leaves the refund
    `pending`); it is no longer a value of the `status` filter either.
-   `TransactionType` values no API-key response carries (`TRANSFER`, `TOKEN_TRANSFER`,
    `CANCEL_SUBSCRIPTION`, `EXECUTE_SUBSCRIPTION_PAYMENT`, `CREATE_PAYG_SUBSCRIPTION`,
    `CANCEL_PAYG_SUBSCRIPTION`, `INCREASE_ALLOWANCE`, `UPDATE_MAX_AMOUNT`, `REFUND`, `FAUCET`,
    `CLAIM_FUNDS`); `ReferralFee::$deadline` (internal to QBitFlow).
-   `getStatusCode()`, `getFields()` and `getResponse()` on exceptions (use `status`,
    `fieldErrors`, `rawBody`).

### Fixed

-   Field errors of a 400 are read from the API's `details.errors` (they came back empty), and
    name each input by its wire name, dotted when nested (`frequency.unit`).
-   User-supplied path segments are escaped, a `.` or `..` segment included, so an id can never
    address another route; a `reference` equal to `.` or `..` is refused before sending (bodies,
    `productReference` / `customerReference`, the reference filters and `getByReference()`).
-   With Guzzle 6 installed next to a PSR-18 adapter, the client no longer fails with a
    `TypeError`: Guzzle is used only when it is a PSR-18 client (Guzzle 7).
-   A request body JSON cannot represent (NaN, ±INF, invalid UTF-8) is a `ValidationException`
    naming the field, instead of a malformed request.

### Security

-   **php2shell (CVE-2026-109618, CVE-2026-109619)**: `require.php` is
    `~8.2.35 || ~8.3.36 || ~8.4.27 || >=8.5.12`, the PHP releases that fix these interpreter
    flaws, so Composer refuses an unpatched PHP (a distribution's PHP with the fixes backported:
    `--ignore-platform-req=php`; see
    [SECURITY.md](https://github.com/QBitFlow/qbitflow-php-sdk/blob/main/SECURITY.md#php-interpreter-vulnerabilities-php2shell)).
-   Webhook input is bounded on every entry point: a body over 1 MiB is refused before it is read
    or parsed (`413` from the router and the Laravel middleware, a `ValidationException` from
    `constructEvent()` / `parseEvent()`), and JSON nesting is capped at 64 levels.
-   The API key and the webhook secret are `#[\SensitiveParameter]` wherever they are passed, so
    no stack trace carries them to your logs or error tracker (a network error's trace included).
-   The key and the secret are held in `\SensitiveParameterValue`s: no dump (`print_r()`,
    `var_dump()`, `var_export()`, `dd()`, an array cast) of the client, a service, a derived
    client, the `WebhookRouter` or the Laravel middleware shows them, and **`serialize()` of any
    of them throws** (a `SensitiveParameterValue` cannot be serialized), so a key never lands in a
    cache, a session or a queued job by accident. Build the client where you use it.
-   Webhook signatures are compared in constant time (`hash_equals()`, every `v1` of the header),
    and a timestamp more than 300 seconds from now is refused.
-   The constructor refuses an API key that is not printable ASCII without spaces, and a
    `baseUrl` with credentials, a query or a fragment, without quoting either value; redirects
    are never followed (the `X-API-Key` header is never replayed to another host).
-   `timeout` bounds each attempt as a whole, through the last byte of the body, so a server
    trickling its body cannot hold a call past it.

## [2.1.0] - 2026-09-21

Matches the 2.1.0 release of the Go, JavaScript and Python SDKs.

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
JavaScript SDKs, which follow the same API documentation (2026-09-13). The API base URL is unchanged (`/v1`).

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

[3.0.1]: https://github.com/QBitFlow/qbitflow-php-sdk/compare/v3.0.0...v3.0.1
[3.0.0]: https://github.com/QBitFlow/qbitflow-php-sdk/compare/v2.1.0...v3.0.0
[2.1.0]: https://github.com/QBitFlow/qbitflow-php-sdk/releases/tag/v2.1.0
[2.0.0]: https://github.com/QBitFlow/qbitflow-php-sdk/releases/tag/v2.0.0
