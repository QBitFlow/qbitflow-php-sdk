<?php

declare(strict_types=1);

namespace QBitFlow\Laravel\Http\Controllers;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use QBitFlow\Dto\Session\SessionWebhookResponse;
use QBitFlow\Dto\SubscriptionHistory;
use QBitFlow\Dto\SubscriptionStatusTransition;
use QBitFlow\Enums\SubscriptionWebhookType;
use QBitFlow\Laravel\Events\SubscriptionBilled;
use QBitFlow\Laravel\Events\SubscriptionStatusChanged;
use QBitFlow\Laravel\Events\TransactionWebhookReceived;

/**
 * Turns verified QBitFlow webhook deliveries into Laravel events.
 *
 * Reached through the route macros; signature verification has already happened in
 * {@see \QBitFlow\Laravel\Http\Middleware\VerifyQBitFlowWebhook} by the time these
 * actions run.
 *
 * Every action answers 200 — a non-2xx response makes QBitFlow retry the delivery, so
 * your own processing belongs in a queued listener rather than inline here.
 */
final class WebhookController
{
	public function __construct(private readonly Dispatcher $events)
	{
	}

	/**
	 * Handle a delivery to your **transaction** webhook URL: a checkout was completed.
	 */
	public function transaction(Request $request): JsonResponse
	{
		$payload = $this->payload($request);

		$this->events->dispatch(
			new TransactionWebhookReceived(SessionWebhookResponse::fromArray($payload)),
		);

		return new JsonResponse(['received' => true]);
	}

	/**
	 * Handle a delivery to your **subscription** webhook URL: a status change, or a
	 * successful renewal.
	 */
	public function subscription(Request $request): JsonResponse
	{
		$payload = $this->payload($request);

		$type = SubscriptionWebhookType::tryFrom((string) ($payload['type'] ?? ''));
		$data = is_array($payload['data'] ?? null) ? $payload['data'] : [];

		$reference = isset($payload['subscriptionReference'])
			? (string) $payload['subscriptionReference']
			: null;

		$event = match ($type) {
			SubscriptionWebhookType::STATUS_TRANSITION => new SubscriptionStatusChanged(
				SubscriptionStatusTransition::fromArray($data),
			),
			SubscriptionWebhookType::BILLING => new SubscriptionBilled(
				SubscriptionHistory::fromArray($data),
				$reference,
			),
			// An unrecognised type is acknowledged rather than retried forever; a new
			// event kind on the API should not wedge the endpoint.
			null => null,
		};

		if ($event !== null) {
			$this->events->dispatch($event);
		}

		return new JsonResponse(['received' => true]);
	}

	/**
	 * Decode the delivery body.
	 *
	 * @return array<string,mixed>
	 */
	private function payload(Request $request): array
	{
		$decoded = json_decode($request->getContent(), true);

		return is_array($decoded) ? $decoded : [];
	}
}
