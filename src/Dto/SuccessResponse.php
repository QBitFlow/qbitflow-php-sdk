<?php

declare(strict_types=1);

namespace QBitFlow\Dto;

use QBitFlow\Support\Cast;
use QBitFlow\Support\Dto;

/**
 * Generic confirmation returned by write endpoints that have nothing else to report.
 */
final class SuccessResponse extends Dto
{
	public function __construct(
		/** Confirmation message from the API. */
		public readonly string $message,
	) {
	}

	/**
	 * @param array<string,mixed> $data
	 */
	public static function fromArray(array $data): self
	{
		return new self(Cast::string($data, 'message'));
	}
}
