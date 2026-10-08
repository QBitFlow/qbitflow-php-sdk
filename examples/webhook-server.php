<?php

/**
 * A QBitFlow webhook endpoint in plain PHP, with no framework.
 *
 * Serve it locally and expose it with a tunnel:
 *
 *   QBITFLOW_API_KEY=your-test-key php -S 127.0.0.1:8001 examples/webhook-server.php
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
use QBitFlow\Enums\SubscriptionWebhookType;
use QBitFlow\Enums\TransactionStatusValue;
use QBitFlow\Exceptions\QBitFlowException;
use QBitFlow\Exceptions\ValidationException;
use QBitFlow\QBitFlow;
use QBitFlow\Webhooks\WebhookVerifier;

$client = new QBitFlow(getenv('QBITFLOW_API_KEY') ?: '');

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

// The raw body is what was signed. Never decode and re-encode before verifying: a change
// in key order or whitespace invalidates the signature.
$raw = file_get_contents('php://input') ?: '';

// extractHeaders understands $_SERVER's HTTP_X_WEBHOOK_* spelling, getallheaders(), PSR-7
// headers and Laravel's $request->headers->all() — all case-insensitively.
$headers = WebhookVerifier::extractHeaders($_SERVER);

$signature = $headers['signature'];
$timestamp = $headers['timestamp'];

// The dashboard's "Test the endpoint" action sends a probe carrying fake data. Acknowledge
// it and skip normal processing.
if ($headers['isTest']) {
    log_line('Test probe received — endpoint is reachable.');
    respond(200, ['message' => 'Test webhook received']);
}

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
        // The replay window defaults to 5 minutes
        // (WebhookVerifier::DEFAULT_MAX_TIMESTAMP_AGE_SECONDS) and must match the server's
        // setting. Pass a different value as the fifth argument to change it.
        WebhookVerifier::verify($secret, $timestamp, $signature, $raw);
        log_line('Verified locally (no API round-trip).');
    } catch (ValidationException $e) {
        log_line('Rejected a delivery: ' . $e->getMessage());
        respond(400, ['error' => 'Invalid webhook signature']);
    }
} else {
    try {
        $verified = $client->webhooks->verify($raw, $signature, $timestamp);
    } catch (QBitFlowException $e) {
        // QBitFlow itself was unreachable. Answering non-2xx makes it retry the delivery,
        // which is what we want — far better than dropping a real event. Local
        // verification avoids this failure mode entirely.
        log_line('Could not verify (QBitFlow unreachable): ' . $e->getMessage());
        respond(503, ['error' => 'Verification temporarily unavailable']);
    }

    if (! $verified) {
        log_line('Rejected a delivery with an invalid signature.');
        respond(400, ['error' => 'Invalid webhook signature']);
    }

    log_line('Verified via the QBitFlow API.');
}

$payload = json_decode($raw, true);

if (! is_array($payload)) {
    respond(400, ['error' => 'Malformed payload']);
}

// The subscription webhook carries a `type` discriminator; the transaction webhook does not.
if (isset($payload['type'])) {
    handleSubscriptionEvent($payload);
} else {
    handleTransactionEvent($payload);
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

    log_line(sprintf(
        'Transaction %s: %s (%s), reference=%s',
        $event->uuid,
        $event->status->status->value,
        $event->txType->value,
        $event->session->reference ?? '—',
    ));

    if ($event->status->status !== TransactionStatusValue::COMPLETED) {
        return;
    }

    // `reference` is the order or invoice ID you set when creating the session — the
    // shortest path back to your own records.
    $reference = $event->session->reference;

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
    $type = SubscriptionWebhookType::tryFrom((string) ($payload['type'] ?? ''));
    $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];

    switch ($type) {
        case SubscriptionWebhookType::STATUS_TRANSITION:
            $transition = SubscriptionStatusTransition::fromArray($data);

            log_line(sprintf(
                'Subscription %s moved %s → %s',
                $transition->subscriptionUUID,
                $transition->previousStatus->value,
                $transition->currentStatus->value,
            ));
            // reactToStatusChange($transition);
            break;

        case SubscriptionWebhookType::BILLING:
            $billing = SubscriptionHistory::fromArray($data);

            log_line(sprintf(
                'Subscription %s renewed: $%.2f, tx %s',
                $billing->subscriptionUUID,
                $billing->amount,
                $billing->transactionHash,
            ));
            // recordRenewal($billing);
            break;

        default:
            // An event kind this SDK has not seen. Acknowledge rather than retry forever.
            log_line('Ignored an unrecognised subscription event: ' . (string) ($payload['type'] ?? ''));
    }
}
