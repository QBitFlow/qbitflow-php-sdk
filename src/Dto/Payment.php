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
		/** Unique identifier for the payment. */
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
		/** Currency ID used for payment. */
		public readonly int $currencyId,
		/** Blockchain transaction hash. */
		public readonly string $transactionHash,
		/** UUID of the paying customer. */
		public readonly string $customerUUID,
		/** Whether this is a test-mode payment. */
		public readonly bool $test,
		/**
		 * Your own reference, set when the session was created. Look the payment up by it
		 * with {@see \QBitFlow\Requests\PaymentRequests::getByReference()}.
		 */
		public readonly ?string $reference = null,
		/** Amount in the smallest units of the payment currency, e.g. satoshis. */
		public readonly ?string $amountMinUnits = null,
		/** Full currency details. */
		public readonly ?Currency $currency = null,
		/** Product ID, when the payment came from a stored product. */
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
			Cast::string($data, 'transactionHash'),
			Cast::string($data, 'customerUUID'),
			Cast::bool($data, 'test'),
			Cast::nullableString($data, 'reference'),
			Cast::nullableString($data, 'amountMinUnits'),
			Cast::nested($data, 'currency', Currency::fromArray(...)),
			Cast::nullableInt($data, 'productId'),
			Cast::nullableInt($data, 'organizationId'),
			Cast::nullableInt($data, 'userId'),
			Cast::nested($data, 'metadata', PaymentMetadata::fromArray(...)),
		);
	}
}
