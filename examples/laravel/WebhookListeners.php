<?php

/**
 * Listeners for the three events the webhook routes dispatch.
 *
 * Register them in your EventServiceProvider:
 *
 *   protected $listen = [
 *       TransactionWebhookReceived::class  => [MarkOrderPaid::class],
 *       SubscriptionBilled::class          => [RecordRenewal::class],
 *       SubscriptionStatusChanged::class   => [ReactToStatusChange::class],
 *   ];
 *
 * Queue them. The route answers 200 as soon as the event is dispatched, so slow work in a
 * synchronous listener risks a timeout — and a redelivery.
 */

declare(strict_types=1);

namespace App\Listeners;

use App\Models\Order;
use App\Models\Renewal;
use App\Models\Subscription;
use Illuminate\Contracts\Queue\ShouldQueue;
use QBitFlow\Enums\SubscriptionStatus;
use QBitFlow\Enums\TransactionStatusValue;
use QBitFlow\Laravel\Events\SubscriptionBilled;
use QBitFlow\Laravel\Events\SubscriptionStatusChanged;
use QBitFlow\Laravel\Events\TransactionWebhookReceived;
use QBitFlow\Support\Enums;

/**
 * A checkout was completed: a payment was paid, or a subscription was started.
 */
final class MarkOrderPaid implements ShouldQueue
{
    public function handle(TransactionWebhookReceived $event): void
    {
        if ($event->payload->status?->status !== TransactionStatusValue::COMPLETED) {
            return;
        }

        // reference() is the order ID you set when creating the session ('' when none).
        $order = Order::find($event->reference());

        if ($order === null) {
            return;
        }

        if ($event->isSubscription()) {
            // The first period has already been billed at this point.
            $order->startSubscription($event->payload->session->uuid);

            return;
        }

        $order->markPaid($event->payload->status->txHash);
    }
}

/**
 * An active subscription renewed for a new period.
 */
final class RecordRenewal implements ShouldQueue
{
    public function handle(SubscriptionBilled $event): void
    {
        Renewal::create([
            'subscription_uuid' => $event->subscriptionUUID,        // from the webhook envelope
            'order_id' => $event->reference() ?: null,              // '' when you set no reference
            'amount_usd' => $event->billing->amount,
            'currency' => $event->billing->currency->symbol,
            'tx_hash' => $event->billing->transactionHash,
            'billed_at' => $event->billing->createdAt,
        ]);
    }
}

/**
 * A subscription changed status. Listening for this beats polling on a cron.
 */
final class ReactToStatusChange implements ShouldQueue
{
    public function handle(SubscriptionStatusChanged $event): void
    {
        // The subscription identity is on the event (it comes from the webhook envelope);
        // the transition itself only holds the two statuses and the timestamp.
        $subscription = Subscription::firstWhere('uuid', $event->subscriptionUUID);

        // A status this SDK does not know yet arrives as a raw string rather than an enum
        // member; Enums::value() renders either, and the match below simply falls through.
        $subscription?->update(['status' => Enums::value($event->transition->currentStatus)]);

        match ($event->transition->currentStatus) {
            // The next billing may fail — ask the subscriber to top up their allowance.
            SubscriptionStatus::LOW_ON_FUNDS,
            SubscriptionStatus::PAST_DUE => $subscription?->notifyPaymentProblem(),

            SubscriptionStatus::CANCELLED => $subscription?->revokeAccess(),

            default => null,
        };
    }
}
