<?php

declare(strict_types=1);

namespace QBitFlow\Dto;

use DateTimeImmutable;
use QBitFlow\Enums\SubscriptionStatus;
use QBitFlow\Support\Cast;
use QBitFlow\Support\Dto;

/**
 * The `data` payload of a subscription webhook whose `type` is `status_transition`.
 *
 * The subscription identity lives on the envelope, not here — see
 * {@see \QBitFlow\Laravel\Events\SubscriptionStatusChanged}, which carries both.
 */
final class SubscriptionStatusTransition extends Dto
{
	public function __construct(
		/** Status before the transition. */
		public readonly SubscriptionStatus $previousStatus,
		/** Status after the transition. */
		public readonly SubscriptionStatus $currentStatus,
		/** When the transition occurred. */
		public readonly DateTimeImmutable $updatedAt,
	) {
	}

	/**
	 * @param array<string,mixed> $data The webhook's `data` object.
	 */
	public static function fromArray(array $data): self
	{
		return new self(
			Cast::enum($data, 'previousStatus', SubscriptionStatus::class, SubscriptionStatus::ACTIVE),
			Cast::enum($data, 'currentStatus', SubscriptionStatus::class, SubscriptionStatus::ACTIVE),
			Cast::date($data, 'updatedAt'),
		);
	}
}
