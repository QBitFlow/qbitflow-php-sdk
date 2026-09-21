# Examples

| File | What it shows |
| ---- | ------------- |
| [`client.php`](client.php) | A tour of the SDK outside any framework: products, customers, a payment session, a subscription, currency lookups, pagination, `onBehalfOf`, and an accounting export. |
| [`webhook-server.php`](webhook-server.php) | A webhook endpoint in plain PHP — signature verification, the dashboard test probe, and both webhook shapes. |
| [`laravel/routes.php`](laravel/routes.php) | Registering the webhook routes. |
| [`laravel/CheckoutController.php`](laravel/CheckoutController.php) | Turning an order into a checkout link, including the marketplace `onBehalfOf` variant. |
| [`laravel/WebhookListeners.php`](laravel/WebhookListeners.php) | Queued listeners for the three webhook events. |

## Running them

Use a **test** API key. Test-mode data is kept entirely separate from live mode, and every
action runs on blockchain testnets.

```bash
composer install

QBITFLOW_API_KEY=your-test-key php examples/client.php
```

For the webhook server, serve it and expose it with a tunnel so QBitFlow can reach it:

```bash
QBITFLOW_API_KEY=your-test-key php -S 127.0.0.1:8001 examples/webhook-server.php
ngrok http 8001
```

Then set the public URL in the dashboard under **Settings → Webhooks** and use the
"Test the endpoint" button to confirm it is reachable.

The Laravel files are illustrative rather than runnable — they reference models such as
`Order` that belong to your application. Copy them into `app/` and adjust.
