<?php

declare(strict_types=1);

namespace QBitFlow\Dto;

use DateTimeImmutable;
use QBitFlow\Enums\SubscriptionStatus;
use QBitFlow\Support\Cast;
use QBitFlow\Support\Dto;

/**
 * A subscription status change, delivered by the subscription webhook.
 *
 * @see \QBitFlow\Laravel\Events\SubscriptionStatusChanged
 */
final class SubscriptionStatusTransition extends Dto
{
	public function __construct(
		/** UUID of the subscription that changed status. */
		public readonly string $subscriptionUUID,
		/** Status before the transition. */
		public readonly SubscriptionStatus $previousStatus,
		/** Status after the transition. */
		public readonly SubscriptionStatus $currentStatus,
		/** When the transition occurred. */
		public readonly DateTimeImmutable $updatedAt,
		/** Your own reference for the subscription, when one was set at creation. */
		public readonly ?string $subscriptionReference = null,
	) {
	}

	/**
	 * @param array<string,mixed> $data
	 */
	public static function fromArray(array $data): self
	{
		return new self(
			Cast::string($data, 'subscriptionUUID'),
			Cast::enum($data, 'previousStatus', SubscriptionStatus::class, SubscriptionStatus::ACTIVE),
			Cast::enum($data, 'currentStatus', SubscriptionStatus::class, SubscriptionStatus::ACTIVE),
			Cast::date($data, 'updatedAt'),
			Cast::nullableString($data, 'subscriptionReference'),
		);
	}
}
