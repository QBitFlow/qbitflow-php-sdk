<?php

declare(strict_types=1);

namespace QBitFlow\Support;

use DateTimeImmutable;
use Exception;
use QBitFlow\Exceptions\ServerException;
use stdClass;

/**
 * Hydration helpers: the SDK's decoding policy (behaviour §6), shared by every model.
 *
 * Responses are decoded with JSON objects as `stdClass` and lists as PHP arrays, so `{}` and
 * `[]` stay distinct. A model's `fromArray()` receives the object's properties as an array
 * whose values may be `stdClass` (nested objects), lists, or scalars; plain associative arrays
 * are accepted for nested objects too.
 *
 * - **Absent or `null`** where the field is not nullable decodes to its zero value: `''`, `0`,
 *   `0.0`, `false`, `[]`, a zero-valued nested object, or Go's zero time
 *   (`0001-01-01T00:00:00Z`, see {@see Time::isZero()}). Never an error. The `nullable*`
 *   helpers return `null` instead.
 * - **Present with the wrong JSON type** raises a {@see ServerException} (the transport adds
 *   the HTTP status). Numeric widening is allowed: an integer into a float, an integral float
 *   into an integer.
 * - **Decimal strings** stay strings; **unknown enum values** are kept as the raw string;
 *   **unknown keys** are ignored.
 *
 * @internal
 */
final class Cast
{
	/** RFC 3339, with any offset and any number of fractional digits. */
	private const RFC3339 = '/^\d{4}-\d{2}-\d{2}[Tt]\d{2}:\d{2}:\d{2}(\.\d+)?([Zz]|[+-]\d{2}:\d{2})$/';

	/** A digit string JSON_BIGINT_AS_STRING produced for an integer beyond PHP's range. */
	private const BIG_INTEGER = '/^-?\d{19,}$/';

	private function __construct()
	{
	}

	/**
	 * A string field. Absent or null → `''`.
	 *
	 * @param array<array-key,mixed> $data
	 */
	public static function string(array $data, string $key): string
	{
		return self::nullableString($data, $key) ?? '';
	}

	/**
	 * A nullable (or optional) string field. Absent or null → null.
	 *
	 * @param array<array-key,mixed> $data
	 */
	public static function nullableString(array $data, string $key): ?string
	{
		$value = $data[$key] ?? null;
		if ($value === null) {
			return null;
		}
		if (! is_string($value)) {
			throw self::wrongType($key, 'a string', $value);
		}

		return $value;
	}

	/**
	 * A signed integer field. Absent or null → 0.
	 *
	 * @param array<array-key,mixed> $data
	 */
	public static function int(array $data, string $key): int
	{
		return self::nullableInt($data, $key) ?? 0;
	}

	/**
	 * @param array<array-key,mixed> $data
	 */
	public static function nullableInt(array $data, string $key): ?int
	{
		$value = $data[$key] ?? null;

		return $value === null ? null : self::toInt($key, $value);
	}

	/**
	 * An unsigned integer field, at most `$max` (a Go `uint8`/`uint16`/`uint32`/`uint64`).
	 * Absent or null → 0.
	 *
	 * @param array<array-key,mixed> $data
	 */
	public static function uint(array $data, string $key, int $max = PHP_INT_MAX): int
	{
		return self::nullableUint($data, $key, $max) ?? 0;
	}

	/**
	 * @param array<array-key,mixed> $data
	 */
	public static function nullableUint(array $data, string $key, int $max = PHP_INT_MAX): ?int
	{
		$value = $data[$key] ?? null;

		return $value === null ? null : self::toUint($key, $value, $max);
	}

	/**
	 * A float field. Absent or null → 0.0. An integer is widened.
	 *
	 * @param array<array-key,mixed> $data
	 */
	public static function float(array $data, string $key): float
	{
		return self::nullableFloat($data, $key) ?? 0.0;
	}

	/**
	 * @param array<array-key,mixed> $data
	 */
	public static function nullableFloat(array $data, string $key): ?float
	{
		$value = $data[$key] ?? null;
		if ($value === null) {
			return null;
		}
		if (is_int($value) || is_float($value)) {
			return (float) $value;
		}
		if (is_string($value) && preg_match(self::BIG_INTEGER, $value) === 1) {
			return (float) $value; // an integer beyond PHP's int, decoded as a digit string
		}

		throw self::wrongType($key, 'a number', $value);
	}

	/**
	 * A boolean field. Absent or null → false.
	 *
	 * @param array<array-key,mixed> $data
	 */
	public static function bool(array $data, string $key): bool
	{
		return self::nullableBool($data, $key) ?? false;
	}

	/**
	 * @param array<array-key,mixed> $data
	 */
	public static function nullableBool(array $data, string $key): ?bool
	{
		$value = $data[$key] ?? null;
		if ($value === null) {
			return null;
		}
		if (! is_bool($value)) {
			throw self::wrongType($key, 'a boolean', $value);
		}

		return $value;
	}

	/**
	 * A timestamp the API always sends. Absent or null → Go's zero time ({@see Time::isZero()}).
	 *
	 * @param array<array-key,mixed> $data
	 */
	public static function date(array $data, string $key): DateTimeImmutable
	{
		return self::nullableDate($data, $key) ?? Time::zero();
	}

	/**
	 * A nullable (or optional) timestamp: any RFC 3339 offset, microseconds kept.
	 *
	 * @param array<array-key,mixed> $data
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
			$date = new DateTimeImmutable($value);
		} catch (Exception $e) {
			throw new ServerException(sprintf('"%s" is not a valid timestamp (%s)', $key, $value), previous: $e);
		}

		// DateTime silently rolls an impossible date over (2026-02-30 → 2026-03-02): refuse it.
		if ($date->format('Y-m-d') !== substr($value, 0, 10)) {
			throw new ServerException(sprintf('"%s" is not a valid timestamp (%s)', $key, $value));
		}

		return $date;
	}

	/**
	 * A nested object the API always sends. Absent or null → the zero-valued object.
	 *
	 * @template T
	 *
	 * @param array<array-key,mixed>           $data
	 * @param callable(array<string,mixed>): T $factory
	 *
	 * @return T
	 */
	public static function object(array $data, string $key, callable $factory): mixed
	{
		$value = $data[$key] ?? null;

		return $factory($value === null ? [] : self::fields($value, $key));
	}

	/**
	 * A nullable (or optional) nested object. Absent or null → null.
	 *
	 * @template T
	 *
	 * @param array<array-key,mixed>           $data
	 * @param callable(array<string,mixed>): T $factory
	 *
	 * @return T|null
	 */
	public static function nullableObject(array $data, string $key, callable $factory): mixed
	{
		$value = $data[$key] ?? null;

		return $value === null ? null : $factory(self::fields($value, $key));
	}

	/**
	 * A list of objects. Absent or null → `[]`.
	 *
	 * @template T
	 *
	 * @param array<array-key,mixed>           $data
	 * @param callable(array<string,mixed>): T $factory
	 *
	 * @return list<T>
	 */
	public static function listOf(array $data, string $key, callable $factory): array
	{
		return self::list($data[$key] ?? null, $factory, $key);
	}

	/**
	 * A list of unsigned integers (currency ids). Absent or null → `[]`.
	 *
	 * @param array<array-key,mixed> $data
	 *
	 * @return list<int>
	 */
	public static function uintList(array $data, string $key): array
	{
		$out = [];
		foreach (self::rawList($data[$key] ?? null, $key) as $index => $item) {
			$out[] = self::toUint(sprintf('%s[%d]', $key, $index), $item, PHP_INT_MAX);
		}

		return $out;
	}

	/**
	 * A list of strings (enum values). Absent or null → `[]`.
	 *
	 * @param array<array-key,mixed> $data
	 *
	 * @return list<string>
	 */
	public static function stringList(array $data, string $key): array
	{
		$out = [];
		foreach (self::rawList($data[$key] ?? null, $key) as $index => $item) {
			if (! is_string($item)) {
				throw self::wrongType(sprintf('%s[%d]', $key, $index), 'a string', $item);
			}
			$out[] = $item;
		}

		return $out;
	}

	/**
	 * A raw JSON object, as an associative array (nested objects too). Absent or null → `[]`.
	 *
	 * @param array<array-key,mixed> $data
	 *
	 * @return array<array-key,mixed>
	 */
	public static function map(array $data, string $key): array
	{
		$value = $data[$key] ?? null;
		if ($value === null) {
			return [];
		}

		$plain = self::plain(self::fields($value, $key));
		assert(is_array($plain));

		return $plain;
	}

	/**
	 * A whole response body that must be a JSON object.
	 *
	 * @template T
	 *
	 * @param callable(array<string,mixed>): T $factory
	 *
	 * @return T
	 */
	public static function one(mixed $value, callable $factory): mixed
	{
		if ($value === null) {
			throw new ServerException('"response" must be an object, got null');
		}

		return $factory(self::fields($value, 'response'));
	}

	/**
	 * A list of objects: a whole response body, or a field's value. Absent or null → `[]`.
	 *
	 * @template T
	 *
	 * @param callable(array<string,mixed>): T $factory
	 *
	 * @return list<T>
	 */
	public static function list(mixed $value, callable $factory, string $key = 'response'): array
	{
		$out = [];
		foreach (self::rawList($value, $key) as $index => $item) {
			if ($item === null) {
				$out[] = $factory([]);

				continue;
			}
			$out[] = $factory(self::fields($item, sprintf('%s[%d]', $key, $index)));
		}

		return $out;
	}

	/**
	 * The properties of a JSON object (a `stdClass`, or an associative array).
	 *
	 * @return array<string,mixed>
	 */
	public static function fields(mixed $value, string $key): array
	{
		if ($value instanceof stdClass) {
			return get_object_vars($value);
		}
		// A non-empty associative array is an object too (a caller's own data). A list is not,
		// an empty one included: a decoded JSON `[]` where an object is expected is refused.
		if (is_array($value) && $value !== [] && ! array_is_list($value)) {
			/** @var array<string,mixed> $value */
			return $value;
		}

		throw self::wrongType($key, 'an object', $value);
	}

	/**
	 * A decoded JSON value with every `stdClass` turned into an associative array.
	 */
	public static function plain(mixed $value): mixed
	{
		if ($value instanceof stdClass) {
			$value = get_object_vars($value);
		}
		if (is_array($value)) {
			return array_map(self::plain(...), $value);
		}

		return $value;
	}

	/**
	 * @return list<mixed>
	 */
	private static function rawList(mixed $value, string $key): array
	{
		if ($value === null) {
			return [];
		}
		if (! is_array($value) || ! array_is_list($value)) {
			throw self::wrongType($key, 'a list', $value);
		}

		return $value;
	}

	private static function toInt(string $key, mixed $value): int
	{
		if (is_int($value)) {
			return $value;
		}
		if (is_float($value) && is_finite($value) && floor($value) === $value) {
			if ($value >= -9.2233720368547758E18 && $value < 9.2233720368547758E18) {
				return (int) $value;
			}

			throw self::outOfRange($key, $value);
		}
		if (is_string($value) && preg_match(self::BIG_INTEGER, $value) === 1) {
			throw self::outOfRange($key, $value);
		}

		throw self::wrongType($key, 'an integer', $value);
	}

	private static function toUint(string $key, mixed $value, int $max): int
	{
		$int = self::toInt($key, $value);
		if ($int < 0 || $int > $max) {
			throw self::outOfRange($key, $value);
		}

		return $int;
	}

	private static function wrongType(string $key, string $expected, mixed $value): ServerException
	{
		$got = match (true) {
			$value instanceof stdClass => 'an object',
			is_array($value) => array_is_list($value) ? 'a list' : 'an object',
			default => get_debug_type($value),
		};

		return new ServerException(sprintf('"%s" must be %s, got %s', $key, $expected, $got));
	}

	private static function outOfRange(string $key, mixed $value): ServerException
	{
		return new ServerException(sprintf('"%s" is out of range (%s)', $key, is_scalar($value) ? (string) $value : get_debug_type($value)));
	}
}
