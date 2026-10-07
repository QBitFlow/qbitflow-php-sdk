<?php

declare(strict_types=1);

namespace QBitFlow\Support;

use DateTimeImmutable;
use QBitFlow\Exceptions\ValidationException;

/**
 * Client-side mirrors of the API's `binding` validation rules.
 *
 * Checking these before the request is sent turns a round-trip and a 400 into an immediate,
 * specific exception, and keeps obviously unsafe values (markup, `javascript:` redirects)
 * from leaving the process at all. Every QBitFlow SDK applies the same rule set, matched
 * against the live API.
 */
final class Validate
{
	/** Characters the API's `producttext` rule rejects (common XSS / injection payloads). */
	public const PRODUCT_TEXT_DISALLOWED = '<>{}[]`\\|;"~^';

	/** Punctuation the API's `alphanumspace` rule allows besides letters, digits and spaces. */
	public const ALPHANUM_SPACE_PUNCTUATION = "-_'.";

	/** Largest value of a Go `uint32`, the type of every duration value and `minPeriods`. */
	public const UINT32_MAX = 4294967295;

	/** A bare UUID, 8-4-4-4-12 hex digits, as the API's `uuid.UUID` fields expect. */
	private const UUID = '/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$/';

	/**
	 * Mirror the API's `producttext` binding rule.
	 *
	 * Accepts letters (including accented and non-Latin), digits, spaces and ordinary
	 * punctuation; rejects angle brackets and other markup characters, and control
	 * characters other than tab, newline and carriage return. Text that merely looks
	 * script-like (for example the literal `javascript:alert(1)`) is allowed — the server
	 * renders these fields as escaped text. A value that is only whitespace (Unicode
	 * whitespace included) is rejected. Length is counted in characters, not bytes.
	 *
	 * `null` means "not provided"; pass `''` through only where an empty value is an error.
	 *
	 * @throws ValidationException When the value is blank, out of range, or contains markup
	 */
	public static function productText(string $field, ?string $value, int $min, int $max): void
	{
		if ($value === null) {
			return;
		}

		if (preg_match('/^[\s\p{Z}\x{0085}]*$/u', $value) === 1) {
			throw new ValidationException("{$field} must not be blank");
		}

		self::length($field, $value, $min, $max);

		$disallowed = str_split(self::PRODUCT_TEXT_DISALLOWED);
		foreach (mb_str_split($value) as $char) {
			if (in_array($char, $disallowed, true)) {
				throw new ValidationException("{$field} must not contain the character {$char}");
			}

			if (self::isControl($char) && ! in_array($char, ["\n", "\r", "\t"], true)) {
				throw new ValidationException("{$field} must not contain control characters");
			}
		}
	}

	/**
	 * Mirror the API's `alphanumspace` binding rule, used for people's names.
	 *
	 * Every character must be a letter (any script), a decimal digit (Unicode category Nd
	 * — `²`, `½` and Roman numerals are rejected, as the API rejects them), a space, or one
	 * of `-`, `_`, `'` and `.`. Markup, `&`, commas, parentheses and the like are rejected.
	 * Length is counted in characters. A value made only of spaces is accepted, as the API
	 * accepts it.
	 *
	 * `null` and `''` mean "not provided" — the create DTOs check their required fields
	 * with {@see Validate::required()} first.
	 *
	 * @throws ValidationException When the value is out of range or has a disallowed character
	 */
	public static function alphanumSpace(string $field, ?string $value, int $min, int $max): void
	{
		if ($value === null || $value === '') {
			return;
		}

		self::length($field, $value, $min, $max);

		foreach (mb_str_split($value) as $char) {
			$allowed = $char === ' '
				|| str_contains(self::ALPHANUM_SPACE_PUNCTUATION, $char)
				|| preg_match('/^[\p{L}\p{Nd}]$/u', $char) === 1;

			if (! $allowed) {
				throw new ValidationException(
					"{$field} may only contain letters, digits, spaces and - _ ' . (found {$char})",
				);
			}
		}
	}

	/**
	 * Check that a value looks like an email address: exactly one `@`, a non-empty local
	 * part, a domain that contains a dot and neither starts nor ends with one, and no
	 * whitespace. The value is never normalised — the API preserves case.
	 *
	 * `null` and `''` mean "not provided".
	 *
	 * @throws ValidationException When the value is not a plausible email address
	 */
	public static function email(string $field, ?string $value): void
	{
		if ($value === null || $value === '') {
			return;
		}

		$parts = explode('@', $value);

		$valid = count($parts) === 2
			&& $parts[0] !== ''
			&& str_contains($parts[1], '.')
			&& ! str_starts_with($parts[1], '.')
			&& ! str_ends_with($parts[1], '.')
			&& preg_match('/\s/u', $value) !== 1;

		if (! $valid) {
			throw new ValidationException("{$field} must be a valid email address");
		}
	}

	/**
	 * Guard a field the API marks `binding:"required"`: it must not be empty. (A value of
	 * only spaces is not empty — the API accepts it for names.)
	 *
	 * @throws ValidationException When the value is empty
	 */
	public static function required(string $field, string $value): void
	{
		if ($value === '') {
			throw new ValidationException("{$field} is required");
		}
	}

	/**
	 * Check that a success/cancel redirect target is an absolute HTTP(S) URL, mirroring the
	 * API's `http_url` rule (the scheme is case-insensitive).
	 *
	 * A redirect target is attacker-visible, so relative paths and non-web schemes such as
	 * `javascript:` are refused before the round-trip. `null` and `''` mean "not provided".
	 *
	 * @throws ValidationException When the value is not an absolute http(s) URL
	 */
	public static function redirectUrl(string $field, ?string $value): void
	{
		if ($value === null || $value === '') {
			return;
		}

		$scheme = parse_url($value, PHP_URL_SCHEME);
		$host = parse_url($value, PHP_URL_HOST);

		// The API parses with Go's url.Parse, which rejects leading whitespace and whitespace or
		// control characters in the host — `parse_url()` tolerates both, so check the text as
		// written: an http(s) scheme followed directly by a non-empty authority.
		if (preg_match('~^https?://[^/?#\\\\\s]~i', $value) !== 1
			|| ! is_string($scheme)
			|| ! in_array(strtolower($scheme), ['http', 'https'], true)
			|| ! is_string($host)
			|| $host === ''
			|| preg_match('/[\s\x00-\x1f\x7f]/u', $host) === 1) {
			throw new ValidationException("{$field} must be an absolute http:// or https:// URL");
		}
	}

	/**
	 * Check a price: finite and strictly greater than zero.
	 *
	 * @throws ValidationException
	 */
	public static function price(string $field, ?float $value): void
	{
		if ($value === null) {
			return;
		}

		if (! is_finite($value) || $value <= 0) {
			throw new ValidationException("{$field} must be a finite number greater than 0");
		}
	}

	/**
	 * Check a bare UUID (`8-4-4-4-12` hex digits, any case), as a `uuid.UUID` body field
	 * expects. Prefixed identifiers such as `pay@…` are rejected. `null` and `''` mean
	 * "not provided".
	 *
	 * @throws ValidationException
	 */
	public static function uuid(string $field, ?string $value): void
	{
		if ($value === null || $value === '') {
			return;
		}

		if (preg_match(self::UUID, $value) !== 1) {
			throw new ValidationException("{$field} must be a bare UUID (xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx)");
		}
	}

	/**
	 * Check an integer against an inclusive range.
	 *
	 * @throws ValidationException
	 */
	public static function intRange(string $field, int $value, int $min, int $max): void
	{
		if ($value < $min || $value > $max) {
			throw new ValidationException("{$field} must be between {$min} and {$max}");
		}
	}

	/**
	 * Check an accounting-export window: both dates required and formatted `YYYY-MM-DD`
	 * (real calendar dates), and `from` not after `to`. The window length is left to the
	 * API.
	 *
	 * @throws ValidationException When a date is missing or malformed, or the window is inverted
	 */
	public static function accountingWindow(string $from, string $to): void
	{
		$start = self::isoDate('from', $from);
		$end = self::isoDate('to', $to);

		if ($start > $end) {
			throw new ValidationException("'from' date must not be after 'to' date");
		}
	}

	/**
	 * @throws ValidationException
	 */
	private static function length(string $field, string $value, int $min, int $max): void
	{
		$length = mb_strlen($value);

		if ($length < $min || $length > $max) {
			throw new ValidationException("{$field} must be between {$min} and {$max} characters");
		}
	}

	/**
	 * Parse a `YYYY-MM-DD` date, rejecting anything else (including impossible dates).
	 *
	 * @throws ValidationException
	 */
	private static function isoDate(string $field, string $value): DateTimeImmutable
	{
		if ($value === '') {
			throw new ValidationException("{$field} date is required (YYYY-MM-DD)");
		}

		if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m) !== 1
			|| ! checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
			throw new ValidationException("{$field} must be a date formatted YYYY-MM-DD");
		}

		return new DateTimeImmutable($value . 'T00:00:00+00:00');
	}

	/**
	 * Go's `unicode.IsControl`: C0 (U+0000–U+001F), DEL and C1 (U+007F–U+009F).
	 */
	private static function isControl(string $char): bool
	{
		$code = mb_ord($char);

		return $code !== false && ($code < 0x20 || ($code >= 0x7F && $code <= 0x9F));
	}
}
