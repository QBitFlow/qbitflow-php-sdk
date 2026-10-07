<?php

declare(strict_types=1);

namespace QBitFlow\Dto;

use QBitFlow\Dto\Metadata\PaymentMetadata;
use QBitFlow\Enums\TransactionStatusValue;
use QBitFlow\Support\Cast;
use QBitFlow\Support\Dto;

/**
 * Detailed state of a transaction.
 */
final class TransactionStatus extends Dto
{
	public function __construct(
		/**
		 * Current status. A `TransactionStatusValue` member when this SDK knows the value,
		 * or the raw string for a status the API added later — see {@see \QBitFlow\Support\Enums}.
		 */
		public readonly TransactionStatusValue|string $status,
		/** Blockchain transaction hash; `''` until the transaction is broadcast. */
		public readonly string $txHash = '',
		/** Status message or error description; `''` when there is none. */
		public readonly string $message = '',
		/** Finalized payment metadata, present once a successful transaction has settled. */
		public readonly ?PaymentMetadata $settlementDetails = null,
	) {
	}

	/**
	 * @param array<array-key,mixed> $data
	 */
	public static function fromArray(array $data): self
	{
		return new self(
			Cast::enum($data, 'status', TransactionStatusValue::class),
			Cast::string($data, 'txHash'),
			Cast::string($data, 'message'),
			Cast::nullableObject($data, 'settlementDetails', PaymentMetadata::fromArray(...)),
		);
	}

	/** Whether the transaction completed successfully. */
	public function isCompleted(): bool
	{
		return $this->status === TransactionStatusValue::COMPLETED;
	}

	/** Whether the transaction reached a terminal unsuccessful state. */
	public function isFailed(): bool
	{
		return in_array($this->status, [
			TransactionStatusValue::FAILED,
			TransactionStatusValue::CANCELLED,
			TransactionStatusValue::EXPIRED,
		], true);
	}
}
