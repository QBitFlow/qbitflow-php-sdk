<?php

declare(strict_types=1);

namespace QBitFlow\Dto;

use QBitFlow\Support\Cast;
use QBitFlow\Support\Dto;

/**
 * The one-time link a provisioned user follows to set a password and connect a wallet.
 */
final class ClaimRequestResponse extends Dto
{
	public function __construct(
		/** Confirmation message. */
		public readonly string $message,
		/** Claim link to send to the user. */
		public readonly string $link,
	) {
	}

	/**
	 * @param array<string,mixed> $data
	 */
	public static function fromArray(array $data): self
	{
		return new self(
			Cast::string($data, 'message'),
			Cast::string($data, 'link'),
		);
	}
}
