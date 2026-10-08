<?php

declare(strict_types=1);

namespace QBitFlow\Dto;

use DateTimeImmutable;
use QBitFlow\Enums\SubscriptionStatus;
use QBitFlow\Support\Cast;
use QBitFlow\Support\Dto;

/**
 * A recurring on-chain subscription.
 */
final class Subscription extends Dto
{
	public function __construct(
		/** Unique identifier for the subscription. */
		public readonly string $uuid,
		/** When the subscription was created. */
		public readonly DateTimeImmutable $createdAt,
		/** When the subscription was last updated. */
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
		/** Whether this is a test-mode subscription. */
		public readonly bool $test,
		/** UUID of the subscribing customer. */
		public readonly string $customerUUID,
		/** Billing frequency, in seconds. */
		public readonly int $frequency,
		/** Remaining on-chain allowance in USD, as a decimal string. */
		public readonly string $allowance,
		/** Current status. */
		public readonly SubscriptionStatus $subscriptionStatus,
		/** Whether the subscription is flagged to stop after the current period. */
		public readonly bool $stopped,
		/** When the next billing is scheduled. */
		public readonly DateTimeImmutable $nextBillingDate,
		/** Your own reference, set when the session was created. */
		public readonly ?string $reference = null,
		/** Full currency details. */
		public readonly ?Currency $currency = null,
		/** When the last billing occurred; null if never billed. */
		public readonly ?DateTimeImmutable $lastBillingDate = null,
		/** Earliest date the subscription may be cancelled, set when `minPeriods` was used. */
		public readonly ?DateTimeImmutable $minimumCancellationDate = null,
		/** Owning organization ID. Authenticated reads only. */
		public readonly ?int $organizationId = null,
		/** Owning user ID. Authenticated reads only. */
		public readonly ?int $userId = null,
	) {
	}

	/**
	 * @param array<string,mixed> $data
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
			Cast::bool($data, 'test'),
			Cast::string($data, 'customerUUID'),
			Cast::int($data, 'frequency'),
			Cast::string($data, 'allowance', '0'),
			Cast::enum($data, 'subscriptionStatus', SubscriptionStatus::class, SubscriptionStatus::ACTIVE),
			Cast::bool($data, 'stopped'),
			Cast::date($data, 'nextBillingDate'),
			Cast::nullableString($data, 'reference'),
			Cast::nested($data, 'currency', Currency::fromArray(...)),
			Cast::nullableDate($data, 'lastBillingDate'),
			Cast::nullableDate($data, 'minimumCancellationDate'),
			Cast::nullableInt($data, 'organizationId'),
			Cast::nullableInt($data, 'userId'),
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
}
