# Changelog

All notable changes to this project will be documented in this file.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this
project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [3.0.0] - 2026-10-19

The SDK for **QBitFlow API v2**, aligned with the final API v2 contract (api-hub `docs/api` @
`b97ca6c`) and with the behaviour contract the Go, JavaScript, Python and PHP SDKs 3.0.0 share
(same services, methods, errors, retries, validation and webhook verification). A major release:
the client, the routes, most names and the Laravel integration change.
[MIGRATION-v3.md](MIGRATION-v3.md) maps every 2.x method and class to its replacement.

This entry describes the changes since 2.1.0, the last published release (2.5.0 was prepared
but never published; its changes are part of 3.0.0). Requires PHP **8.2.35, 8.3.36, 8.4.27 or
8.5.12** or later in the branch (see Security).

### Security

-   **php2shell (CVE-2026-109618, CVE-2026-109619)**: `require.php` is now
    `~8.2.35 || ~8.3.36 || ~8.4.27 || >=8.5.12`, the PHP releases that fix these interpreter
    flaws: Composer refuses an unpatched PHP. A distribution's PHP with the fixes backported:
    `--ignore-platform-req=php` ([SECURITY.md](SECURITY.md#php-interpreter-vulnerabilities-php2shell)).
-   Webhook input is bounded on every entry point: `WebhookRouter::handle()` answers `413` to a
    body over 1 MiB without reading it, `Webhook::constructEvent()` and `Webhook::parseEvent()`
    throw a `ValidationException` on `body` for it, and JSON nesting is capped at
    `Webhook::MAX_JSON_DEPTH` (64).

### ⚠️ Breaking

-   **API v2**: the default base URL is `https://api.qbitflow.app/v2`
    (`QBitFlow::DEFAULT_BASE_URL`), with its REST routes: plural collections (`/products/:uuid`,
    `/customers`, `/payments`, `/subscriptions`, `/bills/:uuid`, `/refunds`, `/wallets`,
    `/currencies`, `/checkout-sessions/…`, `/payment-failures`, `/transactions`,
    `/held-funds`), updates as `PATCH`, actions as `POST /<resource>/:uuid/<verb>`, lookups by
    reference as list filters, and every `DELETE` answering `204` with no body. `/v1` is gone.
    The client no longer reads `QBITFLOW_BASE_URL` from the environment: pass `baseUrl:` (or use
    `QBitFlow::fromEnv()`; the Laravel config still maps it).
-   **The client.** `new QBitFlow(apiKey: …, baseUrl:, timeout:, maxRetries:, onBehalfOf:,
    httpClient:, requestFactory:, streamFactory:)`: use named arguments (`onBehalfOf` comes
    before `httpClient`). The key must be non-blank and start with `sk_`, `baseUrl` an absolute
    http(s) URL, `timeout` positive (now **per attempt**), `maxRetries` not negative (`0`
    disables retries): a `ValidationException` otherwise, and nothing is sent.
    `QBitFlow::fromArray()`, `getApiKey()` and the `Config` class are removed
    (`QBitFlow::DEFAULT_TIMEOUT`, `DEFAULT_MAX_RETRIES`).
-   **`onBehalfOf` takes a member's user UUID**: `$client->onBehalfOf(string $userUuid)` returns
    a client for every service (checked at once: a non-UUID or the nil UUID is a
    `ValidationException`; `''` gives the organization level, and the `On-Behalf-Of` header is
    then omitted: it is never sent blank). The per-service `onBehalfOf(int)` methods are
    removed. `new RequestOptions(onBehalfOf: …)` acts for one call.
-   **`RequestOptions` last on every method** (`onBehalfOf`, `idempotencyKey`, `requestId`).
-   **Params classes.** Every create, update and list takes a `final readonly`
    `QBitFlow\Params\…` class built with named arguments (`CreatePaymentSessionParams`,
    `UpdateCustomerParams`, `PaymentListParams`, …) instead of a DTO or an array; each has
    `validate()` (run before every request) and `toArray()` / `toQuery()`.
-   **Models.** Responses are `readonly` `QBitFlow\Models\…` classes (was `QBitFlow\Dto\…`)
    whose properties are the API's JSON keys (`customerUUID` → `customerUuid`,
    `transactionHash` → `txHash`). A field the API always sends gets its zero value when
    absent; an optional field is `?T`, `null` when absent.
-   **Money is a decimal string, never a float**, in requests and in responses: USD amounts
    (`priceUsd`, `amountUsd`, `paidUsd`, `totalUsd`, `attemptedUsd`), token amounts (`amount`,
    `paid`, `owed`, next to their `…MinUnits`) and fee rates in percent (`feePercent`,
    `organizationFeePercent`, `refundPercent`: `"1.5"` is 1.5 %). `CreateProductParams::$price`
    → `priceUsd: string`, the session params' inline `price` → `priceUsd: string`,
    `FeeItem::$amountUsd`,
    `CreateInvitationParams::$organizationFeePercent` and `UpdateMemberParams::$organizationFeePercent`
    are strings (no `int|float` input is accepted). No `float` is left in a model or a params
    class.
-   **Amounts are token amounts.** `Payment::$amount`, `Bill::$amount`, `Transaction::$amount`
    and `HeldFundsReleased::$amount` are in the currency's units (`"10"` for 10 USDC), next to
    `amountMinUnits` and `amountUsd` (what `amount` used to be).
-   **Enums are strings.** Enum properties are plain `string`s, and `QBitFlow\Enums\…` are
    classes of string constants (was PHP backed `enum`s): compare with `===`. Values follow the
    API's lowerCamelCase (`pastDue`, `trialExpired`); `TransactionType::ONE_TIME_PAYMENT` →
    `PAYMENT`, `EXECUTE_SUBSCRIPTION_PAYMENT` → `EXECUTE_SUBSCRIPTION`; `UserRole` → `Role`;
    `RefundStatus::REFUSED` → `REJECTED`, and `FAILED` stays (declared, never set);
    `AccountingEventType` `subscriptionHistory` → `BILL` (`bill`); `LedgerEntryType` is
    `PAYMENT` or `REFUND` only (a held seller sells no subscription, so no bill is ever a
    held-funds line); `CombinedPaymentSource` → `TransactionKind` (`PAYMENT`, `BILL`);
    `WebhookPayloadVersion::V1` is removed (v2 is the only payload).
-   **Ids are UUID strings.** Products are named by `uuid` (was the numeric `id`), and
    `productId` is `productUuid` everywhere; people are named by their user UUID (`userId` →
    `userUuid`). `organizationId` is gone from every model. Currencies keep numeric ids.
    Transaction ids are prefixed (`pay@`, `sub@`, `sub-hist@`, `refund@`, `transfer@`,
    `release@`) and every route also takes the bare UUID.
-   **Users and claims become invitations, members and held funds**: `users` →
    `invitations` and `members` (a person exists once they accept an invitation); `claims` →
    `members->trust()` and the held-funds reads; `apiKeys` is removed (`$client->me()`
    describes the current key). `CreateInvitationParams(holdFunds: …)` (was `trustLayer`,
    now required and always sent).
-   **Checkout sessions have their own service.** `oneTimePayments->createSession()` and
    `subscriptions->createSession()` → `checkoutSessions->createPayment()` /
    `createSubscription()`, returning a `CreatedCheckoutSession` (`link`, `uuid`, `expiresAt`);
    `transactionStatus->get($uuid, $type)` → `checkoutSessions->get($uuid)`
    (`GET /checkout-sessions/:uuid`), with four statuses (`created`, `waitingConfirmation`,
    `completed`, `expired`) and `expiresAt` while open: a failed attempt is `created` with
    `lastAttempt` set, never final. `oneTimePayments` becomes `payments`.
-   **Subscriptions.** `status` (was `subscriptionStatus`) takes `trial`, `trialExpired`,
    `active`, `pastDue`, `paused`, `stopped`, `cancelled`; `low_on_funds` and `pending` became
    `actionRequired` values (`topUpAllowance`, `raiseMaximum`, `confirmTrial`), and the
    `stopped` bool the `stopped` status. `frequency` is a `Duration` (was seconds);
    `lastBillingDate` → `lastBilledAt`, `nextBillingDate` → `nextBillingAt`,
    `minimumCancellationDate` → `cancellableAt` (all nullable); `allowance` is an `Allowance`
    object (`amount`, `amountMinUnits`, `amountUsd`, `remainingPeriods`, `maxPerPeriod`,
    `maxPerPeriodMinUnits`); `currencyId` → the `currency` object; `from`, `productUuid` and
    `subscriptionHash` are null when unknown; `managementPageLink` is on the subscription itself.
    `forceCancel()` → `cancel($uuid, ?CancelSubscriptionParams)` (`POST
    /subscriptions/:uuid/cancel`, never retried, returns a `SubscriptionCancellation` with
    `pending` for an HTTP 202); `executeTestBillingCycle()` → `testBill($uuid)`
    (`POST /subscriptions/:uuid/test-bill`, returns a `TestBill`: `billUuid` `sub-hist@…`,
    `stage`, `outcome`, `attempts`, `nextAttemptAt`); `getPaymentHistory()` → `listBills()` /
    `iterateBills()`; `get()` returns cancelled subscriptions too.
-   **The settlement.** `Payment::$metadata` (the fee split) → `Payment::$settlement`, a
    `Settlement` (`feePercent`, `organizationFee`, `referralFee`, `split`, `transaction`) on
    payments, bills, transactions and held-funds lines; a `TransferSettlement` on refunds and
    held-funds releases. `PaymentMetadata`, `TxMetadata`, `BlockData`, `NetworkFees`,
    `TxAmounts`, `TxAmountsMinUnits` and `TxAmountsUsd` are replaced by `Settlement`, `Split`
    (each part a `TokenAmount`; `platform` no longer includes the referrer's share),
    `SettlementTransaction` (`blockNumber`, `blockTime`, `gasUsed`, `fee`, `l1Fee`,
    `nativePriceUsd`) and `TransferSettlement`. `OrganizationFee` and `ReferralFee` name their
    wallet `address`.
-   **`metadata` is now your own data**: an `array<string,string>` on `Product`, `Customer`,
    `Payment` (copied from its checkout, read-only), `Subscription`, `Transaction` and the
    `checkout.expired` session.
-   **Models renamed and retyped**: `LinkResponse` → `CreatedCheckoutSession`,
    `TransactionStatus` → `CheckoutSessionStatus`, `SubscriptionHistory` → `Bill`, `RefundEntry`
    → `Refund` (`txId` → `txUuid`), `User` → `Member`, `CursorData` → `Page`. `name` →
    `firstName` on `Customer`, `CustomerSummary`, `Member` and `MeMember` (and the customer
    params); a payment's and a bill's `name` / `description` → `productName` /
    `productDescription`; `Product::$price` → `priceUsd`; `Me::$onBehalfOf` →
    `onBehalfOfUserUuid`; `Member::$acceptedCurrencyIds` → `acceptedCurrencies`
    (`list<Currency>`); `Wallet::$publicKey` → `address`; `TokenWallet` names its `currency`;
    `Balance` is a `TokenAmount` plus its `currency`; `HeldFunds` / `MemberHeldFunds`
    `totalAmount` → `totalUsd`; `LedgerEntry::$amount` → `amountUsd`, its `metadata` →
    `settlement`, plus `owed`; `Invitation::$trustLayer` → `holdFunds`; `InvitationCreated`
    `{invitation, link}` → `CreatedInvitation`, flat (the invitation's properties and `link`);
    `MemberHeldFundsSummary` → `MemberHeldFunds`; `BillingState` → `TestBill`; the
    `currencyId` properties are gone (each model carries its `currency` object).
-   **The currency object** gains `code` (`BASE:USDC`) and `chain`; `address` is null on a
    native coin; the nested `mainCurrency` is gone (`mainCurrencyId` stays).
-   **Fee rates are percents**: `feeBps` → `feePercent`, `organizationFeeBps` →
    `organizationFeePercent` (`150` bps is `"1.5"`).
-   **Pagination**: `getAll…($limit, $cursor)` returning a `CursorData` → `list(?…ListParams)`
    returning a `QBitFlow\Page` `{items, hasMore, nextCursor}`: `hasMore` is a property (no
    `hasMore()` method), `nextCursor` an opaque `?string` (null on the last page). The
    `iterate*()` generators stop when `hasMore` is false (also, defensively, on a page that
    claims more without a new cursor or without items). Bounded lists (`currencies->list()`,
    `wallets->list()`, `wallets->listForMember()`, `wallets->listAcceptedCurrencies()`,
    `members->listHeldFunds()`, `accounting->exportJson()`) return a `list<T>` (and follow a
    `nextCursor` should one ever claim more, so an export is never cut short silently).
-   **Lookups by reference are list filters.** `products`, `customers`, `payments` and
    `subscriptions` `getByReference($reference)` run the list's `?reference=` filter and return
    the item whose `reference` equals the one asked for (exactly), or a `NotFoundException`
    (status 404, code `not_found`) when none does — never another resource. The reference is
    validated. `customers->getByEmail()` is removed: an email may match several customers
    (`customers->list(new CustomerListParams(email: …))`).
-   **Every `DELETE` returns `void`**: `invitations->revoke()` no longer returns the invitation
    (`204`), nor do the deletes return a `SuccessResponse`.
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
    envelope `{id, type, version, createdAt, test, userUuid, data}`; a version other than `v2`
    is refused. `WebhookVerifier` → `QBitFlow\Webhooks\Webhook`;
    `webhooks->verify($payload, $signature, $timestamp): bool` →
    `webhooks->verifyRemote($endpointUuid, $rawBody, $signatureHeader): void`.
-   **Webhook events renamed and reshaped**: `refund.completed` → `refund.approved`
    (`EventType::REFUND_APPROVED`, `Events\RefundApprovedEvent`), `refund.denied` →
    `refund.rejected` (`EventType::REFUND_REJECTED`, `Events\RefundRejectedEvent`).
    `subscription.billed`'s data is nested: `Models\SubscriptionBilled {bill, subscription}`
    (the flattened `subscriptionReference` / `subscriptionStatus` are `subscription->reference`
    / `->status`). `subscription.created`'s data is a plain `Models\Subscription` (the
    `SubscriptionCreated` model is gone). `subscription.upcomingBill`'s `billingDate` →
    `billingAt` and its `amountUsd` is a string. `heldFunds.released`'s `txMetadata` →
    `settlement`. The `checkout.expired` session has `priceUsd`, `amountUsd` (was `price` /
    `amount` numbers), `availableCurrencies` (was `availableCurrencyIds`), `metadata`, and
    `upgradingFromTrial` as a bool.
-   **Laravel.** `Route::qbitflowTransactionWebhook()` and `Route::qbitflowSubscriptionWebhook()`
    → one `Route::qbitflowWebhooks()`; the invokable `WebhookController` dispatches one Laravel
    event per webhook type (`PaymentCompleted`, `SubscriptionCreated`, `SubscriptionBilled`,
    `SubscriptionStatusChanged`, `SubscriptionActionRequiredChanged`,
    `SubscriptionBillingFailed`, `SubscriptionUpcomingBill`, `RefundRequested`,
    `RefundApproved`, `RefundRejected`, `MemberJoined`, `MemberRemoved`, `HeldFundsReleased`,
    `CheckoutExpired`, `WebhookTestReceived`) then `WebhookReceived` for every delivery;
    `TransactionWebhookReceived` and the 2.x payloads of `SubscriptionBilled` /
    `SubscriptionStatusChanged` are gone. The `qbitflow.webhook` middleware verifies locally with
    `QBITFLOW_WEBHOOK_SECRET` (required; no API fallback), answers 400 to a bad signature or a
    non-v2 body, and no longer short-circuits the dashboard's test (it is a `webhook.test`
    event, dispatched as `WebhookTestReceived`). `qbitflow:verify` calls `me()`.
-   **Renamed inside 3.0.0's preparation** (before API v2's final contract; nothing was
    published): `checkoutSessions->getStatus()` → `get()`; `payments->listCombined()` /
    `iterateCombined()` → `transactions->list()` / `iterate()` (`CombinedPayment` →
    `Transaction`, `CombinedPaymentListParams` → `TransactionListParams`, its `source` filter →
    `type`); `subscriptions->executeTestBilling()` → `testBill()`; `refunds->initiate()` is
    removed (`InitiateRefundParams` with it: refunds are read only, below); `refunds->list()` (active) and
    `listInactive()` / `iterateInactive()` → one `refunds->list()` / `iterate()` with a
    `status` filter; `wallets->listSupportedCurrencies()` → `listAcceptedCurrencies()`
    (`SupportedCurrenciesParams` → `AcceptedCurrenciesParams`); `currencies->listAvailable()` /
    `listMain()` → `currencies->list(new CurrencyListParams(main: …))`;
    `webhooks->endpoints->list()` takes `?EndpointListParams` and returns a `Page`;
    `subscriptions->getPublicHistory()` is removed (`listBills()` has every bill).

### Added

-   `$client->me()`: what the key is (role, space, mode, the member acted for), the recommended
    start-up check; `$client->onBehalfOf()`: a client acting in a member's space, sharing the
    transport. `MeSpace` has `role`, `trustedAt`, `createdAt` and `organizationFeePercent`.
-   `checkoutSessions`: `createPayment`, `createSubscription` (with `expiresInMinutes`,
    `metadata` and the `{{UUID}}` / `{{TRANSACTION_TYPE}}` redirect placeholders), `get`,
    `expire`.
-   **Checkout fees**: `CreatePaymentSessionParams(fees: new CheckoutFees(…))` and
    `CreateSubscriptionSessionParams(fees: …)` add your lines (`FeeItem`: `label`, `amountUsd`
    as a decimal string, optional `description`; at most 10) and QBitFlow's processing fee
    (`processingFee: true`, grossed up so you keep the price and your lines; `null` follows the
    dashboard setting `checkout.customerPaysProcessingFee`) to the price. Checked before the
    request (label 1-40 on one line, description at most 200, `amountUsd` above 0, at most
    1,000,000, 2 decimals) on `fees.items[<i>].<field>`. The customer pays the price plus every
    line, then the network fee on top; test mode caps that amount, fees included, at `"5"`. A
    subscription's are locked with its price: every bill charges both. `Payment::$priceUsd` and
    `$fees`, `Subscription::$fees`, `Transaction::$priceUsd` and `$fees`, `Models\FeeLine` and the
    open enum `Enums\FeeLineType` (`CUSTOM`, `PROCESSING_FEE`).
-   Merchant metadata (`array<string,string>`) on `CreateProductParams` / `UpdateProductParams`,
    `CreateCustomerParams` / `UpdateCustomerParams`, both session creates, and
    `subscriptions->update($uuid, UpdateSubscriptionParams)` (`PATCH /subscriptions/:uuid`):
    merged on the updates, a key set to `''` deleting it.
-   `$client->transactions` (`TransactionsService`): `list()` / `iterate()`
    (`GET /transactions`), the feed of payments and bills, each a `Transaction` with its `type`,
    filtered by `type`, `subscriptionUuid`, `refunded` and the shared filters.
-   `payments->list()` with filters (customer, product, dates, `reference`, `refunded`,
    `includeMembers` / `userUuid`); `payments->get()` with `ReadParams`. `failures->list()`: the
    failed payment attempts (`attempted`, `attemptedUsd`, `currency`).
-   `products->list()` / `iterate()` (paginated: `limit`, `cursor`, `includeHidden`,
    `subscription`, `reference`) and subscription products; `customers->list()` / `iterate()`
    (`email`, `reference`, `verified`).
-   `subscriptions->list()` (status, reference and the shared filters), `update()`,
    `listBills()`, `getBill()` (`GET /bills/:uuid`); `Subscription::$currentPeriodEnd` (grant
    access while now is before it), `actionRequired`, `priceUsd`, `cancellationReason`,
    `dunning`, `metadata`, `managementPageLink`; `Allowance`.
-   `refunds->list()` / `iterate()` (`status` (a list, sent repeated, up to 5), `txUuid`,
    `held`, `includeMembers`, `userUuid`) and `refunds->get()` (`GET /refunds/:uuid`), read
    only: a customer's request is answered, and a merchant's own refund sent, from the dashboard
    (the merchant's is recorded only once its transfer confirmed, `approved`: `refund.approved` is
    its only event). A `Refund` carries `paid`, `amount`, its `currency` and,
    once approved, its `settlement`.
-   `members` (`list`, `iterate`, `get`, `update`, `remove`, `trust`, `listHeldFunds`,
    `getHeldFunds`, `getOwnHeldFunds`) and `invitations` (`create`, `list`, `iterate`,
    `revoke`) for marketplaces. `CreateInvitationParams(sendEmail: …)` (null = emailed): `false`
    creates the invitation without emailing it, to share its `link` yourself.
-   `Enums\MerchantNotReadyReason` (`details['reason']` of a `409 merchant_not_ready`):
    `NO_WALLET`, `ORGANIZATION_NO_WALLET`, `NO_TOKEN_WALLET`, and `FUNDS_HELD` (`fundsHeld`), a
    subscription checkout of a member whose funds the organization holds (a held member sells
    one-time payments only, until `members->trust()`).
-   `wallets` (`list` with balances, `listForMember`, `listAcceptedCurrencies`),
    `accounting->exportCsv()`, `currencies->list()` and `get()`.
-   `Models\TokenAmount` (`amount`, `amountMinUnits`, `amountUsd`), the shape of every token
    amount.
-   `webhooks->endpoints` (`list` / `iterate` paginated, `create` with the secret shown once,
    `get`, `update`, `delete`) and `webhooks->events` (the event log: `list`, `iterate`, `get`
    with its deliveries).
-   Webhook verification without a client: `Webhook::verify()`, `Webhook::verifyRequest()` (a
    PSR-7 request, 1 MiB at most), `Webhook::constructEvent()`, `Webhook::parseEvent()`, with a
    tolerance and an injectable clock; every `v1=` signature is checked, so a secret rotation
    needs nothing on the receiver's side. The header constants `Webhook::SIGNATURE_HEADER`,
    `EVENT_ID_HEADER`, `EVENT_TYPE_HEADER`, `VERSION_HEADER`.
-   Typed events: `Events\Event::fromArray()` returns one `final readonly` subclass per type
    (`PaymentCompletedEvent`, `SubscriptionBilledEvent`, `RefundApprovedEvent`,
    `CheckoutExpiredEvent`, … 15 types) with a typed `data`, or an `UnknownEvent` keeping the
    raw data; `Event::decodeData()` for another shape.
-   A `Generator` next to every paginated list: `iterate()`, `iterateBills()`.
-   Retries: reads and the 6 creates are retried on network errors, timeouts, 5xx, 429 (after
    `Retry-After`, at most 60 s) and `409 idempotency_key_in_use`, 3 times by default with a
    1 s · 2ⁿ back-off; `maxRetries: 0` disables them; `QBitFlowException::isRetryable()`.
-   An `Idempotency-Key` on each create, generated per call and reused by its retries;
    `RequestOptions(idempotencyKey: …)` for retries across processes.
-   `RequestOptions(requestId: …)` (`X-Request-Id`) and the request id on every exception.
-   Client-side validation mirroring the API v2 validators, through each params class's
    `validate()`, run before every request (no request is sent on failure): names, texts,
    references (also on the `reference` filters and `getByReference()`), emails (also the
    customers' `email` filter), phones, URLs, USD amounts as strings (at most 2 decimals, above
    0, at most 1,000,000, compared exactly), percents as strings (at most 2 decimals and 16
    characters; `organizationFeePercent` 0 to 50, required on
    `UpdateMemberParams`), metadata (at most 50 keys of 1 to 40 of `A-Z a-z 0-9 _ - . :`, values
    of at most 500 characters without control or bidirectional characters), durations, cursors
    (at most 200 characters), the refunds' `status` filter (at most 5 known statuses), the
    transactions' `type`, the `release@` transaction prefix, and a clearable
    `UpdateCustomerParams::$lastName`.
-   Laravel: `webhook_secret` and `webhook_tolerance` config keys (`QBITFLOW_WEBHOOK_SECRET`,
    `QBITFLOW_WEBHOOK_TOLERANCE`); the verified event on `$request->attributes`
    (`qbitflow.event`); facade methods for every service (`transactions()` included) and `me()`.
-   `User-Agent: qbitflow-php/3.0.0` and `QBitFlow::VERSION`.
-   **Integration helpers** (the same in the Go, JavaScript, Python and PHP SDKs):
    -   `Webhooks\WebhookRouter` (also `$client->webhooks->router($secret)`): verifies, parses and
        dispatches a delivery to the handlers of its type with typed data (`on(EventType::…,
        fn (PaymentCompleted $data, Event $event) => …)`, several per type, chaining),
        `onUnknown()`, `onAny()`, `onError()`; `handle($rawBody, $header)` returns a
        `WebhookResult` (`status`, `event`, `error`, `responseBody()`): 200 handled or ignored,
        400 bad signature or not a v2 event, 500 when a handler throws (the later ones skipped).
        Adapters: `handleGlobals()` (plain PHP), `handleRequest()` (PSR-7 in and out, PSR-17
        factories auto-detected or `withResponseFactory()`), `psr15()` (a PSR-15
        `Psr15WebhookHandler`); 405 unless POST, 413 over 1 MiB (also from `Content-Length`),
        bodies `{"received":true}` / `{"error":"…"}`. `dispatch($event)` for an event verified
        elsewhere. New dependency: `psr/http-server-handler` ^1.0 (the PSR-15 interface).
    -   `Webhook::sign($rawBody, $secret, $timestamp = now)`: the `QBitFlow-Signature` header
        QBitFlow would send, to test your handlers.
    -   `checkoutSessions->waitForCompletion($uuid, timeout: 600, interval: 3)`: polls `get()`
        until `completed` or `expired`, or returns the last status seen at the timeout; uses the
        client's sleep.
    -   `Subscription::hasAccess(?DateTimeInterface $at = null)` (also on the subscription
        webhook data): `currentPeriodEnd` set and `$at` before it.
    -   `Support\Amount::format()` / `parse()`: exact min units ↔ decimal strings by string
        arithmetic (no extension needed); `Currency::formatAmount($minUnits)`.
    -   `accounting->exportJsonRange()` / `exportCsvRange()`: any date range, split into windows
        of at most 92 days, both ends included (`[from, from+91d]`), and joined (the CSV header
        once).
    -   `QBitFlow::fromEnv(...)`: `QBITFLOW_API_KEY` (required), `QBITFLOW_BASE_URL`,
        `QBITFLOW_ON_BEHALF_OF` from `getenv()` / `$_ENV` / `$_SERVER`; named arguments win.
    -   `Placeholders::UUID` and `Placeholders::TRANSACTION_TYPE` for the redirect URLs.
    -   Laravel: the `WebhookController` dispatches through a `WebhookRouter` (bound in the
        container on `QBITFLOW_WEBHOOK_SECRET`), still one Laravel event per type then
        `WebhookReceived`; it also verifies on its own when used without the middleware, and a
        throwing listener is a 500 `{"error":"internal error"}` reported to the exception
        handler. The middleware answers `{"error":"invalid signature"}` /
        `{"error":"invalid event"}` (400) and 413 over 1 MiB.
-   Examples: `checkout.php`, `subscriptions.php`, `marketplace.php`, `refunds.php`,
    `webhook-handler.php`, `errors-and-retries.php`, `laravel/`; `MIGRATION-v3.md`.
-   Website snippets: three new ids, `transactions-list`, `subscriptions-update` and
    `refunds-get`; none renamed. `refunds-create` is listed as unsupported (refunds are read only;
    the id stays in the website's catalog, marked retired).

### Changed

-   `AccountingEvent` follows the API's export: amounts in the currency's units as decimal
    strings, each with its `…MinUnits` twin (`grossAmountMinUnits`, `platformFeeMinUnits`,
    `referralFeeMinUnits`, `organizationFeeMinUnits`, `netAmountMinUnits`,
    `networkFeesMinUnits`); USD amounts and rates as decimal strings; `currency` (was
    `tokenSymbol`), `currencyPriceUsd`, and the network fees apart from the net
    (`networkFeesPaidBy`, the open enum `Enums\NetworkFeesPayer`: `PAYER`, `MERCHANT`,
    `ORGANIZATION`). `paymentReference` / `relatedPaymentReference` → `reference` /
    `relatedReference`, `paymentId` / `relatedPaymentId` → `txUuid` / `relatedTxUuid`; a fee
    that doesn't apply is null (was `"0"`). Its properties are in the CSV's column order; the
    always-empty `userName`, `userLastName`, `customerName` and `customerLastName` are gone. The
    export window is at most 92 days, both ends included (`AccountingService::MAX_WINDOW_DAYS`).
-   Responses decode leniently: a field the API leaves out or sends as `null` gets its zero
    value; a value of the wrong JSON type (a JSON number where a decimal string is expected, an
    empty list where an object or a map is expected, a non-string `metadata` value included) is
    a `ServerException`. Unknown enum values are kept as is, decimal strings are never parsed,
    timestamps keep the offset the API sends. Every answer is a JSON object: a `null` body is a
    `ServerException`.
-   Redirects are never followed: a 3xx is a `ServerException`.
-   A 2xx with an empty or non-JSON body where a result is expected is a `ServerException`.
-   A request body JSON cannot represent (NaN, ±INF, invalid UTF-8) is a `ValidationException`
    naming the field, and nothing is sent.
-   Update params send an explicit `''` for the clearable fields
    (`UpdateCustomerParams::$lastName` / `$phoneNumber` / `$address`,
    `UpdateProductParams::$description`, `UpdateWebhookEndpointParams::$description`): `''`
    clears them, `null` leaves them.
-   The event log's cursor is opaque (was an event id); the subscriptions' `status` and the
    event log's `type` filters are sent as given (the API checks them).
-   `CustomerSummary::$deleted` and `DeliveryAttempt::$skipped` are `bool`s.
-   Exception messages read `<message> (status <status>, code <code>, request <requestId>)`,
    followed by each field error.

### Removed

-   `users`, `claims`, `apiKeys`, `transactionStatus`, `oneTimePayments->getSession()`,
    `subscriptions->getSession()`, `oneTimePayments->getCustomerForTransaction()` (read
    `Payment::$customer`, or `customers->get($payment->customerUuid)`),
    `refunds->getByTransaction()` (read `Payment::$refund` / `Bill::$refund`, or
    `refunds->list(new RefundListParams(txUuid: …))`), `customers->getByEmail()`
    (`customers->list()` with `email`), `subscriptions->getPaymentHistory()` and the public bill
    history (the customer's page; `listBills()` has every bill), `accounting->export($from,
    $to, $format)` (use `exportJson()` / `exportCsv()`).
-   The v1 webhook verifier: `WebhookVerifier` (`verify`, `computeSignature`, `canonicalJson`,
    `extractHeaders`), the `X-Webhook-*` header constants, `TEST_WEBHOOK_ID`,
    `webhooks->isTestWebhook()` and its header getters, and the v1 payloads
    `SessionWebhookResponse` and `SubscriptionStatusTransition`.
-   `Dto\Session\SessionCheckout`, `OneTimePaymentSession`, `SubscriptionSession`,
    `PaygSubscriptionSession`, `StatusLinkResponse`, `Dto\StatusResponse`, `SuccessResponse`,
    `Organization`, `ApiKey`, `ClaimFunds`, `ClaimRequestResponse`, `Support\Dto`,
    `Support\CursorData`, `Support\Validate`, `Config`, the enums `ExportFormat`,
    `SubscriptionWebhookType`, `TransactionShortType`, `TransactionStatusValue`; `legacyId` is
    not modelled.

### Fixed

-   **Field validation errors were read from the wrong key** (a top-level `errors` list instead
    of the API's `details.errors`), so every field of a 400 came back empty. `fieldErrors` now
    names each failing input by the name sent, dotted when nested (`frequency.unit`).
-   User-supplied path segments are escaped, a `.` or `..` segment included, so an id can no
    longer address another route.

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
