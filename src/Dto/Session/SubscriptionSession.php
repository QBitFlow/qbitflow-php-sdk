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
		TransactionType|string $txType,
		string $productName,
		string $description,
		float $price,
		string $organizationName,
		int $organizationId,
		int $feeBps,
		bool $test,
		array $availableCurrencies = [],
		string $reference = '',
		int $productId = 0,
		string $productReference = '',
		string $successUrl = '',
		string $cancelUrl = '',
		int $organizationFeeBps = 0,
		int $userId = 0,
		string $userName = '',
		?string $customerUUID = null,
		string $customerReference = '',
		/** Billing frequency, in seconds. For example 2592000 for 30 days. */
		public readonly int $frequency = 0,
		/** Trial period in seconds; `0` when there is no trial. */
		public readonly int $trialPeriod = 0,
		/** Minimum number of billing periods the subscriber must complete; `0` when none. */
		public readonly int $minPeriods = 0,
		/** Whether this checkout upgrades an existing trial subscription. */
		public readonly bool $upgradingFromTrial = false,
	) {
		parent::__construct(
			$uuid,
			$txType,
			$productName,
			$description,
			$price,
			$organizationName,
			$organizationId,
			$feeBps,
			$test,
			$availableCurrencies,
			$reference,
			$productId,
			$productReference,
			$successUrl,
			$cancelUrl,
			$organizationFeeBps,
			$userId,
			$userName,
			$customerUUID,
			$customerReference,
		);
	}

	/**
	 * @param array<array-key,mixed> $data
	 */
	public static function fromArray(array $data): self
	{
		return new self(
			...self::baseArguments($data),
			frequency: Cast::int($data, 'frequency'),
			trialPeriod: Cast::int($data, 'trialPeriod'),
			minPeriods: Cast::int($data, 'minPeriods'),
			upgradingFromTrial: Cast::bool($data, 'upgradingFromTrial'),
		);
	}

	/** Whether the session includes a trial period. */
	public function hasTrial(): bool
	{
		return $this->trialPeriod > 0;
	}
}
