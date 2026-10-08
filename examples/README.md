# Examples

Runnable with an API key (and, for a non-production server, `QBITFLOW_BASE_URL`):

```bash
composer install
QBITFLOW_API_KEY=sk_… php examples/checkout.php
```

| Example | Shows |
|---|---|
| [`checkout.php`](checkout.php) | a payment checkout, its status, expiry |
| [`subscriptions.php`](subscriptions.php) | a subscription checkout with a trial, past-due subscriptions, bills, cancel at period end |
| [`marketplace.php`](marketplace.php) | invite a seller, sell `onBehalfOf`, held funds, trust |
| [`webhook-handler.php`](webhook-handler.php) | a plain-PHP receiver with the webhook router: typed handlers, deduplication, the right answers (`php -S`) |
| [`errors-and-retries.php`](errors-and-retries.php) | exception types, `isRetryable()`, idempotency keys across processes |
| [`laravel/`](laravel) | the webhook route, a checkout controller with the facade, queued listeners |
