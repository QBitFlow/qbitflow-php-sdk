<?php

declare(strict_types=1);

namespace QBitFlow\Support;

use QBitFlow\Exceptions\ValidationException;

/**
 * Client-side mirrors of the API's `binding` validation rules.
 *
 * Checking these before the request is sent turns a round-trip and a 400 into an immediate,
 * specific exception, and keeps obviously unsafe values (markup, `javascript:` redirects)
 * from leaving the process at all.
 */
final class Validate
{
	/** Characters the API's `producttext` rule rejects (common XSS / injection payloads). */
	public const PRODUCT_TEXT_DISALLOWED = '<>{}[]`\\|;"~^';

	/**
	 * Mirror the API's `producttext` binding rule.
	 *
	 * Accepts letters (including accented and non-Latin), digits, spaces and ordinary
	 * punctuation; rejects angle brackets and other markup characters. Text that merely
	 * looks script-like (for example the literal `javascript:alert(1)`) is allowed — the
	 * server renders these fields as escaped text.
	 *
	 * @throws ValidationException When the value is blank, out of range, or contains markup
	 */
	public static function productText(string $field, ?string $value, int $min, int $max): void
	{
		// null and '' both mean "not provided": these fields are optional on the API
		// (`binding:"omitempty,..."`) and the server skips validation for an empty value.
		if ($value === null || $value === '') {
			return;
		}

		if (trim($value) === '') {
			throw new ValidationException("{$field} must not be blank");
		}

		// Count characters, not bytes, so multi-byte text is measured the way the server
		// measures it.
		$length = mb_strlen($value);
		if ($length < $min || $length > $max) {
			throw new ValidationException("{$field} must be between {$min} and {$max} characters");
		}

		$disallowed = str_split(self::PRODUCT_TEXT_DISALLOWED);
		foreach (mb_str_split($value) as $char) {
			if (in_array($char, $disallowed, true)) {
				throw new ValidationException("{$field} must not contain the character {$char}");
			}

			// Reject control characters, but allow the common whitespace ones.
			$code = mb_ord($char);
			if ($code !== false && $code < 32 && ! in_array($char, ["\n", "\r", "\t"], true)) {
				throw new ValidationException("{$field} must not contain control characters");
			}
		}
	}

	/**
	 * Check that a success/cancel redirect target is an absolute HTTP(S) URL.
	 *
	 * A redirect target is attacker-visible, so relative paths and non-web schemes such as
	 * `javascript:` are refused before the round-trip. Note that the API's own `uri` rule is
	 * more permissive than this.
	 *
	 * @throws ValidationException When the value is not an absolute http(s) URL
	 */
	public static function redirectUrl(string $field, ?string $value): void
	{
		// null and '' both mean "not provided", matching the API's omitempty.
		if ($value === null || $value === '') {
			return;
		}

		$scheme = parse_url($value, PHP_URL_SCHEME);
		$host = parse_url($value, PHP_URL_HOST);

		if (! in_array($scheme, ['http', 'https'], true) || $host === null || $host === false || $host === '') {
			throw new ValidationException("{$field} must be an absolute http:// or https:// URL");
		}
	}
}
