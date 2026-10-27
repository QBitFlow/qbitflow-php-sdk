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
		Events\RefundApprovedEvent::class => LaravelEvents\RefundApproved::class,
		Events\RefundRejectedEvent::class => LaravelEvents\RefundRejected::class,
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
		EventType::REFUND_APPROVED => LaravelEvents\RefundApproved::class,
		EventType::REFUND_REJECTED => LaravelEvents\RefundRejected::class,
		EventType::MEMBER_JOINED => LaravelEvents\MemberJoined::class,
		EventType::MEMBER_REMOVED => LaravelEvents\MemberRemoved::class,
		EventType::HELD_FUNDS_RELEASED => LaravelEvents\HeldFundsReleased::class,
		EventType::CHECKOUT_EXPIRED => LaravelEvents\CheckoutExpired::class,
		EventType::WEBHOOK_TEST => LaravelEvents\WebhookTestReceived::class,
	];

	/**
	 * Registers, on the router, a handler per known event type (dispatching its Laravel event)
	 * and an any-type handler (dispatching {@see LaravelEvents\WebhookReceived}).
	 *
	 * @param Dispatcher            $events     Laravel's event dispatcher.
	 * @param WebhookRouter         $router     The container's (bound by the service provider with `QBITFLOW_WEBHOOK_SECRET`).
	 * @param ExceptionHandler|null $exceptions Receives the exceptions listeners throw (the delivery is then answered 500).
	 */
	public function __construct(
		private readonly Dispatcher $events,
		private readonly WebhookRouter $router,
		private readonly ?ExceptionHandler $exceptions = null,
	) {
		foreach (array_keys(self::TYPES) as $type) {
			$router->on($type, function (mixed $data, Event $event): void {
				$laravelEvent = self::laravelEvent($event);
				if ($laravelEvent !== null) {
					$this->events->dispatch($laravelEvent);
				}
			});
		}
		$router->onAny(function (Event $event): void {
			$this->events->dispatch(new LaravelEvents\WebhookReceived($event));
		});
	}

	/**
	 * Answers a webhook delivery: dispatches the Laravel events of the event the middleware
	 * verified (or verifies the raw request itself when mounted without the middleware).
	 *
	 * @return JsonResponse 200 once every listener ran (unknown types included); 400 for a bad
	 *                      signature or body; 413 for a body over 1 MiB; 500 when a listener
	 *                      threw (reported, and QBitFlow retries).
	 */
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

	/** The Laravel event of an SDK event ({@see self::EVENTS}); null for a type without one. */
	private static function laravelEvent(Event $event): ?object
	{
		return match (true) {
			$event instanceof Events\PaymentCompletedEvent => new LaravelEvents\PaymentCompleted($event),
			$event instanceof Events\SubscriptionCreatedEvent => new LaravelEvents\SubscriptionCreated($event),
			$event instanceof Events\SubscriptionBilledEvent => new LaravelEvents\SubscriptionBilled($event),
			$event instanceof Events\SubscriptionStatusChangedEvent => new LaravelEvents\SubscriptionStatusChanged($event),
			$event instanceof Events\SubscriptionActionRequiredChangedEvent => new LaravelEvents\SubscriptionActionRequiredChanged($event),
			$event instanceof Events\SubscriptionBillingFailedEvent => new LaravelEvents\SubscriptionBillingFailed($event),
			$event instanceof Events\SubscriptionUpcomingBillEvent => new LaravelEvents\SubscriptionUpcomingBill($event),
			$event instanceof Events\RefundRequestedEvent => new LaravelEvents\RefundRequested($event),
			$event instanceof Events\RefundApprovedEvent => new LaravelEvents\RefundApproved($event),
			$event instanceof Events\RefundRejectedEvent => new LaravelEvents\RefundRejected($event),
			$event instanceof Events\MemberJoinedEvent => new LaravelEvents\MemberJoined($event),
			$event instanceof Events\MemberRemovedEvent => new LaravelEvents\MemberRemoved($event),
			$event instanceof Events\HeldFundsReleasedEvent => new LaravelEvents\HeldFundsReleased($event),
			$event instanceof Events\CheckoutExpiredEvent => new LaravelEvents\CheckoutExpired($event),
			$event instanceof Events\WebhookTestEvent => new LaravelEvents\WebhookTestReceived($event),
			default => null,
		};
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
