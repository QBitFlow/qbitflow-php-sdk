<?php

declare(strict_types=1);

namespace QBitFlow\Support;

use BackedEnum;
use DateTimeImmutable;
use Exception;
use QBitFlow\Exceptions\ServerException;

/**
 * Hydration helpers that turn a decoded API response into typed objects.
 *
 * Every QBitFlow SDK applies the same decoding policy, modelled on Go's `encoding/json` (the
 * API is written in Go):
 *
 * - **Absent or `null`** where the type is non-nullable decodes to the zero value: `''`, `0`,
 *   `0.0`, `false`, `[]`, a zero-valued nested object, or Go's zero time
 *   (`0001-01-01T00:00:00Z`) for a timestamp. It is never an error. The `nullable*` helpers
 *   return `null` instead, for the fields the API models as pointers.
 * - **Present with the wrong JSON type** — a string where a number is expected, an object
 *   where a string is expected, a list where an object is expected — means the response
 *   does not have the documented shape, and raises a {@see ServerException}. Harmless
 *   numeric widening is accepted: an integer into a float field, an integral float into an
 *   integer field.
 * - **An integer beyond PHP's range** (the SDK decodes with `JSON_BIGINT_AS_STRING`) cannot
 *   be represented and raises a {@see ServerException} rather than silently wrapping.
 * - **Unknown enum values** are kept as the raw string.
 *
 * The transport attaches the HTTP status to any {@see ServerException} raised here.
 *
 * @internal
 */
final class Cast
{
	/** Go's zero `time.Time`, what the API sends for a timestamp that was never set. */
	public const GO_ZERO_TIME = Time::GO_ZERO;

	/**
	 * RFC 3339, as Go's `time.Time` marshals it: `2026-01-15T10:30:00Z`,
	 * `2026-01-15T10:30:00.123456789+02:00`.
	 */
	private const RFC3339 = '/^\d{4}-\d{2}-\d{2}[Tt]\d{2}:\d{2}:\d{2}(\.\d+)?([Zz]|[+-]\d{2}:\d{2})$/';

	/** A digit string that JSON_BIGINT_AS_STRING produced for an integer beyond PHP_INT_MAX. */
	private const BIG_INTEGER = '/^-?\d{19,}$/';

	/** Go's zero time; see {@see Time}. */
	public static function zeroTime(): DateTimeImmutable
	{
		return Time::zero();
	}

	/** Whether a timestamp is Go's zero time; see {@see Time::isZero()}. */
	public static function isZeroTime(DateTimeImmutable $value): bool
	{
		return Time::isZero($value);
	}

	/**
	 * A string field (decimal amounts included). Absent or null → `$default`, `''` unless the
	 * caller passes another.
	 *
	 * @param array<array-key,mixed> $data
	 *
	 * @throws ServerException When the value is present but not a string.
	 */
	public static function string(array $data, string $key, string $default = ''): string
	{
		$value = $data[$key] ?? null;

		if ($value === null) {
			return $default;
		}

		if (! is_string($value)) {
			throw self::wrongType($key, 'a string', $value);
		}

		return $value;
	}

	/**
	 * A nullable string field (a Go pointer). Absent or null → null.
	 *
	 * @param array<array-key,mixed> $data
	 *
	 * @throws ServerException When the value is present but not a string.
	 */
	public static function nullableString(array $data, string $key): ?string
	{
		return ($data[$key] ?? null) === null ? null : self::string($data, $key);
	}

	/**
	 * An integer field. Absent or null → 0.
	 *
	 * @param array<array-key,mixed> $data
	 *
	 * @throws ServerException When the value is not an integer, or does not fit in PHP's int.
	 */
	public static function int(array $data, string $key): int
	{
		$value = $data[$key] ?? null;

		if ($value === null) {
			return 0;
		}

		return self::toInt($key, $value);
	}

	/**
	 * A nullable integer field (a Go pointer). Absent or null → null.
	 *
	 * @param array<array-key,mixed> $data
	 *
	 * @throws ServerException When the value is present but not an integer.
	 */
	public static function nullableInt(array $data, string $key): ?int
	{
		$value = $data[$key] ?? null;

		return $value === null ? null : self::toInt($key, $value);
	}

	/**
	 * A float field. Absent or null → 0.0. An integer is widened.
	 *
	 * @param array<array-key,mixed> $data
	 *
	 * @throws ServerException When the value is present but not a number.
	 */
	public static function float(array $data, string $key): float
	{
		$value = $data[$key] ?? null;

		if ($value === null) {
			return 0.0;
		}

		if (is_int($value) || is_float($value)) {
			return (float) $value;
		}

		// An integer too large for PHP's int, decoded as a digit string: still a number.
		if (is_string($value) && preg_match(self::BIG_INTEGER, $value) === 1) {
			return (float) $value;
		}

		throw self::wrongType($key, 'a number', $value);
	}

	/**
	 * A boolean field. Absent or null → false.
	 *
	 * @param array<array-key,mixed> $data
	 *
	 * @throws ServerException When the value is present but not a boolean.
	 */
	public static function bool(array $data, string $key): bool
	{
		$value = $data[$key] ?? null;

		if ($value === null) {
			return false;
		}

		if (! is_bool($value)) {
			throw self::wrongType($key, 'a boolean', $value);
		}

		return $value;
	}

	/**
	 * A timestamp the API always sends (a Go `time.Time`). Absent or null → Go's zero time;
	 * test for it with {@see Time::isZero()}.
	 *
	 * @param array<array-key,mixed> $data
	 *
	 * @throws ServerException When the value is present but not an RFC 3339 timestamp.
	 */
	public static function date(array $data, string $key): DateTimeImmutable
	{
		return self::nullableDate($data, $key) ?? self::zeroTime();
	}

	/**
	 * A nullable timestamp (a Go `*time.Time`). Absent or null → null.
	 *
	 * @param array<array-key,mixed> $data
	 *
	 * @throws ServerException When the value is present but not an RFC 3339 timestamp.
	 */
	public static function nullableDate(array $data, string $key): ?DateTimeImmutable
	{
		$value = $data[$key] ?? null;

		if ($value === null) {
			return null;
		}

		if (! is_string($value) || preg_match(self::RFC3339, $value) !== 1) {
			throw self::wrongType($key, 'an RFC 3339 timestamp', $value);
		}

		try {
			return new DateTimeImmutable($value);
		} catch (Exception $e) {
			throw new ServerException(
				sprintf('Malformed API response: "%s" is not a valid timestamp (%s)', $key, $value),
				previous: $e,
			);
		}
	}

	/**
	 * An enum-backed field. A known value hydrates to the enum member; an unknown one — a
	 * value the API added after this SDK was released — is kept as the raw string, so the
	 * rest of the response stays usable. Absent or null → `''` (Go's zero value), which is
	 * never a member. Use {@see Enums} to work with the `<Enum>|string` union.
	 *
	 * @template T of BackedEnum
	 *
	 * @param array<array-key,mixed> $data
	 * @param class-string<T>        $enum
	 *
	 * @return T|string
	 *
	 * @throws ServerException When the value is present but not a string.
	 */
	public static function enum(array $data, string $key, string $enum): BackedEnum|string
	{
		$value = self::string($data, $key);

		return $value === '' ? '' : ($enum::tryFrom($value) ?? $value);
	}

	/**
	 * A nested object the API always sends. Absent or null → the zero-valued object
	 * (`$factory([])`).
	 *
	 * @template T of object
	 *
	 * @param array<array-key,mixed>              $data
	 * @param callable(array<array-key,mixed>): T $factory
	 *
	 * @return T
	 *
	 * @throws ServerException When the value is present but not a JSON object.
	 */
	public static function object(array $data, string $key, callable $factory): object
	{
		$value = $data[$key] ?? null;

		return $factory($value === null ? [] : self::assertObject($value, $key));
	}

	/**
	 * A nullable nested object (a Go pointer). Absent or null → null.
	 *
	 * @template T of object
	 *
	 * @param array<array-key,mixed>              $data
	 * @param callable(array<array-key,mixed>): T $factory
	 *
	 * @return T|null
	 *
	 * @throws ServerException When the value is present but not a JSON object.
	 */
	public static function nullableObject(array $data, string $key, callable $factory): ?object
	{
		$value = $data[$key] ?? null;

		return $value === null ? null : $factory(self::assertObject($value, $key));
	}

	/**
	 * Hydrate a whole response body that must be a JSON object.
	 *
	 * @template T of object
	 *
	 * @param callable(array<array-key,mixed>): T $factory
	 *
	 * @return T
	 *
	 * @throws ServerException When the body is not a JSON object.
	 */
	public static function one(mixed $value, callable $factory): object
	{
		return $factory($value === null ? [] : self::assertObject($value, 'response'));
	}

	/**
	 * Hydrate a list of objects: a whole response body, or a field's value. Absent or null →
	 * `[]` (Go encodes a nil slice as `null`).
	 *
	 * @template T of object
	 *
	 * @param callable(array<array-key,mixed>): T $factory
	 *
	 * @return list<T>
	 *
	 * @throws ServerException When the value is not a list, or an item is not an object.
	 */
	public static function listOf(mixed $value, callable $factory, string $key = 'response'): array
	{
		if ($value === null) {
			return [];
		}

		if (! is_array($value) || ! array_is_list($value)) {
			throw self::wrongType($key, 'a list', $value);
		}

		$out = [];

		foreach ($value as $index => $item) {
			$out[] = $factory(self::assertObject($item, sprintf('%s[%d]', $key, $index)));
		}

		return $out;
	}

	/**
	 * A list of integers, such as `availableCurrencies`. Absent or null → `[]`.
	 *
	 * @param array<array-key,mixed> $data
	 *
	 * @return list<int>
	 *
	 * @throws ServerException When the value is not a list of integers.
	 */
	public static function intList(array $data, string $key): array
	{
		$value = $data[$key] ?? null;

		if ($value === null) {
			return [];
		}

		if (! is_array($value) || ! array_is_list($value)) {
			throw self::wrongType($key, 'a list', $value);
		}

		$out = [];

		foreach ($value as $index => $item) {
			$out[] = self::toInt(sprintf('%s[%d]', $key, $index), $item);
		}

		return $out;
	}

	/**
	 * @return array<array-key,mixed>
	 *
	 * @throws ServerException
	 */
	private static function assertObject(mixed $value, string $key): array
	{
		// json_decode(..., true) renders an empty object as [], which is indistinguishable
		// from an empty list — accept it as the empty object.
		if (! is_array($value) || ($value !== [] && array_is_list($value))) {
			throw self::wrongType($key, 'an object', $value);
		}

		return $value;
	}

	/**
	 * @throws ServerException
	 */
	private static function toInt(string $key, mixed $value): int
	{
		if (is_int($value)) {
			return $value;
		}

		if (is_float($value) && is_finite($value) && floor($value) === $value) {
			if ($value >= -9.2233720368547758E18 && $value < 9.2233720368547758E18) {
				return (int) $value;
			}

			throw self::outOfRange($key, (string) $value);
		}

		if (is_string($value) && preg_match(self::BIG_INTEGER, $value) === 1) {
			throw self::outOfRange($key, $value);
		}

		throw self::wrongType($key, 'an integer', $value);
	}

	private static function wrongType(string $key, string $expected, mixed $value): ServerException
	{
		return new ServerException(sprintf(
			'Malformed API response: "%s" must be %s, got %s',
			$key,
			$expected,
			is_array($value) ? (array_is_list($value) ? 'a list' : 'an object') : get_debug_type($value),
		));
	}

	private static function outOfRange(string $key, string $value): ServerException
	{
		return new ServerException(sprintf(
			'Malformed API response: "%s" is an integer beyond PHP\'s range (%s)',
			$key,
			$value,
		));
	}
}
