<?php

declare(strict_types=1);

namespace QBitFlow\Dto;

use QBitFlow\Support\Cast;
use QBitFlow\Support\Dto;

/**
 * An error returned in place of a transaction-status update.
 */
final class StatusResponseError extends Dto
{
	public function __construct(
		/** Error type or code. */
		public readonly string $error,
		/** HTTP status code. */
		public readonly int $status,
		/** Human-readable error message. */
		public readonly string $message,
	) {
	}

	/**
	 * @param array<string,mixed> $data
	 */
	public static function fromArray(array $data): self
	{
		return new self(
			Cast::string($data, 'error'),
			Cast::int($data, 'status'),
			Cast::string($data, 'message') ?: Cast::string($data, 'error'),
		);
	}
}
