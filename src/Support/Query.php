<?php

declare(strict_types=1);

namespace QBitFlow\Support;

use DateTimeInterface;

/**
 * Builds a query string: an unset value is omitted, booleans are `true`/`false`, times are
 * RFC 3339 (with their offset; a `+` is sent as `%2B`), numbers are decimal.
 *
 * @internal
 */
final class Query
{
	/** @var array<string,string> */
	private array $values = [];

	/** Sets a string when it is neither null nor empty. */
	public function string(string $key, ?string $value): self
	{
		if ($value !== null && $value !== '') {
			$this->values[$key] = $value;
		}

		return $this;
	}

	/** Sets `true` when the flag is set (false is the API's default, left out). */
	public function flag(string $key, bool $value): self
	{
		if ($value) {
			$this->values[$key] = 'true';
		}

		return $this;
	}

	/** Sets `true` or `false` when not null. */
	public function bool(string $key, ?bool $value): self
	{
		if ($value !== null) {
			$this->values[$key] = $value ? 'true' : 'false';
		}

		return $this;
	}

	/** Sets an integer when not null. */
	public function int(string $key, ?int $value): self
	{
		if ($value !== null) {
			$this->values[$key] = (string) $value;
		}

		return $this;
	}

	/** Sets an RFC 3339 time when not null. */
	public function time(string $key, ?DateTimeInterface $value): self
	{
		if ($value !== null) {
			$this->values[$key] = self::formatTime($value);
		}

		return $this;
	}

	/** Sets a page's `limit` and `cursor`. */
	public function page(?int $limit, ?string $cursor): self
	{
		return $this->int('limit', $limit)->string('cursor', $cursor);
	}

	/** @return array<string,string> */
	public function toArray(): array
	{
		return $this->values;
	}

	/**
	 * Encodes a query, keys sorted, every byte but `A-Z a-z 0-9 - _ . ~` percent-encoded (a
	 * space as `+`).
	 *
	 * @param array<string,string> $values
	 */
	public static function encode(array $values): string
	{
		ksort($values, SORT_STRING);
		$parts = [];
		foreach ($values as $key => $value) {
			$parts[] = self::escape((string) $key) . '=' . self::escape($value);
		}

		return implode('&', $parts);
	}

	/**
	 * RFC 3339 with the time's own offset (`Z` for UTC) and the shortest fraction of a second:
	 * `2026-10-04T12:00:00+02:00`, `2026-10-01T00:00:00.5Z`.
	 */
	public static function formatTime(DateTimeInterface $time): string
	{
		$out = $time->format('Y-m-d\TH:i:s');
		$micros = (int) $time->format('u');
		if ($micros !== 0) {
			$out .= '.' . rtrim(sprintf('%06d', $micros), '0');
		}

		return $out . ($time->getOffset() === 0 ? 'Z' : $time->format('P'));
	}

	private static function escape(string $value): string
	{
		return str_replace('%20', '+', rawurlencode($value));
	}
}
