<?php

declare(strict_types=1);

namespace QBitFlow\Laravel\Http\Controllers;

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use QBitFlow\Enums\EventType;
use QBitFlow\Events;
use QBitFlow\Events\Event;
use QBitFlow\Laravel\Events as LaravelEvents;
use QBitFlow\Laravel\Http\Middleware\VerifyQBitFlowWebhook;
use QBitFlow\Webhooks\Webhook;
use QBitFlow\Webhooks\WebhookResult;
use QBitFlow\Webhooks\WebhookRouter;
use Throwable;

/**
 * Turns webhook deliveries into Laravel events, through a {@see WebhookRouter}: the event of the
 * webhook's type ({@see LaravelEvents\PaymentCompleted}, …), then
 * {@see LaravelEvents\WebhookReceived} for every delivery (unknown types included).
 *
 * Behind {@see VerifyQBitFlowWebhook} (as `Route::qbitflowWebhooks()` mounts it) it dispatches
 * the event the middleware verified; on its own it verifies the delivery with the router
 * (`QBITFLOW_WEBHOOK_SECRET`). It answers 200 to every verified delivery, the ignored types
 * included; 400 to a bad signature or body; 500 when a listener throws (reported to the
 * exception handler; QBitFlow retries). Do the work in queued listeners, and deduplicate on
 * `$event->id` (deliveries are at least once).
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

	/** Each event type and the Laravel event dispatched for it. */
	public const TYPES = [
		EventType::PAYMENT_COMPLETED => LaravelEvents\PaymentCompleted::class,
		EventType::SUBSCRIPTION_CREATED => LaravelEvents\SubscriptionCreated::class,
		EventType::SUBSCRIPTION_BILLED => LaravelEvents\SubscriptionBilled::class,
		EventType::SUBSCRIPTION_STATUS_CHANGED => LaravelEvents\SubscriptionStatusChanged::class,
		EventType::SUBSCRIPTION_ACTION_REQUIRED_CHANGED => LaravelEvents\SubscriptionActionRequiredChanged::class,
		EventType::SUBSCRIPTION_BILLING_FAILED => LaravelEvents\SubscriptionBillingFailed::class,
		EventType::SUBSCRIPTION_UPCOMING_BILL => LaravelEvents\SubscriptionUpcomingBill::class,
		EventType::REFUND_REQUESTED => LaravelEvents\RefundRequested::class,
		EventType::REFUND_COMPLETED => LaravelEvents\RefundCompleted::class,
		EventType::REFUND_DENIED => LaravelEvents\RefundDenied::class,
		EventType::MEMBER_JOINED => LaravelEvents\MemberJoined::class,
		EventType::MEMBER_REMOVED => LaravelEvents\MemberRemoved::class,
		EventType::HELD_FUNDS_RELEASED => LaravelEvents\HeldFundsReleased::class,
		EventType::CHECKOUT_EXPIRED => LaravelEvents\CheckoutExpired::class,
		EventType::WEBHOOK_TEST => LaravelEvents\WebhookTestReceived::class,
	];

	/**
	 * @param WebhookRouter $router The container's (bound by the service provider with `QBITFLOW_WEBHOOK_SECRET`).
	 */
	public function __construct(
		private readonly Dispatcher $events,
		private readonly WebhookRouter $router,
		private readonly ?ExceptionHandler $exceptions = null,
	) {
		foreach (self::TYPES as $type => $laravelEvent) {
			$router->on($type, function (mixed $data, Event $event) use ($laravelEvent): void {
				$this->events->dispatch(new $laravelEvent($event));
			});
		}
		$router->onAny(function (Event $event): void {
			$this->events->dispatch(new LaravelEvents\WebhookReceived($event));
		});
	}

	public function __invoke(Request $request): JsonResponse
	{
		$event = $request->attributes->get(VerifyQBitFlowWebhook::EVENT_ATTRIBUTE);
		if ($event instanceof Event) {
			$result = $this->router->dispatch($event); // verified by the middleware
		} else {
			$body = $request->getContent();
			$result = (int) $request->headers->get('Content-Length', '0') > Webhook::MAX_BODY_BYTES || strlen($body) > Webhook::MAX_BODY_BYTES
				? new WebhookResult(413)
				: $this->router->handle($body, (string) $request->headers->get(Webhook::SIGNATURE_HEADER, ''));
		}

		if ($result->status === 500 && $result->error !== null) {
			$this->report($result->error);
		}

		return new JsonResponse(json_decode($result->responseBody(), true), $result->status);
	}

	private function report(Throwable $error): void
	{
		try {
			$this->exceptions?->report($error);
		} catch (Throwable) {
			// Reporting must not change the answer.
		}
	}
}
