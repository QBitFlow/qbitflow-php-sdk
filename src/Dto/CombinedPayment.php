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
		/**
		 * Where this entry originated. A `CombinedPaymentSource` member, or the raw string
		 * for a source this SDK does not know — see {@see \QBitFlow\Support\Enums}.
		 */
		public readonly CombinedPaymentSource|string $source,
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
		/** Amount in the smallest units of the payment currency, as a decimal string. */
		public readonly string $amountMinUnits,
		/** Currency ID. */
		public readonly int $currencyId,
		/** The currency used for payment. */
		public readonly Currency $currency,
		/** Blockchain transaction hash. */
		public readonly string $transactionHash,
		/**
		 * UUID of the paying customer. The zero UUID
		 * (`00000000-0000-0000-0000-000000000000`) when no customer was attached.
		 */
		public readonly string $customerUUID,
		/** Whether this is a test-mode payment. */
		public readonly bool $test,
		/** Product ID; null when the payment did not come from a stored product. */
		public readonly ?int $productId = null,
		/** Parent subscription UUID; set only on subscription-history entries. */
		public readonly ?string $subscriptionUUID = null,
		/** Fee breakdown and on-chain details; null when not recorded. */
		public readonly ?PaymentMetadata $metadata = null,
	) {
	}

	/**
	 * @param array<array-key,mixed> $data
	 */
	public static function fromArray(array $data): self
	{
		return new self(
			Cast::enum($data, 'source', CombinedPaymentSource::class),
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
			Cast::string($data, 'transactionHash'),
			Cast::string($data, 'customerUUID'),
			Cast::bool($data, 'test'),
			Cast::nullableInt($data, 'productId'),
			Cast::nullableString($data, 'subscriptionUUID'),
			Cast::nullableObject($data, 'metadata', PaymentMetadata::fromArray(...)),
		);
	}

	/** Whether this entry is a subscription billing rather than a one-time payment. */
	public function isSubscriptionBilling(): bool
	{
		return $this->source === CombinedPaymentSource::SUBSCRIPTION_HISTORY;
	}
}
