<?php

declare(strict_types=1);

namespace QBitFlow\Dto;

use QBitFlow\Support\Cast;
use QBitFlow\Support\Dto;

/**
 * A transaction-status update, as delivered by the status stream.
 */
final class StatusResponse extends Dto
{
	public function __construct(
		/** UUID of the transaction. */
		public readonly string $transactionUUID,
		/** Current status of the transaction. */
		public readonly TransactionStatus $status,
	) {
	}

	/**
	 * @param array<string,mixed> $data
	 */
	public static function fromArray(array $data): self
	{
		$status = $data['status'] ?? [];

		return new self(
			Cast::string($data, 'transactionUUID'),
			TransactionStatus::fromArray(is_array($status) ? $status : []),
		);
	}
}
