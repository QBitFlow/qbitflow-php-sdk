<?php

declare(strict_types=1);

namespace QBitFlow\Dto;

use DateTimeImmutable;
use QBitFlow\Dto\Metadata\PaymentMetadata;
use QBitFlow\Enums\CombinedPaymentSource;
use QBitFlow\Support\Cast;
use QBitFlow\Support\Dto;

/**
 * An entry from the combined feed of one-time payments and subscription billings.
 *
 * Check {@see CombinedPayment::$source} to tell the two apart; `subscriptionUUID` is
 * set only on subscription-history entries.
 */
final class CombinedPayment extends Dto
{
	public function __construct(
		/** Where this entry originated. */
		public readonly CombinedPaymentSource $source,
		/** Unique identifier for the entry. */
		public readonly string $uuid,
		/** When the payment was created. */
		public readonly DateTimeImmutable $createdAt,
		/** Sender address. */
		public readonly string $from,
		/** Receiver address. */
		public readonly string $to,
		/** Product name at time of payment. */
		public readonly string $name,
		/** Product description at time of payment. */
		public readonly string $description,
		/** Amount paid, in USD. */
		public readonly float $amount,
		/** Currency ID. */
		public readonly int $currencyId,
		/** Blockchain transaction hash. */
		public readonly string $transactionHash,
		/** UUID of the paying customer. */
		public readonly string $customerUUID,
		/** Whether this is a test-mode payment. */
		public readonly bool $test,
		/** Amount in the smallest units of the payment currency. */
		public readonly ?string $amountMinUnits = null,
		/** Full currency details. */
		public readonly ?Currency $currency = null,
		/** Product ID, when the payment came from a stored product. */
		public readonly ?int $productId = null,
		/** Parent subscription UUID, present only on subscription-history entries. */
		public readonly ?string $subscriptionUUID = null,
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
			Cast::enum($data, 'source', CombinedPaymentSource::class, CombinedPaymentSource::PAYMENT),
			Cast::string($data, 'uuid'),
			Cast::date($data, 'createdAt'),
			Cast::string($data, 'from'),
			Cast::string($data, 'to'),
			Cast::string($data, 'name'),
			Cast::string($data, 'description'),
			Cast::float($data, 'amount'),
			Cast::int($data, 'currencyId'),
			Cast::string($data, 'transactionHash'),
			Cast::string($data, 'customerUUID'),
			Cast::bool($data, 'test'),
			Cast::nullableString($data, 'amountMinUnits'),
			Cast::nested($data, 'currency', Currency::fromArray(...)),
			Cast::nullableInt($data, 'productId'),
			Cast::nullableString($data, 'subscriptionUUID'),
			Cast::nullableInt($data, 'organizationId'),
			Cast::nullableInt($data, 'userId'),
			Cast::nested($data, 'metadata', PaymentMetadata::fromArray(...)),
		);
	}

	/** Whether this entry is a subscription billing rather than a one-time payment. */
	public function isSubscriptionBilling(): bool
	{
		return $this->source === CombinedPaymentSource::SUBSCRIPTION_HISTORY;
	}
}
