<?php

declare(strict_types=1);

namespace QBitFlow\Dto;

use DateTimeImmutable;
use QBitFlow\Dto\Metadata\PaymentMetadata;
use QBitFlow\Support\Cast;
use QBitFlow\Support\Dto;

/**
 * One billing-cycle record of a subscription. Also the `data` of a `billing` subscription
 * webhook.
 */
final class SubscriptionHistory extends Dto
{
	public function __construct(
		/** Unique identifier for this billing record, `sub-hist@`-prefixed. */
		public readonly string $uuid,
		/** When the billing occurred. */
		public readonly DateTimeImmutable $createdAt,
		/** Subscriber's address. */
		public readonly string $from,
		/** Receiver address. */
		public readonly string $to,
		/** Product name at time of billing. */
		public readonly string $name,
		/** Product description at time of billing. */
		public readonly string $description,
		/** Amount charged, in USD. */
		public readonly float $amount,
		/** Amount in the smallest units of the payment currency, as a decimal string. */
		public readonly string $amountMinUnits,
		/** Currency ID. */
		public readonly int $currencyId,
		/** The currency charged. */
		public readonly Currency $currency,
		/** Whether this was a test-mode transaction. */
		public readonly bool $test,
		/** Product the subscription bills for. */
		public readonly int $productId,
		/** UUID of the parent subscription, `sub@`-prefixed. */
		public readonly string $subscriptionUUID,
		/** Blockchain transaction hash. */
		public readonly string $transactionHash,
		/** Owning organization ID. */
		public readonly int $organizationId,
		/** Owning user ID; `0` for an organization-level subscription. */
		public readonly int $userId,
		/** Fee breakdown and on-chain details. */
		public readonly PaymentMetadata $metadata,
		/** UUID of the billed customer; null when no customer was attached. */
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
			Cast::string($data, 'from'),
			Cast::string($data, 'to'),
			Cast::string($data, 'name'),
			Cast::string($data, 'description'),
			Cast::float($data, 'amount'),
			Cast::string($data, 'amountMinUnits'),
			Cast::int($data, 'currencyId'),
			Cast::object($data, 'currency', Currency::fromArray(...)),
			Cast::bool($data, 'test'),
			Cast::int($data, 'productId'),
			Cast::string($data, 'subscriptionUUID'),
			Cast::string($data, 'transactionHash'),
			Cast::int($data, 'organizationId'),
			Cast::int($data, 'userId'),
			Cast::object($data, 'metadata', PaymentMetadata::fromArray(...)),
			Cast::nullableString($data, 'customerUUID'),
		);
	}
}
