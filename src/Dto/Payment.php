<?php

declare(strict_types=1);

namespace QBitFlow\Dto;

use DateTimeImmutable;
use QBitFlow\Dto\Metadata\PaymentMetadata;
use QBitFlow\Support\Cast;
use QBitFlow\Support\Dto;

/**
 * A settled one-time payment.
 */
final class Payment extends Dto
{
	public function __construct(
		/** Unique identifier for the payment, `pay@`-prefixed. */
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
		/** Amount in the smallest units of the payment currency (e.g. wei), as a decimal string. */
		public readonly string $amountMinUnits,
		/** Currency ID used for payment. */
		public readonly int $currencyId,
		/** The currency used for payment. */
		public readonly Currency $currency,
		/** Blockchain transaction hash. */
		public readonly string $transactionHash,
		/** Whether this is a test-mode payment. */
		public readonly bool $test,
		/** Owning organization ID. */
		public readonly int $organizationId,
		/** Owning user ID; `0` for an organization-level payment. */
		public readonly int $userId,
		/** Fee breakdown and on-chain details. */
		public readonly PaymentMetadata $metadata,
		/** Product ID; `0` when the payment did not come from a stored product. */
		public readonly int $productId = 0,
		/**
		 * Your own reference, set when the session was created; null when none was set. Look
		 * the payment up by it with {@see \QBitFlow\Requests\PaymentRequests::getByReference()}.
		 */
		public readonly ?string $reference = null,
		/** UUID of the paying customer; null when no customer was attached. */
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
			Cast::string($data, 'transactionHash'),
			Cast::bool($data, 'test'),
			Cast::int($data, 'organizationId'),
			Cast::int($data, 'userId'),
			Cast::object($data, 'metadata', PaymentMetadata::fromArray(...)),
			Cast::int($data, 'productId'),
			Cast::nullableString($data, 'reference'),
			Cast::nullableString($data, 'customerUUID'),
		);
	}
}
