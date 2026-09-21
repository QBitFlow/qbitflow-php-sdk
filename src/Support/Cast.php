<?php

declare(strict_types=1);

namespace QBitFlow\Support;

use BackedEnum;
use DateTimeImmutable;
use Exception;

/**
 * Small casting helpers used when hydrating API responses into typed objects.
 *
 * The API occasionally omits optional fields entirely and occasionally sends them as
 * `null`; every helper treats both the same way.
 *
 * @internal
 */
final class Cast
{
	/**
	 * @param array<string,mixed> $data
	 */
	public static function string(array $data, string $key, string $default = ''): string
	{
		$value = $data[$key] ?? null;

		return is_scalar($value) ? (string) $value : $default;
	}

	/**
	 * @param array<string,mixed> $data
	 */
	public static function nullableString(array $data, string $key): ?string
	{
		$value = $data[$key] ?? null;

		return is_scalar($value) ? (string) $value : null;
	}

	/**
	 * @param array<string,mixed> $data
	 */
	public static function int(array $data, string $key, int $default = 0): int
	{
		$value = $data[$key] ?? null;

		return is_numeric($value) ? (int) $value : $default;
	}

	/**
	 * @param array<string,mixed> $data
	 */
	public static function nullableInt(array $data, string $key): ?int
	{
		$value = $data[$key] ?? null;

		return is_numeric($value) ? (int) $value : null;
	}

	/**
	 * @param array<string,mixed> $data
	 */
	public static function float(array $data, string $key, float $default = 0.0): float
	{
		$value = $data[$key] ?? null;

		return is_numeric($value) ? (float) $value : $default;
	}

	/**
	 * @param array<string,mixed> $data
	 */
	public static function nullableFloat(array $data, string $key): ?float
	{
		$value = $data[$key] ?? null;

		return is_numeric($value) ? (float) $value : null;
	}

	/**
	 * @param array<string,mixed> $data
	 */
	public static function bool(array $data, string $key, bool $default = false): bool
	{
		$value = $data[$key] ?? null;

		return $value === null ? $default : (bool) $value;
	}

	/**
	 * Parse an ISO-8601 timestamp, falling back to the Unix epoch when absent or unparseable.
	 *
	 * @param array<string,mixed> $data
	 */
	public static function date(array $data, string $key): DateTimeImmutable
	{
		return self::nullableDate($data, $key) ?? new DateTimeImmutable('@0');
	}

	/**
	 * @param array<string,mixed> $data
	 */
	public static function nullableDate(array $data, string $key): ?DateTimeImmutable
	{
		$value = $data[$key] ?? null;

		if (! is_string($value) || $value === '') {
			return null;
		}

		try {
			return new DateTimeImmutable($value);
		} catch (Exception) {
			return null;
		}
	}

	/**
	 * Resolve a backed enum, returning `$default` when the API sends an unknown value.
	 *
	 * Unknown values are tolerated on purpose: the API may introduce new statuses before
	 * this SDK is updated, and that should not break an otherwise valid response.
	 *
	 * @template T of BackedEnum
	 *
	 * @param array<string,mixed> $data
	 * @param class-string<T>     $enum
	 * @param T                   $default
	 *
	 * @return T
	 */
	public static function enum(array $data, string $key, string $enum, BackedEnum $default): BackedEnum
	{
		return self::nullableEnum($data, $key, $enum) ?? $default;
	}

	/**
	 * @template T of BackedEnum
	 *
	 * @param array<string,mixed> $data
	 * @param class-string<T>     $enum
	 *
	 * @return T|null
	 */
	public static function nullableEnum(array $data, string $key, string $enum): ?BackedEnum
	{
		$value = $data[$key] ?? null;

		if (! is_string($value) && ! is_int($value)) {
			return null;
		}

		return $enum::tryFrom($value);
	}

	/**
	 * Extract a nested object and hydrate it, or return null when absent.
	 *
	 * @template T of object
	 *
	 * @param array<string,mixed>   $data
	 * @param callable(array<string,mixed>): T $factory
	 *
	 * @return T|null
	 */
	public static function nested(array $data, string $key, callable $factory): ?object
	{
		$value = $data[$key] ?? null;

		return is_array($value) ? $factory($value) : null;
	}

	/**
	 * Hydrate a list of nested objects, skipping anything that is not an object.
	 *
	 * @template T of object
	 *
	 * @param callable(array<string,mixed>): T $factory
	 *
	 * @return list<T>
	 */
	public static function listOf(mixed $value, callable $factory): array
	{
		if (! is_array($value)) {
			return [];
		}

		$out = [];

		foreach ($value as $item) {
			if (is_array($item)) {
				$out[] = $factory($item);
			}
		}

		return $out;
	}

	/**
	 * Cast a raw value to a list of integers (used for `availableCurrencies`).
	 *
	 * @return list<int>
	 */
	public static function intList(mixed $value): array
	{
		if (! is_array($value)) {
			return [];
		}

		$out = [];

		foreach ($value as $item) {
			if (is_numeric($item)) {
				$out[] = (int) $item;
			}
		}

		return $out;
	}
}
