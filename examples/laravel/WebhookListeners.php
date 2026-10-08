<?php

declare(strict_types=1);

namespace App\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use QBitFlow\Laravel\Events\CheckoutExpired;
use QBitFlow\Laravel\Events\PaymentCompleted;
use QBitFlow\Laravel\Events\SubscriptionStatusChanged;
use QBitFlow\Laravel\Events\WebhookReceived;

/**
 * Queued listeners for the QBitFlow webhook events (register them in your EventServiceProvider,
 * or let Laravel discover them). Deliveries are at least once: deduplicate on the event id.
 */
final class FulfilPaidOrder implements ShouldQueue
{
	public function handle(PaymentCompleted $event): void
	{
		if (! Cache::add('qbitflow-event-' . $event->event->id, true, now()->addDays(7))) {
			return; // a retry of an event already handled
		}
		Log::info('fulfil order', ['reference' => $event->data->reference, 'payment' => $event->data->uuid, 'usd' => $event->data->amount]);
	}
}

final class ReleaseExpiredOrder implements ShouldQueue
{
	public function handle(CheckoutExpired $event): void
	{
		Log::info('release order', ['reference' => $event->data->reference, 'session' => $event->data->uuid]);
	}
}

final class SyncSubscriptionAccess implements ShouldQueue
{
	public function handle(SubscriptionStatusChanged $event): void
	{
		$subscription = $event->data;
		$hasAccess = $subscription->hasAccess();
		Log::info('subscription', ['uuid' => $subscription->uuid, 'from' => $subscription->previousStatus, 'to' => $subscription->status, 'access' => $hasAccess]);
	}
}

final class StoreEveryWebhook implements ShouldQueue
{
	public function handle(WebhookReceived $event): void
	{
		// Every verified delivery, the types this SDK does not know included (UnknownEvent).
		Log::debug('qbitflow webhook', ['id' => $event->event->id, 'type' => $event->event->type, 'member' => $event->event->userUuid]);
	}
}
