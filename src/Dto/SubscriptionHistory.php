<?php

declare(strict_types=1);

namespace QBitFlow\Dto;

use DateTimeImmutable;
use QBitFlow\Dto\Metadata\PaymentMetadata;
use QBitFlow\Support\Cast;
use QBitFlow\Support\Dto;

/**
 * One billing-cycle record of a subscription.
 */
final class SubscriptionHistory extends Dto
{
	public function __construct(
		/** Unique identifier for this billing record. */
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
		/** Currency ID. */
		public readonly int $currencyId,
		/** Whether this was a test-mode transaction. */
		public readonly bool $test,
		/** UUID of the parent subscription. */
		public readonly string $subscriptionUUID,
		/** Blockchain transaction hash. */
		public readonly string $transactionHash,
		/** UUID of the billed customer. */
		public readonly string $customerUUID,
		/** Amount in the smallest units of the payment currency. */
		public readonly ?string $amountMinUnits = null,
		/** Full currency details. */
		public readonly ?Currency $currency = null,
		/** Product ID, when the subscription came from a stored product. */
		public readonly ?int $productId = null,
		/** Owning organization ID. Authenticated reads only. */
		public readonly ?int $organizationId = null,
		/** Owning user ID. Authenticated reads only. */
		public readonly ?int $userId = null,
		/** Fee breakdown and on-chain details. Authenticated reads only. */
		public readonly ?PaymentMetadata $metadata = null,
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
			Cast::string($data, 'from'),
			Cast::string($data, 'to'),
			Cast::string($data, 'name'),
			Cast::string($data, 'description'),
			Cast::float($data, 'amount'),
			Cast::int($data, 'currencyId'),
			Cast::bool($data, 'test'),
			Cast::string($data, 'subscriptionUUID'),
			Cast::string($data, 'transactionHash'),
			Cast::string($data, 'customerUUID'),
			Cast::nullableString($data, 'amountMinUnits'),
			Cast::nested($data, 'currency', Currency::fromArray(...)),
			Cast::nullableInt($data, 'productId'),
			Cast::nullableInt($data, 'organizationId'),
			Cast::nullableInt($data, 'userId'),
			Cast::nested($data, 'metadata', PaymentMetadata::fromArray(...)),
		);
	}
}
