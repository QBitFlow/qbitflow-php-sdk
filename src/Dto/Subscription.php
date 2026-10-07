<?php

declare(strict_types=1);

namespace QBitFlow\Dto;

use DateTimeImmutable;
use QBitFlow\Enums\SubscriptionStatus;
use QBitFlow\Support\Cast;
use QBitFlow\Support\Dto;
use QBitFlow\Support\Time;

/**
 * A recurring on-chain subscription.
 *
 * `lastBillingDate` and `nextBillingDate` are always set: a date that was never set comes
 * back as Go's zero time (`0001-01-01T00:00:00Z`), which {@see Time::isZero()} detects —
 * or use {@see Subscription::hasBeenBilled()} / {@see Subscription::hasNextBilling()}.
 */
final class Subscription extends Dto
{
	public function __construct(
		/** Unique identifier for the subscription, `sub@`-prefixed. */
		public readonly string $uuid,
		/** When the subscription was created. */
		public readonly DateTimeImmutable $createdAt,
		/** When the subscription was last updated (e.g. when the next billing date moved). */
		public readonly DateTimeImmutable $updatedAt,
		/** Subscriber's address. */
		public readonly string $from,
		/** Merchant's receiving address for the selected currency. */
		public readonly string $to,
		/** Product being subscribed to. */
		public readonly int $productId,
		/** On-chain subscription hash. */
		public readonly string $subscriptionHash,
		/** Selected currency ID. */
		public readonly int $currencyId,
		/** The selected currency. */
		public readonly Currency $currency,
		/** Billing frequency, in seconds. */
		public readonly int $frequency,
		/** Remaining on-chain allowance in USD, as a decimal string. */
		public readonly string $allowance,
		/**
		 * Current status. A `SubscriptionStatus` member when this SDK knows the value, or
		 * the raw string for a status the API added later — see {@see \QBitFlow\Support\Enums}.
		 */
		public readonly SubscriptionStatus|string $subscriptionStatus,
		/** Whether the subscription is flagged to stop after the current period. */
		public readonly bool $stopped,
		/** When the last billing occurred; Go's zero time if never billed. */
		public readonly DateTimeImmutable $lastBillingDate,
		/** When the next billing is scheduled; Go's zero time when none is. */
		public readonly DateTimeImmutable $nextBillingDate,
		/** Whether this is a test-mode subscription. */
		public readonly bool $test,
		/** Owning organization ID. */
		public readonly int $organizationId,
		/** Owning user ID; `0` for an organization-level subscription. */
		public readonly int $userId,
		/** Your own reference, set when the session was created; null when none was set. */
		public readonly ?string $reference = null,
		/** Earliest date the subscription may be cancelled; null unless `minPeriods` was used. */
		public readonly ?DateTimeImmutable $minimumCancellationDate = null,
		/** UUID of the subscribing customer; null when no customer was attached. */
		public readonly ?string $customerUUID = null,
	) {
	}

	/**
	 * @param array<array-key,mixed> $data
	 */
	public static function fromArray(array $data): self
	{
		return new self(
			Cast::string($data, 'uuid'),
			Cast::date($data, 'createdAt'),
			Cast::date($data, 'updatedAt'),
			Cast::string($data, 'from'),
			Cast::string($data, 'to'),
			Cast::int($data, 'productId'),
			Cast::string($data, 'subscriptionHash'),
			Cast::int($data, 'currencyId'),
			Cast::object($data, 'currency', Currency::fromArray(...)),
			Cast::int($data, 'frequency'),
			Cast::string($data, 'allowance'),
			Cast::enum($data, 'subscriptionStatus', SubscriptionStatus::class),
			Cast::bool($data, 'stopped'),
			Cast::date($data, 'lastBillingDate'),
			Cast::date($data, 'nextBillingDate'),
			Cast::bool($data, 'test'),
			Cast::int($data, 'organizationId'),
			Cast::int($data, 'userId'),
			Cast::nullableString($data, 'reference'),
			Cast::nullableDate($data, 'minimumCancellationDate'),
			Cast::nullableString($data, 'customerUUID'),
		);
	}

	/** Whether the subscription is currently billing normally or in trial. */
	public function isActive(): bool
	{
		return in_array(
			$this->subscriptionStatus,
			[SubscriptionStatus::ACTIVE, SubscriptionStatus::TRIAL],
			true,
		);
	}

	/** Whether the subscription has been billed at least once. */
	public function hasBeenBilled(): bool
	{
		return ! Time::isZero($this->lastBillingDate);
	}

	/** Whether a next billing is scheduled. */
	public function hasNextBilling(): bool
	{
		return ! Time::isZero($this->nextBillingDate);
	}
}
