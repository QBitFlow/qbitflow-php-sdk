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
		/** Unique identifier for the refund, `refund@`-prefixed. */
		public readonly string $uuid,
		/** Transaction ID of the refunded transaction, e.g. `pay@<uuid>`. */
		public readonly string $txId,
		/** Whether this is a test-mode refund. */
		public readonly bool $test,
		/** Reason the customer gave for the refund. */
		public readonly string $reason,
		/**
		 * Current status. A `RefundStatus` member, or the raw string for a status this SDK
		 * does not know — see {@see \QBitFlow\Support\Enums}.
		 */
		public readonly RefundStatus|string $status,
		/** When the refund was requested. */
		public readonly DateTimeImmutable $createdAt,
		/** Message from the merchant; `''` until one is left. */
		public readonly string $merchantMessage,
		/** On-chain hash of the refund transaction; `''` until processed. */
		public readonly string $txHash,
		/** Refund amount in the smallest units of the currency, as a decimal string. */
		public readonly string $amountMinUnits,
		/** Owning organization ID. */
		public readonly int $organizationId,
		/** Owning user ID; `0` for an organization-level refund. */
		public readonly int $userId,
		/** When the merchant answered the refund; null while it awaits a decision. */
		public readonly ?DateTimeImmutable $respondedAt = null,
		/** On-chain metadata for the refund transaction; null until processed. */
		public readonly ?TxMetadata $metadata = null,
	) {
	}

	/**
	 * @param array<array-key,mixed> $data
	 */
	public static function fromArray(array $data): self
	{
		return new self(
			Cast::string($data, 'uuid'),
			Cast::string($data, 'txId'),
			Cast::bool($data, 'test'),
			Cast::string($data, 'reason'),
			Cast::enum($data, 'status', RefundStatus::class),
			Cast::date($data, 'createdAt'),
			Cast::string($data, 'merchantMessage'),
			Cast::string($data, 'txHash'),
			Cast::string($data, 'amountMinUnits'),
			Cast::int($data, 'organizationId'),
			Cast::int($data, 'userId'),
			Cast::nullableDate($data, 'respondedAt'),
			Cast::nullableObject($data, 'metadata', TxMetadata::fromArray(...)),
		);
	}

	/** Whether the refund is still awaiting a decision. */
	public function isPending(): bool
	{
		return $this->status === RefundStatus::PENDING;
	}
}
