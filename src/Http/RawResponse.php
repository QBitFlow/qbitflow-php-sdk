<?php

declare(strict_types=1);

namespace QBitFlow\Http;

/**
 * One HTTP exchange's answer, its body read in full.
 *
 * @internal
 */
final readonly class RawResponse
{
	/**
	 * @param array<string,string> $headers Header lines, keyed by lowercase name.
	 */
	public function __construct(
		public int $status,
		public array $headers,
		public string $body,
	) {
	}

	/** A header's value (`''` when absent), by case-insensitive name. */
	public function header(string $name): string
	{
		return $this->headers[strtolower($name)] ?? '';
	}
}
