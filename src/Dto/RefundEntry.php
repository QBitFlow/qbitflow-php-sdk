<?php

declare(strict_types=1);

namespace QBitFlow\Dto;

use DateTimeImmutable;
use QBitFlow\Dto\Metadata\TxMetadata;
use QBitFlow\Enums\RefundStatus;
use QBitFlow\Support\Cast;
use QBitFlow\Support\Dto;

/**
 * A refund attached to a transaction.
 */
final class RefundEntry extends Dto
{
	public function __construct(
		/** Unique identifier for the refund. */
		public readonly string $uuid,
		/** Internal transaction ID of the refunded transaction, e.g. `pay@<uuid>`. */
		public readonly string $txId,
		/** Whether this is a test-mode refund. */
		public readonly bool $test,
		/** Reason the customer gave for the refund. */
		public readonly string $reason,
		/** Current status. */
		public readonly RefundStatus $status,
		/** When the refund was requested. */
		public readonly DateTimeImmutable $createdAt,
		/** Message from the merchant, when one was left. */
		public readonly ?string $merchantMessage = null,
		/** When the refund was processed; null while still pending. */
		public readonly ?DateTimeImmutable $respondedAt = null,
		/** On-chain hash of the refund transaction; null until processed. */
		public readonly ?string $txHash = null,
		/** Refund amount in the smallest units of the currency. */
		public readonly ?string $amountMinUnits = null,
		/** Owning organization ID. Authenticated reads only. */
		public readonly ?int $organizationId = null,
		/** Owning user ID. Authenticated reads only. */
		public readonly ?int $userId = null,
		/** On-chain metadata for the refund. Authenticated reads only. */
		public readonly ?TxMetadata $metadata = null,
	) {
	}

	/**
	 * @param array<string,mixed> $data
	 */
	public static function fromArray(array $data): self
	{
		return new self(
			Cast::string($data, 'uuid'),
			Cast::string($data, 'txId'),
			Cast::bool($data, 'test'),
			Cast::string($data, 'reason'),
			Cast::enum($data, 'status', RefundStatus::class, RefundStatus::PENDING),
			Cast::date($data, 'createdAt'),
			Cast::nullableString($data, 'merchantMessage'),
			Cast::nullableDate($data, 'respondedAt'),
			Cast::nullableString($data, 'txHash'),
			Cast::nullableString($data, 'amountMinUnits'),
			Cast::nullableInt($data, 'organizationId'),
			Cast::nullableInt($data, 'userId'),
			Cast::nested($data, 'metadata', TxMetadata::fromArray(...)),
		);
	}

	/** Whether the refund is still awaiting a decision. */
	public function isPending(): bool
	{
		return $this->status === RefundStatus::PENDING;
	}
}
