<?php

declare(strict_types=1);

namespace QBitFlow\Webhooks;

use ArrayObject;
use BackedEnum;
use JsonSerializable;
use QBitFlow\Exceptions\ValidationException;
use UnitEnum;

/**
 * Go-compatible canonical JSON, as QBitFlow signs webhooks.
 *
 * The API signs `json.Marshal` of the payload decoded into Go's generic representation
 * (`any`), so the canonical form is exactly what Go's `encoding/json` produces:
 *
 * - object keys sorted by their UTF-8 bytes, duplicate keys resolved last-wins;
 * - no insignificant whitespace;
 * - every number decoded as a float64 and rendered with Go's shortest round-trip rules
 *   (`1e-7`, `100`, `1e+21`, `-0`), **independent of PHP's `serialize_precision`**;
 * - strings escaped as Go does: `"`, `\`, `\b`, `\f`, `\n`, `\r`, `\t`, other C0 controls as
 *   `\u00xx`, `<` `>` `&` as `<` `>` `&`, U+2028/U+2029 escaped, `/` and all
 *   other characters (DEL and C1 included) literal;
 * - invalid UTF-8 bytes and unpaired UTF-16 surrogate escapes become U+FFFD.
 *
 * Raw JSON is read with a strict parser of its own rather than `json_decode`, which cannot
 * keep `{}` apart from `[]` in associative mode, turns the literal `-0` into `0`, rejects
 * lone surrogates, and would need `JSON_BIGINT_AS_STRING` guesswork for long digit strings.
 * Objects are held as {@see ArrayObject}, lists as PHP lists.
 *
 * @internal Use {@see WebhookVerifier::canonicalJson()}.
 */
final class CanonicalJson
{
	/** Go's maximum nesting depth for `encoding/json`. */
	private const MAX_DEPTH = 10000;

	private const REPLACEMENT = "\u{FFFD}";

	/** @var array<string,string>|null */
	private static ?array $escapes = null;

	private int $pos = 0;

	private readonly int $length;

	private function __construct(private readonly string $json)
	{
		$this->length = strlen($json);
	}

	/**
	 * Canonicalise raw JSON, or an already-decoded PHP value.
	 *
	 * @throws ValidationException When the JSON is invalid or the value cannot be represented.
	 */
	public static function canonicalize(string|array|object $payload): string
	{
		return self::encode(is_string($payload) ? self::parse($payload) : self::fromValue($payload));
	}

	/**
	 * Parse raw JSON as Go's `json.Unmarshal` into `any` does.
	 *
	 * @return mixed `null`, `bool`, `float`, `string`, a list, or an {@see ArrayObject} for an object.
	 *
	 * @throws ValidationException When the input is not valid JSON.
	 */
	public static function parse(string $json): mixed
	{
		$parser = new self($json);
		$parser->skipWhitespace();
		$value = $parser->value(0);
		$parser->skipWhitespace();

		if ($parser->pos !== $parser->length) {
			throw $parser->error('unexpected data after the top-level value');
		}

		return $value;
	}

	/**
	 * Convert an already-decoded PHP value into the canonical tree.
	 *
	 * Associative arrays and objects become JSON objects, lists become JSON arrays. An empty
	 * PHP array is ambiguous and becomes `[]` — pass the raw body, or decode with
	 * `json_decode($body, false)`, to keep an empty object `{}`.
	 *
	 * @throws ValidationException When the value holds something JSON cannot represent.
	 */
	public static function fromValue(mixed $value, int $depth = 0): mixed
	{
		if ($depth > self::MAX_DEPTH) {
			throw new ValidationException('webhook payload is nested too deeply');
		}

		return match (true) {
			$value === null, is_bool($value) => $value,
			is_int($value), is_float($value) => (float) $value,
			is_string($value) => self::scrub($value),
			$value instanceof BackedEnum => self::fromValue($value->value, $depth),
			$value instanceof UnitEnum => throw new ValidationException(
				'webhook payload contains a non-backed enum, which JSON cannot represent',
			),
			$value instanceof JsonSerializable => self::fromValue($value->jsonSerialize(), $depth + 1),
			is_array($value) && array_is_list($value) => array_map(
				static fn (mixed $item): mixed => self::fromValue($item, $depth + 1),
				$value,
			),
			is_array($value) => self::objectFrom($value, $depth),
			is_object($value) => self::objectFrom(get_object_vars($value), $depth),
			default => throw new ValidationException(
				'webhook payload contains a value that is not JSON: ' . get_debug_type($value),
			),
		};
	}

	/**
	 * Render a canonical tree (from {@see parse()} or {@see fromValue()}).
	 *
	 * @throws ValidationException When a number is not finite.
	 */
	public static function encode(mixed $node): string
	{
		if ($node === null) {
			return 'null';
		}

		if (is_bool($node)) {
			return $node ? 'true' : 'false';
		}

		if (is_float($node)) {
			return self::formatNumber($node);
		}

		if (is_string($node)) {
			return self::encodeString($node);
		}

		if ($node instanceof ArrayObject) {
			$members = $node->getArrayCopy();
			// Go sorts map keys by their bytes; SORT_STRING compares bytes too.
			ksort($members, SORT_STRING);

			$out = [];
			foreach ($members as $key => $item) {
				$out[] = self::encodeString((string) $key) . ':' . self::encode($item);
			}

			return '{' . implode(',', $out) . '}';
		}

		if (is_array($node)) {
			return '[' . implode(',', array_map(self::encode(...), $node)) . ']';
		}

		throw new ValidationException('webhook payload contains a value that is not JSON: ' . get_debug_type($node));
	}

	/**
	 * Render a float64 exactly the way Go's `encoding/json` (and JavaScript) do: the shortest
	 * digit string that round-trips, in plain decimal for exponents in [-6, 21) and in
	 * `d.ddde±x` form outside that range, with no `.0` on whole numbers and no zero-padded
	 * exponent. Negative zero keeps its sign.
	 *
	 * @throws ValidationException When the number is NaN or infinite.
	 */
	public static function formatNumber(float $value): string
	{
		if (is_nan($value) || is_infinite($value)) {
			throw new ValidationException('webhook payload contains a non-finite number');
		}

		if ($value === 0.0) {
			return fdiv(1.0, $value) < 0 ? '-0' : '0';
		}

		[$digits, $position] = self::shortestDigits(abs($value));
		$count = strlen($digits);

		if ($count <= $position && $position <= 21) {
			$rendered = $digits . str_repeat('0', $position - $count);
		} elseif (0 < $position && $position <= 21) {
			$rendered = substr($digits, 0, $position) . '.' . substr($digits, $position);
		} elseif (-6 < $position && $position <= 0) {
			$rendered = '0.' . str_repeat('0', -$position) . $digits;
		} else {
			$exp = $position - 1;
			$rendered = $digits[0] . ($count > 1 ? '.' . substr($digits, 1) : '');
			$rendered .= 'e' . ($exp > 0 ? '+' : '-') . abs($exp);
		}

		return ($value < 0 ? '-' : '') . $rendered;
	}

	/**
	 * The shortest decimal digits that round-trip to `$value` (> 0), and the position of the
	 * decimal point: `$value == 0.<digits> × 10^position`.
	 *
	 * @return array{string, int}
	 */
	private static function shortestDigits(float $value): array
	{
		// PHP's own shortest round-trip conversion (zend_dtoa mode 0, the same result as Go's)
		// is used whenever `serialize_precision` is -1, which is forced here for the call.
		$previous = ini_get('serialize_precision');
		$forced = $previous === '-1' || ini_set('serialize_precision', '-1') !== false;

		try {
			$shortest = $forced && ini_get('serialize_precision') === '-1' ? json_encode($value) : false;
		} finally {
			if ($previous !== false && $previous !== '-1') {
				ini_set('serialize_precision', $previous);
			}
		}

		if (! is_string($shortest)) {
			// ini_set is unavailable: find the shortest correctly rounded digits that round-trip.
			for ($precision = 0; $precision < 17; $precision++) {
				$shortest = sprintf('%.' . $precision . 'e', $value);

				if ((float) $shortest === $value) {
					break;
				}
			}
		}

		[$mantissa, $exponent] = array_pad(explode('e', strtolower((string) $shortest), 2), 2, '0');
		[$intPart, $fracPart] = array_pad(explode('.', $mantissa, 2), 2, '');
		$digits = $intPart . $fracPart;
		$position = strlen($intPart) + (int) $exponent;

		$stripped = ltrim($digits, '0');
		$position -= strlen($digits) - strlen($stripped);

		return [rtrim($stripped, '0'), $position];
	}

	/**
	 * Quote a (valid UTF-8) string the way Go's `encoding/json` does.
	 */
	private static function encodeString(string $value): string
	{
		if (self::$escapes === null) {
			$map = [
				'"' => '\\"',
				'\\' => '\\\\',
				'<' => '\\u003c',
				'>' => '\\u003e',
				'&' => '\\u0026',
				"\u{2028}" => '\\u2028',
				"\u{2029}" => '\\u2029',
			];

			for ($c = 0; $c < 0x20; $c++) {
				$map[chr($c)] = match ($c) {
					0x08 => '\\b',
					0x09 => '\\t',
					0x0A => '\\n',
					0x0C => '\\f',
					0x0D => '\\r',
					default => sprintf('\\u%04x', $c),
				};
			}

			self::$escapes = $map;
		}

		return '"' . strtr($value, self::$escapes) . '"';
	}

	/**
	 * Replace every invalid UTF-8 byte with U+FFFD, one per byte, as Go does.
	 */
	private static function scrub(string $value): string
	{
		if (mb_check_encoding($value, 'UTF-8')) {
			return $value;
		}

		return (string) preg_replace_callback(
			'/[\x00-\x7F]|[\xC2-\xDF][\x80-\xBF]|\xE0[\xA0-\xBF][\x80-\xBF]|[\xE1-\xEC\xEE\xEF][\x80-\xBF]{2}'
				. '|\xED[\x80-\x9F][\x80-\xBF]|\xF0[\x90-\xBF][\x80-\xBF]{2}|[\xF1-\xF3][\x80-\xBF]{3}'
				. '|\xF4[\x80-\x8F][\x80-\xBF]{2}|(.)/s',
			static fn (array $m): string => isset($m[1]) ? self::REPLACEMENT : $m[0],
			$value,
		);
	}

	/**
	 * @param array<array-key,mixed> $properties
	 *
	 * @throws ValidationException
	 */
	private static function objectFrom(array $properties, int $depth): ArrayObject
	{
		$members = [];

		foreach ($properties as $key => $item) {
			$members[self::scrub((string) $key)] = self::fromValue($item, $depth + 1);
		}

		return new ArrayObject($members);
	}

	// ---------------------------------------------------------------------------------
	// Parser
	// ---------------------------------------------------------------------------------

	/**
	 * @throws ValidationException
	 */
	private function value(int $depth): mixed
	{
		if ($depth > self::MAX_DEPTH) {
			throw $this->error('nested too deeply');
		}

		$char = $this->json[$this->pos] ?? '';

		return match (true) {
			$char === '{' => $this->object($depth),
			$char === '[' => $this->list($depth),
			$char === '"' => $this->string(),
			$char === '-' || ctype_digit($char) => $this->number(),
			default => $this->literal(),
		};
	}

	/**
	 * @throws ValidationException
	 */
	private function object(int $depth): ArrayObject
	{
		$this->pos++;
		$members = [];
		$this->skipWhitespace();

		if (($this->json[$this->pos] ?? '') === '}') {
			$this->pos++;

			return new ArrayObject($members);
		}

		while (true) {
			$this->skipWhitespace();

			if (($this->json[$this->pos] ?? '') !== '"') {
				throw $this->error('expected an object key');
			}

			$key = $this->string();
			$this->skipWhitespace();

			if (($this->json[$this->pos] ?? '') !== ':') {
				throw $this->error('expected ":" after an object key');
			}

			$this->pos++;
			$this->skipWhitespace();
			// Last wins, as in a Go map.
			$members[$key] = $this->value($depth + 1);
			$this->skipWhitespace();

			$char = $this->json[$this->pos] ?? '';
			$this->pos++;

			if ($char === '}') {
				return new ArrayObject($members);
			}

			if ($char !== ',') {
				$this->pos--;

				throw $this->error('expected "," or "}" in an object');
			}
		}
	}

	/**
	 * @return list<mixed>
	 *
	 * @throws ValidationException
	 */
	private function list(int $depth): array
	{
		$this->pos++;
		$items = [];
		$this->skipWhitespace();

		if (($this->json[$this->pos] ?? '') === ']') {
			$this->pos++;

			return $items;
		}

		while (true) {
			$this->skipWhitespace();
			$items[] = $this->value($depth + 1);
			$this->skipWhitespace();

			$char = $this->json[$this->pos] ?? '';
			$this->pos++;

			if ($char === ']') {
				return $items;
			}

			if ($char !== ',') {
				$this->pos--;

				throw $this->error('expected "," or "]" in an array');
			}
		}
	}

	/**
	 * @throws ValidationException
	 */
	private function string(): string
	{
		$this->pos++;
		$out = '';

		while (true) {
			// A run of ordinary bytes: anything but a quote, a backslash or a C0 control.
			$run = strcspn($this->json, "\"\\\x00\x01\x02\x03\x04\x05\x06\x07\x08\x09\x0A\x0B\x0C\x0D\x0E\x0F"
				. "\x10\x11\x12\x13\x14\x15\x16\x17\x18\x19\x1A\x1B\x1C\x1D\x1E\x1F", $this->pos);
			$out .= substr($this->json, $this->pos, $run);
			$this->pos += $run;

			$char = $this->json[$this->pos] ?? '';

			if ($char === '"') {
				$this->pos++;

				return self::scrub($out);
			}

			if ($char !== '\\') {
				throw $this->error($char === '' ? 'unterminated string' : 'control character in a string');
			}

			$escape = $this->json[$this->pos + 1] ?? '';
			$this->pos += 2;

			$out .= match ($escape) {
				'"' => '"',
				'\\' => '\\',
				'/' => '/',
				'b' => "\x08",
				'f' => "\x0C",
				'n' => "\n",
				'r' => "\r",
				't' => "\t",
				'u' => $this->unicodeEscape(),
				default => throw $this->error('invalid escape sequence in a string'),
			};
		}
	}

	/**
	 * Decode a `\uXXXX` escape (the `\u` already consumed), pairing surrogates as Go does:
	 * a valid high/low pair is one character; any other surrogate becomes U+FFFD, and the
	 * escape that followed it is decoded on its own.
	 *
	 * @throws ValidationException
	 */
	private function unicodeEscape(): string
	{
		$code = $this->hex4($this->pos);

		if ($code === null) {
			throw $this->error('invalid \\u escape in a string');
		}

		$this->pos += 4;

		if ($code >= 0xD800 && $code <= 0xDFFF) {
			if ($code <= 0xDBFF && substr($this->json, $this->pos, 2) === '\\u') {
				$low = $this->hex4($this->pos + 2);

				if ($low !== null && $low >= 0xDC00 && $low <= 0xDFFF) {
					$this->pos += 6;

					return (string) mb_chr(0x10000 + (($code - 0xD800) << 10) + ($low - 0xDC00), 'UTF-8');
				}
			}

			return self::REPLACEMENT;
		}

		return (string) mb_chr($code, 'UTF-8');
	}

	private function hex4(int $at): ?int
	{
		$hex = substr($this->json, $at, 4);

		return strlen($hex) === 4 && ctype_xdigit($hex) ? (int) hexdec($hex) : null;
	}

	/**
	 * @throws ValidationException
	 */
	private function number(): float
	{
		if (preg_match('/\G-?(?:0|[1-9][0-9]*)(?:\.[0-9]+)?(?:[eE][+-]?[0-9]+)?/', $this->json, $m, 0, $this->pos) !== 1) {
			throw $this->error('invalid number');
		}

		$this->pos += strlen($m[0]);
		$value = (float) $m[0];

		if (is_infinite($value)) {
			// Go refuses a number that overflows a float64.
			throw $this->error('number out of range');
		}

		return $value;
	}

	/**
	 * @throws ValidationException
	 */
	private function literal(): ?bool
	{
		foreach (['true' => true, 'false' => false, 'null' => null] as $word => $value) {
			if (substr_compare($this->json, $word, $this->pos, strlen($word)) === 0) {
				$this->pos += strlen($word);

				return $value;
			}
		}

		throw $this->error($this->pos >= $this->length ? 'unexpected end of input' : 'invalid character');
	}

	private function skipWhitespace(): void
	{
		$this->pos += strspn($this->json, " \t\n\r", $this->pos);
	}

	private function error(string $reason): ValidationException
	{
		return new ValidationException(sprintf('webhook payload is not valid JSON: %s at offset %d', $reason, $this->pos));
	}
}
