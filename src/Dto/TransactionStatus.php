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
		/** Current status. */
		public readonly TransactionStatusValue $status,
		/** Blockchain transaction hash, empty until one exists. */
		public readonly string $txHash = '',
		/** Status message or error description. */
		public readonly ?string $message = null,
		/** Finalized payment metadata, present once a successful transaction has settled. */
		public readonly ?PaymentMetadata $settlementDetails = null,
	) {
	}

	/**
	 * @param array<string,mixed> $data
	 */
	public static function fromArray(array $data): self
	{
		return new self(
			Cast::enum($data, 'status', TransactionStatusValue::class, TransactionStatusValue::CREATED),
			Cast::string($data, 'txHash'),
			Cast::nullableString($data, 'message'),
			Cast::nested($data, 'settlementDetails', PaymentMetadata::fromArray(...)),
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
