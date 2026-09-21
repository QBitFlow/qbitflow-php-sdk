<?php

declare(strict_types=1);

namespace QBitFlow\Dto\Session;

use QBitFlow\Enums\TransactionType;
use QBitFlow\Support\Cast;
use QBitFlow\Support\Duration;

/**
 * Checkout session for a pay-as-you-go subscription.
 *
 * Creating PAYG sessions is currently disabled on the API; existing sessions can still
 * be read. Unlike {@see SubscriptionSession}, the frequency here is a structured
 * duration rather than a number of seconds.
 */
final class PaygSubscriptionSession extends SessionCheckout
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
		/** Billing frequency, as a structured duration. */
		public readonly ?Duration $frequency = null,
		/** Free credits in USD granted before the on-chain allowance is drawn down. */
		public readonly float $freeCredits = 0.0,
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
		$frequency = $data['frequency'] ?? null;

		return new self(
			...self::baseArguments($data),
			frequency: is_array($frequency) ? Duration::fromArray($frequency) : null,
			freeCredits: Cast::float($data, 'freeCredits'),
		);
	}
}
