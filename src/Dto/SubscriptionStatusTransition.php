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
 * {@see SubscriptionWebhook}, and {@see \QBitFlow\Laravel\Events\SubscriptionStatusChanged}
 * which carries both.
 */
final class SubscriptionStatusTransition extends Dto
{
	public function __construct(
		/**
		 * Status before the transition. A `SubscriptionStatus` member, or the raw string for
		 * a status this SDK does not know — see {@see \QBitFlow\Support\Enums}.
		 */
		public readonly SubscriptionStatus|string $previousStatus,
		/** Status after the transition; same union as `previousStatus`. */
		public readonly SubscriptionStatus|string $currentStatus,
		/** When the transition occurred. */
		public readonly DateTimeImmutable $updatedAt,
	) {
	}

	/**
	 * @param array<array-key,mixed> $data The webhook's `data` object.
	 */
	public static function fromArray(array $data): self
	{
		return new self(
			Cast::enum($data, 'previousStatus', SubscriptionStatus::class),
			Cast::enum($data, 'currentStatus', SubscriptionStatus::class),
			Cast::date($data, 'updatedAt'),
		);
	}
}
