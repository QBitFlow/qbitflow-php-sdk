<?php

/**
 * A QBitFlow webhook endpoint in plain PHP, with no framework.
 *
 * Serve it locally and expose it with a tunnel. With your webhook secret it verifies
 * locally and needs no API key; without the secret it asks the API, which needs the key:
 *
 *   QBITFLOW_WEBHOOK_SECRET=whsec_... php -S 127.0.0.1:8001 examples/webhook-server.php
 *   QBITFLOW_API_KEY=your-test-key    php -S 127.0.0.1:8001 examples/webhook-server.php
 *   ngrok http 8001
 *
 * Then set the public URL in the dashboard under Settings → Webhooks. There are two
 * endpoints — Transaction and Subscription — each with separate Test and Live URLs. Point
 * both at this script; it tells them apart from the payload.
 *
 * In Laravel you would not write any of this: register the route macros instead, and
 * listen for the typed events. See the README.
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use QBitFlow\Dto\Session\SessionWebhookResponse;
use QBitFlow\Dto\SubscriptionHistory;
use QBitFlow\Dto\SubscriptionStatusTransition;
use QBitFlow\Dto\SubscriptionWebhook;
use QBitFlow\Enums\TransactionStatusValue;
use QBitFlow\Exceptions\QBitFlowException;
use QBitFlow\Exceptions\ValidationException;
use QBitFlow\QBitFlow;
use QBitFlow\Support\Enums;
use QBitFlow\Webhooks\WebhookVerifier;

/** Answer with a status code and a short JSON body, then stop. */
function respond(int $status, array $body): never
{
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($body, JSON_THROW_ON_ERROR);
    exit;
}

function log_line(string $message): void
{
    error_log('[qbitflow] ' . $message);
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    respond(405, ['error' => 'Method not allowed']);
}

// Verify the raw body. The signature covers a canonical rendering, so key order and
// whitespace do not matter — but decoding it into PHP arrays first would lose the
// difference between {} and [], so hand the SDK the bytes you received.
$raw = file_get_contents('php://input') ?: '';

// extractHeaders understands $_SERVER's HTTP_X_WEBHOOK_* spelling, getallheaders(), PSR-7
// headers and Laravel's $request->headers->all() — all case-insensitively.
$headers = WebhookVerifier::extractHeaders($_SERVER);

$signature = $headers['signature'];
$timestamp = $headers['timestamp'];

if ($signature === '' || $timestamp === '') {
    respond(400, ['error' => 'Missing webhook signature headers']);
}

// Verify authenticity.
//
// Local verification is the recommended default: it needs your webhook secret (from the
// QBitFlow dashboard) but makes no network call, so it is faster and keeps working when
// QBitFlow is unreachable. If you would rather not hold the secret at all, the remote path
// below asks QBitFlow to check the signature instead.
$secret = getenv('QBITFLOW_WEBHOOK_SECRET') ?: '';

if ($secret !== '') {
    try {
        // Note the argument order — timestamp before signature — hence the named arguments.
        // The replay window defaults to 5 minutes and must match the server's setting;
        // pass maxTimestampAgeSeconds to change it.
        WebhookVerifier::verify(secret: $secret, timestamp: $timestamp, signature: $signature, payload: $raw);
        log_line('Verified locally (no API round-trip).');
    } catch (ValidationException $e) {
        log_line('Rejected a delivery: ' . $e->getMessage());
        respond(400, ['error' => 'Invalid webhook signature']);
    }
} else {
    $apiKey = getenv('QBITFLOW_API_KEY') ?: '';

    if ($apiKey === '') {
        // A misconfiguration, not a bad delivery: answer non-2xx so it is redelivered.
        log_line('Set QBITFLOW_WEBHOOK_SECRET (local) or QBITFLOW_API_KEY (remote) to verify deliveries.');
        respond(500, ['error' => 'Webhook verification is not configured']);
    }

    try {
        $client = new QBitFlow($apiKey);
        // The API-backed call takes the signature before the timestamp.
        $verified = $client->webhooks->verify(payload: $raw, signature: $signature, timestamp: $timestamp);
    } catch (ValidationException $e) {
        if ($e->getStatusCode() !== null) {
            // The API answered with a status other than the "rejected" 400.
            log_line('QBitFlow could not verify the delivery: ' . $e->getMessage());
            respond(503, ['error' => 'Verification temporarily unavailable']);
        }

        // Raised locally, before any request: a body that is not a JSON object.
        log_line('Rejected a delivery: ' . $e->getMessage());
        respond(400, ['error' => 'Invalid webhook payload']);
    } catch (QBitFlowException $e) {
        // QBitFlow itself was unreachable, or the API key was refused. Answering non-2xx
        // makes QBitFlow retry the delivery — far better than dropping a real event. Local
        // verification avoids this failure mode entirely.
        log_line('Could not verify: ' . $e->getMessage());
        respond(503, ['error' => 'Verification temporarily unavailable']);
    }

    if (! $verified) {
        log_line('Rejected a delivery with an invalid signature.');
        respond(400, ['error' => 'Invalid webhook signature']);
    }

    log_line('Verified via the QBitFlow API.');
}

// The dashboard's "Test the endpoint" action sends a probe carrying fake data. It is
// checked only now, *after* verification: the probe is signed like any other delivery, so
// putting it through the same path proves the whole setup — a broken secret shows up as a
// failed test rather than a false success. Acknowledge it and skip normal processing.
if ($headers['isTest']) {
    log_line('Test probe received and verified — endpoint is reachable.');
    respond(200, ['message' => 'Test webhook received']);
}

$payload = json_decode($raw, true);

if (! is_array($payload)) {
    respond(400, ['error' => 'Malformed payload']);
}

try {
    // The subscription webhook carries a `type` discriminator; the transaction webhook does not.
    if (isset($payload['type'])) {
        handleSubscriptionEvent($payload);
    } else {
        handleTransactionEvent($payload);
    }
} catch (QBitFlowException $e) {
    // A verified delivery this SDK cannot read. Answer non-2xx so QBitFlow delivers it
    // again once the integration is fixed, rather than dropping it.
    log_line('Could not read a verified delivery: ' . $e->getMessage());
    respond(500, ['error' => 'Unreadable payload']);
}

// Always acknowledge. Anything other than a 2xx makes QBitFlow retry.
respond(200, ['received' => true]);

/**
 * A checkout you created was completed: a one-time payment was paid, or a subscription
 * checkout finished — in which case the first billing has already been taken.
 *
 * @param array<string,mixed> $payload
 */
function handleTransactionEvent(array $payload): void
{
    $event = SessionWebhookResponse::fromArray($payload);

    // `reference` is the order or invoice ID you set when creating the session ('' when
    // none was set) — the shortest path back to your own records.
    $reference = $event->session->reference !== '' ? $event->session->reference : '—';

    log_line(sprintf(
        'Transaction %s: %s (%s), reference=%s',
        $event->uuid,
        $event->status !== null ? Enums::value($event->status->status) : 'no status',
        Enums::value($event->txType),
        $reference,
    ));

    if ($event->status === null || $event->status->status !== TransactionStatusValue::COMPLETED) {
        return;
    }

    if ($event->isSubscription()) {
        log_line("Subscription started for {$reference}; first period already billed.");
        // startSubscription($reference, $event->session->uuid);
        return;
    }

    log_line("Payment settled for {$reference}, tx {$event->status->txHash}.");
    // markOrderPaid($reference, $event->status->txHash);
}

/**
 * An existing subscription changed status, or renewed successfully.
 *
 * @param array<string,mixed> $payload
 */
function handleSubscriptionEvent(array $payload): void
{
    // The subscription identity lives on the envelope; `data` is typed by `type`.
    $event = SubscriptionWebhook::fromArray($payload);
    $reference = $event->subscriptionReference !== '' ? $event->subscriptionReference : 'no reference';

    if ($event->data instanceof SubscriptionStatusTransition) {
        // Statuses hydrate to SubscriptionStatus members, or to the raw string for a value
        // this SDK does not know yet; Enums::value() prints either.
        log_line(sprintf(
            'Subscription %s (%s) moved %s → %s',
            $event->subscriptionUUID,
            $reference,
            Enums::value($event->data->previousStatus),
            Enums::value($event->data->currentStatus),
        ));
        // reactToStatusChange($event->subscriptionUUID, $event->data);
        return;
    }

    if ($event->data instanceof SubscriptionHistory) {
        log_line(sprintf(
            'Subscription %s (%s) renewed: $%.2f in %s, tx %s',
            $event->subscriptionUUID,
            $reference,
            $event->data->amount,
            $event->data->currency->symbol,
            $event->data->transactionHash,
        ));
        // recordRenewal($event->subscriptionUUID, $event->data);
        return;
    }

    // An event kind this SDK has not seen. Acknowledge rather than retry forever.
    log_line('Ignored an unrecognised subscription event: ' . Enums::value($event->type));
}
