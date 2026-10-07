<?php

declare(strict_types=1);

namespace QBitFlow\Dto\Session;

use QBitFlow\Support\Cast;
use QBitFlow\Support\Dto;

/**
 * The checkout link to send your customer, returned when a session is created.
 */
final class LinkResponse extends Dto
{
	public function __construct(
		/** Session UUID. */
		public readonly string $uuid,
		/** Payment link to send to the customer. */
		public readonly string $link,
	) {
	}

	/**
	 * @param array<array-key,mixed> $data
	 */
	public static function fromArray(array $data): self
	{
		return new self(
			Cast::string($data, 'uuid'),
			Cast::string($data, 'link'),
		);
	}
}
