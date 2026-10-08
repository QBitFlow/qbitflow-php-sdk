<?php

declare(strict_types=1);

namespace QBitFlow\Laravel\Http\Controllers;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use LogicException;
use QBitFlow\Events;
use QBitFlow\Events\Event;
use QBitFlow\Laravel\Events as LaravelEvents;
use QBitFlow\Laravel\Http\Middleware\VerifyQBitFlowWebhook;

/**
 * Turns verified webhook deliveries into Laravel events: the event of the webhook's type
 * ({@see LaravelEvents\PaymentCompleted}, …), then {@see LaravelEvents\WebhookReceived} for every
 * delivery (unknown types included).
 *
 * It answers 200 to every verified delivery, the ignored types included (anything else is
 * retried): do the work in queued listeners, and deduplicate on `$event->id` (deliveries are
 * at least once). Reached through `Route::qbitflowWebhooks()`, behind
 * {@see VerifyQBitFlowWebhook}.
 */
final class WebhookController
{
	/** Each SDK event class and the Laravel event dispatched for it. */
	public const EVENTS = [
		Events\PaymentCompletedEvent::class => LaravelEvents\PaymentCompleted::class,
		Events\SubscriptionCreatedEvent::class => LaravelEvents\SubscriptionCreated::class,
		Events\SubscriptionBilledEvent::class => LaravelEvents\SubscriptionBilled::class,
		Events\SubscriptionStatusChangedEvent::class => LaravelEvents\SubscriptionStatusChanged::class,
		Events\SubscriptionActionRequiredChangedEvent::class => LaravelEvents\SubscriptionActionRequiredChanged::class,
		Events\SubscriptionBillingFailedEvent::class => LaravelEvents\SubscriptionBillingFailed::class,
		Events\SubscriptionUpcomingBillEvent::class => LaravelEvents\SubscriptionUpcomingBill::class,
		Events\RefundRequestedEvent::class => LaravelEvents\RefundRequested::class,
		Events\RefundCompletedEvent::class => LaravelEvents\RefundCompleted::class,
		Events\RefundDeniedEvent::class => LaravelEvents\RefundDenied::class,
		Events\MemberJoinedEvent::class => LaravelEvents\MemberJoined::class,
		Events\MemberRemovedEvent::class => LaravelEvents\MemberRemoved::class,
		Events\HeldFundsReleasedEvent::class => LaravelEvents\HeldFundsReleased::class,
		Events\CheckoutExpiredEvent::class => LaravelEvents\CheckoutExpired::class,
		Events\WebhookTestEvent::class => LaravelEvents\WebhookTestReceived::class,
	];

	public function __construct(private readonly Dispatcher $events)
	{
	}

	public function __invoke(Request $request): JsonResponse
	{
		$event = $request->attributes->get(VerifyQBitFlowWebhook::EVENT_ATTRIBUTE);
		if (! $event instanceof Event) {
			// Never act on an unverified body.
			throw new LogicException('The QBitFlow webhook route must use the qbitflow.webhook middleware (Route::qbitflowWebhooks() does).');
		}

		$typed = self::EVENTS[$event::class] ?? null;
		if ($typed !== null) {
			$this->events->dispatch(new $typed($event));
		}
		$this->events->dispatch(new LaravelEvents\WebhookReceived($event));

		return new JsonResponse(['received' => true]);
	}
}
