<?php

declare(strict_types=1);

namespace QBitFlow\Dto\Session;

use QBitFlow\Enums\TransactionType;
use QBitFlow\Support\Cast;

/**
 * Checkout session for a recurring subscription.
 *
 * Note that the server reports durations here in **seconds**, unlike the structured
 * {@see \QBitFlow\Support\Duration} used when creating the session.
 */
final class SubscriptionSession extends SessionCheckout
{
	/**
	 * @param list<int> $availableCurrencies
	 */
	public function __construct(
		string $uuid,
		string $productName,
		string $description,
		float $price,
		string $organizationName,
		bool $test,
		array $availableCurrencies = [],
		?string $reference = null,
		?int $productId = null,
		?string $productReference = null,
		?string $successUrl = null,
		?string $cancelUrl = null,
		?TransactionType $txType = null,
		?int $organizationId = null,
		?int $feeBps = null,
		?int $organizationFeeBps = null,
		?int $userId = null,
		?string $userName = null,
		?string $customerUUID = null,
		?string $customerReference = null,
		/** Billing frequency, in seconds. For example 2592000 for 30 days. */
		public readonly int $frequency = 0,
		/** Trial period in seconds; null or 0 when there is no trial. */
		public readonly ?int $trialPeriod = null,
		/** Minimum number of billing periods the subscriber must complete. */
		public readonly ?int $minPeriods = null,
	) {
		parent::__construct(
			$uuid,
			$productName,
			$description,
			$price,
			$organizationName,
			$test,
			$availableCurrencies,
			$reference,
			$productId,
			$productReference,
			$successUrl,
			$cancelUrl,
			$txType,
			$organizationId,
			$feeBps,
			$organizationFeeBps,
			$userId,
			$userName,
			$customerUUID,
			$customerReference,
		);
	}

	/**
	 * @param array<string,mixed> $data
	 */
	public static function fromArray(array $data): self
	{
		return new self(
			...self::baseArguments($data),
			frequency: Cast::int($data, 'frequency'),
			trialPeriod: Cast::nullableInt($data, 'trialPeriod'),
			minPeriods: Cast::nullableInt($data, 'minPeriods'),
		);
	}

	/** Whether the session includes a trial period. */
	public function hasTrial(): bool
	{
		return $this->trialPeriod !== null && $this->trialPeriod > 0;
	}
}
