<?php

declare(strict_types=1);

namespace QBitFlow\Dto\Session;

use QBitFlow\Support\Cast;
use QBitFlow\Support\Dto;

/**
 * Confirmation plus a link to the transaction's status stream.
 */
final class StatusLinkResponse extends Dto
{
	public function __construct(
		/** Status message. */
		public readonly string $message,
		/** WebSocket URL for following the transaction status. */
		public readonly string $statusLink,
	) {
	}

	/**
	 * @param array<string,mixed> $data
	 */
	public static function fromArray(array $data): self
	{
		return new self(
			Cast::string($data, 'message'),
			Cast::string($data, 'statusLink'),
		);
	}
}
