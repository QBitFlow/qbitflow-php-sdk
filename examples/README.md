# Examples

Runnable with an API key (and, for a non-production server, `QBITFLOW_BASE_URL`), from the SDK's
root; arguments in brackets are optional (a `pay@…`, `sub@…` or member id, or a flag for a write):

```bash
composer install
QBITFLOW_API_KEY=sk_… php examples/checkout.php
```

The checkout examples use the order references `order-1042`, `order-1043` and `order-1044` and
expire the sessions they open where they can, so they run again. The `// docs:start <id>` regions are the code
shown on qbitflow.app/docs and in the README (see [CONTRIBUTING.md](../CONTRIBUTING.md)), listed in
[`snippets.manifest.json`](snippets.manifest.json).

| Example | Shows |
|---|---|
| [`client-setup.php`](client-setup.php) | a client from the API key and `me()` (default server), and from the environment |
| [`checkout.php`](checkout.php) | a payment checkout, one with fees (a shipping line and the processing fee), its status, waiting for it (`--wait`), expiry |
| [`catalog.php`](catalog.php) | create and list products, one page of customers, a checkout for a product by its reference |
| [`payments.php`](payments.php) | list payments with a filter, get one by id and by reference, the iterator, exact amounts |
| [`subscriptions.php`](subscriptions.php) | a subscription checkout with a trial, past-due subscriptions, access, bills, a test bill (`--bill`), cancel at period end (`--cancel`) |
| [`refunds.php`](refunds.php) | list the active refunds, refund half of a payment |
| [`marketplace.php`](marketplace.php) | invite a seller, invitations, members, `onBehalfOf`, wallets, held funds, fee, trust (`--trust`), remove (`--remove`) |
| [`webhooks.php`](webhooks.php) | create a webhook endpoint (`--create`), the event log |
| [`webhook-handler.php`](webhook-handler.php) | a plain-PHP receiver with the webhook router: typed handlers, the right answers (`php -S`, `QBITFLOW_WEBHOOK_SECRET`) |
| [`webhook-verify.php`](webhook-verify.php) | the lower level: `Webhook::constructEvent()` on the raw body (`php -S`, `QBITFLOW_WEBHOOK_SECRET`) |
| [`errors-and-retries.php`](errors-and-retries.php) | exception types, `isRetryable()`, retries and idempotency keys across processes |
| [`accounting.php`](accounting.php) | a year of accounting events as JSON and CSV |
| [`currencies.php`](currencies.php) | the currencies customers can pay with |
| [`laravel/`](laravel) | the webhook route, a checkout controller with the facade, queued listeners |
