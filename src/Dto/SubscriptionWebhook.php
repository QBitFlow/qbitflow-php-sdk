<?php

declare(strict_types=1);

namespace QBitFlow\Dto;

use QBitFlow\Enums\SubscriptionWebhookType;
use QBitFlow\Support\Cast;
use QBitFlow\Support\Dto;

/**
 * Payload delivered to your **subscription** webhook URL: an existing subscription changed
 * status, or was billed for a new period.
 *
 * The subscription identity lives on this envelope; `data` holds the event itself and is
 * typed by `type`:
 *
 * | `type`              | `data`                                 |
 * |---------------------|----------------------------------------|
 * | `status_transition` | {@see SubscriptionStatusTransition}    |
 * | `billing`           | {@see SubscriptionHistory}             |
 * | anything else       | the raw decoded array, left untouched  |
 *
 * ```php
 * $event = SubscriptionWebhook::fromArray(json_decode($rawBody, true));
 *
 * if ($event->data instanceof SubscriptionHistory) {
 *     recordRenewal($event->subscriptionUUID, $event->data);
 * }
 * ```
 */
final class SubscriptionWebhook extends Dto
{
	/**
	 * @param SubscriptionStatusTransition|SubscriptionHistory|array<array-key,mixed> $data
	 */
	public function __construct(
		/** UUID of the subscription, `sub@`-prefixed. */
		public readonly string $subscriptionUUID,
		/**
		 * What happened. A `SubscriptionWebhookType` member, or the raw string for an event
		 * kind this SDK does not know (whose `data` then stays a raw array).
		 */
		public readonly SubscriptionWebhookType|string $type,
		/** The event payload; see the class documentation. */
		public readonly SubscriptionStatusTransition|SubscriptionHistory|array $data,
		/** Your own reference for the subscription; `''` when you set none. */
		public readonly string $subscriptionReference = '',
	) {
	}

	/**
	 * @param array<array-key,mixed> $data
	 *
	 * @throws \QBitFlow\Exceptions\ServerException When the payload does not have the webhook shape.
	 */
	public static function fromArray(array $data): self
	{
		return Cast::one($data, static function (array $body): self {
			$type = Cast::enum($body, 'type', SubscriptionWebhookType::class);

			$payload = match ($type) {
				SubscriptionWebhookType::STATUS_TRANSITION => Cast::object(
					$body,
					'data',
					SubscriptionStatusTransition::fromArray(...),
				),
				SubscriptionWebhookType::BILLING => Cast::object($body, 'data', SubscriptionHistory::fromArray(...)),
				default => is_array($body['data'] ?? null) ? $body['data'] : [],
			};

			return new self(
				Cast::string($body, 'subscriptionUUID'),
				$type,
				$payload,
				Cast::string($body, 'subscriptionReference'),
			);
		});
	}

	/** Whether this event reports a status change. */
	public function isStatusTransition(): bool
	{
		return $this->data instanceof SubscriptionStatusTransition;
	}

	/** Whether this event reports a successful billing. */
	public function isBilling(): bool
	{
		return $this->data instanceof SubscriptionHistory;
	}
}
