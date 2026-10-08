<?php

declare(strict_types=1);

namespace QBitFlow\Support;

use QBitFlow\Enums\DurationUnit;
use QBitFlow\Enums\EventType;
use QBitFlow\Exceptions\FieldError;
use QBitFlow\Exceptions\ValidationException;
use QBitFlow\Models\Duration;

/**
 * The SDK's one set of client-side checks (behaviour §8, docs `validators.md`).
 *
 * They mirror the API's binding rules so a bad input fails before the round trip, with a
 * {@see ValidationException} naming each failing input by its wire name. Lengths count Unicode
 * code points. Rules that depend on the key's mode or on server state (the test-mode price
 * cap, https-only URLs in live mode, the frequency minimum, the 95-day export window,
 * uniqueness) are left to the API.
 *
 * @internal
 */
final class Validator
{
	/** The characters names and free text refuse. */
	public const TEXT_DISALLOWED = '<>{}[]`\\|;"~^';

	private const REFERENCE = '/^[A-Za-z0-9._:@-]+$/D';

	private const PHONE = '/^\+?[0-9][0-9 ().-]{4,}[0-9]$/D';

	private const UUID = '/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$/D';

	private const NIL_UUID = '00000000-0000-0000-0000-000000000000';

	/** The prefixes of the API's transaction ids (`pay@…`, `sub-hist@…`). */
	private const TX_PREFIXES = ['pay', 'sub', 'payg', 'sub-hist', 'refund', 'transfer'];

	/** Unicode White_Space (Go's `unicode.IsSpace`, `strings.TrimSpace`): NBSP included. */
	private const SPACE = '[\x{9}-\x{D}\x{20}\x{85}\x{A0}\x{1680}\x{2000}-\x{200A}\x{2028}\x{2029}\x{202F}\x{205F}\x{3000}]';

	/** Each duration unit's length, in seconds (months = 30 days, years = 365 days). */
	private const UNIT_SECONDS = [
		DurationUnit::SECONDS => 1,
		DurationUnit::MINUTES => 60,
		DurationUnit::HOURS => 3600,
		DurationUnit::DAYS => 86400,
		DurationUnit::WEEKS => 7 * 86400,
		DurationUnit::MONTHS => 30 * 86400,
		DurationUnit::YEARS => 365 * 86400,
	];

	/** @var list<FieldError> */
	private array $fields = [];

	/** Records a failing input; the message follows the field's name. */
	public function add(string $field, string $message): void
	{
		$this->fields[] = new FieldError($field, $field . ' ' . $message);
	}

	/**
	 * @throws ValidationException When any input failed.
	 */
	public function throwIfAny(): void
	{
		if ($this->fields !== []) {
			throw self::exception($this->fields);
		}
	}

	/**
	 * A client-side validation error ("validation failed", no status, no code).
	 *
	 * @param list<FieldError> $fields
	 */
	public static function exception(array $fields, string $message = 'validation failed'): ValidationException
	{
		return new ValidationException($message, fieldErrors: $fields);
	}

	/** A client-side validation error for one input. */
	public static function fieldError(string $field, string $message): ValidationException
	{
		return self::exception([new FieldError($field, $field . ' ' . $message)]);
	}

	/** Checks that a required string is set (not blank). Returns whether it is. */
	public function required(string $field, ?string $value): bool
	{
		if ($value === null || self::isBlank($value)) {
			$this->add($field, 'is required');

			return false;
		}

		return true;
	}

	/** Checks a code-point length (`$max` 0 = no maximum). Returns whether it passed. */
	public function length(string $field, string $value, int $min, int $max): bool
	{
		$n = self::codePoints($value);

		if ($max > 0 && $min > 0 && ($n < $min || $n > $max)) {
			$this->add($field, sprintf('must be %d to %d characters', $min, $max));
		} elseif ($min > 0 && $n < $min) {
			$this->add($field, sprintf('must be at least %d characters', $min));
		} elseif ($max > 0 && $n > $max) {
			$this->add($field, sprintf('must be at most %d characters', $max));
		} else {
			return true;
		}

		return false;
	}

	/** A name (product, customer), when set: one line, `$min`..`$max` characters. */
	public function name(string $field, ?string $value, int $min, int $max): void
	{
		if ($value === null || $value === '' || ! $this->length($field, $value, $min, $max)) {
			return;
		}
		if (! self::isName($value)) {
			$this->add($field, 'must be one line, not blank, without < > { } [ ] ` \\ | ; " ~ ^');
		}
	}

	/** Free text (descriptions, addresses, messages), when set: `$min`..`$max` characters. */
	public function text(string $field, ?string $value, int $min, int $max): void
	{
		if ($value === null || $value === '' || ! $this->length($field, $value, $min, $max)) {
			return;
		}
		if (! self::isText($value, true)) {
			$this->add($field, 'must not be blank nor contain control characters or < > { } [ ] ` \\ | ; " ~ ^');
		}
	}

	/** A merchant reference, when set: 1 to 100 of `A-Z a-z 0-9 . _ : @ -`. */
	public function reference(string $field, ?string $value): void
	{
		if ($value === null || $value === '' || ! $this->length($field, $value, 1, 100)) {
			return;
		}
		if (preg_match(self::REFERENCE, $value) !== 1) {
			$this->add($field, 'must contain only letters, digits and . _ : @ -');
		}
	}

	/** A phone number, when set: at most 32 characters, digits and `. - ( )` space, an optional `+`. */
	public function phone(string $field, ?string $value): void
	{
		if ($value === null || $value === '' || ! $this->length($field, $value, 0, 32)) {
			return;
		}
		if (preg_match(self::PHONE, $value) !== 1) {
			$this->add($field, 'must be a valid phone number');
		}
	}

	/** An email address, when set: at most 254 characters, structurally valid. */
	public function email(string $field, ?string $value): void
	{
		if ($value === null || $value === '' || ! $this->length($field, $value, 0, 254)) {
			return;
		}
		if (! self::isEmail($value)) {
			$this->add($field, 'must be a valid email address');
		}
	}

	/** A URL, when set: at most 2048 characters, absolute `http` or `https` with a host. */
	public function url(string $field, ?string $value): void
	{
		if ($value === null || $value === '' || ! $this->length($field, $value, 0, 2048)) {
			return;
		}
		if (! self::isHttpUrl($value)) {
			$this->add($field, 'must be an absolute http or https URL');
		}
	}

	/** A price in USD: finite and above 0. */
	public function price(string $field, float $value): void
	{
		if (! is_finite($value) || $value <= 0) {
			$this->add($field, 'must be a number above 0');
		}
	}

	/** A rate in percent: finite, from 0 (above 0 when `$positive`) to `$max`, at most 2 decimals. */
	public function percent(string $field, float $value, float $max, bool $positive): void
	{
		if (! is_finite($value) || $value < 0 || ($positive && $value == 0) || $value > $max || ! self::hasAtMostTwoDecimals($value)) {
			$this->add($field, sprintf(
				'must be a percentage from %s to %s, with at most 2 decimals',
				$positive ? 'above 0' : '0',
				self::formatNumber($max),
			));
		}
	}

	/**
	 * A Duration: a unit (one of the 7) is required when value > 0, and a unit given with 0
	 * must be one of them too. A frequency must be at least 1 unit and at most 1 year.
	 */
	public function duration(string $field, Duration $duration, bool $frequency): void
	{
		$unit = $duration->unit ?? '';
		$known = array_key_exists($unit, self::UNIT_SECONDS);

		if ($duration->value < 0 || $duration->value > 4294967295) {
			$this->add($field . '.value', 'must be from 0 to 4294967295');

			return;
		}
		if ($duration->value > 0 && $unit === '') {
			$this->add($field . '.unit', 'is required when value is above 0');

			return;
		}
		if ($unit !== '' && ! $known) {
			$this->add($field . '.unit', 'must be one of seconds, minutes, hours, days, weeks, months, years');

			return;
		}
		if (! $frequency) {
			return;
		}
		if ($duration->value < 1) {
			$this->add($field . '.value', 'must be at least 1');
		} elseif ($duration->value * self::UNIT_SECONDS[$unit] > 365 * 86400) {
			$this->add($field, 'must be at most 1 year');
		}
	}

	/** An integer bound. */
	public function intRange(string $field, int $value, int $min, int $max): void
	{
		if ($value < $min || $value > $max) {
			$this->add($field, sprintf('must be from %d to %d', $min, $max));
		}
	}

	/** A UUID, when set. */
	public function uuid(string $field, ?string $value): void
	{
		if ($value !== null && $value !== '' && ! self::isUuid($value)) {
			$this->add($field, 'must be a UUID');
		}
	}

	/** A transaction id (`pay@…`, `sub@…`, `sub-hist@…`, `refund@…`, or a bare UUID), when set. */
	public function txId(string $field, ?string $value): void
	{
		if ($value !== null && $value !== '' && ! self::isTxId($value)) {
			$this->add($field, 'must be a transaction id (pay@…, sub@…, sub-hist@… or a UUID)');
		}
	}

	/** An export window: two `YYYY-MM-DD` dates, `from <= to` (the API enforces the 95-day maximum). */
	public function dateRange(string $fromField, string $from, string $toField, string $to): void
	{
		$okFrom = $this->date($fromField, $from);
		$okTo = $this->date($toField, $to);
		if ($okFrom && $okTo && strcmp($from, $to) > 0) {
			$this->add($toField, 'must not be before ' . $fromField);
		}
	}

	/** Two filters that cannot be combined. */
	public function exclusive(string $fieldA, bool $a, string $fieldB, bool $b): void
	{
		if ($a && $b) {
			$this->add($fieldB, 'cannot be combined with ' . $fieldA);
		}
	}

	/**
	 * An enum value, when set, against the known values.
	 *
	 * @param list<string> $allowed
	 */
	public function oneOf(string $field, ?string $value, array $allowed): void
	{
		if ($value === null || $value === '' || in_array($value, $allowed, true)) {
			return;
		}
		$this->add($field, 'must be one of ' . implode(', ', $allowed));
	}

	/**
	 * A webhook endpoint's event list: at most 20, never `webhook.test`. Other values are not
	 * checked against a list (new types may appear).
	 *
	 * @param list<string> $events
	 */
	public function endpointEvents(string $field, array $events): void
	{
		if (count($events) > 20) {
			$this->add($field, 'must list at most 20 event types');
		}
		foreach (array_values($events) as $i => $event) {
			if ($event === '') {
				$this->add(sprintf('%s[%d]', $field, $i), 'is required');
			} elseif ($event === EventType::WEBHOOK_TEST) {
				$this->add(sprintf('%s[%d]', $field, $i), 'cannot be webhook.test (sent by the endpoint test only)');
			}
		}
	}

	private function date(string $field, string $value): bool
	{
		if (! $this->required($field, $value)) {
			return false;
		}
		if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/D', $value, $m) !== 1 || ! checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
			$this->add($field, 'must be a date (YYYY-MM-DD)');

			return false;
		}

		return true;
	}

	/** Whether `$value` is empty or only white space (Unicode). */
	public static function isBlank(string $value): bool
	{
		// Not valid UTF-8: PCRE fails on it, and such a value is not blank.
		return preg_match('/^' . self::SPACE . '*$/uD', $value) === 1;
	}

	/** The API's text rule: not blank, no control character (`\t \n \r` allowed when multiline), none of the 13 characters. */
	public static function isText(string $value, bool $multiline): bool
	{
		if (self::isBlank($value) || ! self::isUtf8($value)) {
			return false;
		}
		$controls = $multiline ? '[\x{0}-\x{8}\x{B}\x{C}\x{E}-\x{1F}\x{7F}-\x{9F}]' : '[\x{0}-\x{1F}\x{7F}-\x{9F}]';
		if (preg_match('/' . $controls . '/u', $value) === 1) {
			return false;
		}

		return strpbrk($value, self::TEXT_DISALLOWED) === false;
	}

	/** The API's name rule: the text rule on one line, without bidirectional controls. */
	public static function isName(string $value): bool
	{
		return self::isText($value, false) && preg_match('/[\x{202A}-\x{202E}\x{2066}-\x{2069}]/u', $value) !== 1;
	}

	/** One `@`, a non-empty local part, a dotted domain not starting or ending with a dot, no white space. */
	public static function isEmail(string $value): bool
	{
		if (substr_count($value, '@') !== 1 || preg_match('/' . self::SPACE . '/u', $value) === 1) {
			return false;
		}
		[$local, $domain] = explode('@', $value, 2);

		return $local !== '' && str_contains($domain, '.') && ! str_starts_with($domain, '.') && ! str_ends_with($domain, '.');
	}

	/** An absolute `http(s)` URL with a host. */
	public static function isHttpUrl(string $value): bool
	{
		// Control characters are refused anywhere, white space in the host (as Go's url.Parse does).
		if (preg_match('/[\x00-\x1F\x7F]/', $value) === 1) {
			return false;
		}
		$parts = parse_url($value);
		if ($parts === false || ! isset($parts['scheme'], $parts['host']) || $parts['host'] === '' || preg_match('/[\s<>"{}|\\^`]/', $parts['host']) === 1) {
			return false;
		}

		return in_array(strtolower($parts['scheme']), ['http', 'https'], true);
	}

	/** A UUID in its 8-4-4-4-12 hex form, any case. */
	public static function isUuid(string $value): bool
	{
		return preg_match(self::UUID, $value) === 1;
	}

	/** A UUID other than the nil UUID (an `On-Behalf-Of` value). */
	public static function isMemberUuid(string $value): bool
	{
		return self::isUuid($value) && $value !== self::NIL_UUID;
	}

	/** `<pay|sub|payg|sub-hist|refund|transfer>@<uuid>`, or a bare UUID. */
	public static function isTxId(string $value): bool
	{
		$at = strpos($value, '@');
		if ($at === false) {
			return self::isUuid($value);
		}

		return in_array(substr($value, 0, $at), self::TX_PREFIXES, true) && self::isUuid(substr($value, $at + 1));
	}

	/** 1 to 255 printable ASCII characters without spaces (0x21–0x7E). */
	public static function isIdempotencyKey(string $key): bool
	{
		return preg_match('/^[\x21-\x7E]{1,255}$/D', $key) === 1;
	}

	/** 1 to 128 of `A-Z a-z 0-9 - _ . :`. */
	public static function isRequestId(string $id): bool
	{
		return preg_match('/^[A-Za-z0-9\-_.:]{1,128}$/D', $id) === 1;
	}

	/**
	 * Checks a UUID path parameter (members, invitations, endpoints, customers, products): an
	 * empty one would address another route.
	 */
	public static function pathUuid(string $field, string $value): void
	{
		if ($value === '') {
			throw self::fieldError($field, 'is required');
		}
		if (! self::isUuid($value)) {
			throw self::fieldError($field, 'must be a UUID');
		}
	}

	/** Checks a transaction id path parameter (payments, subscriptions, bills, sessions). */
	public static function pathTxId(string $field, string $value): void
	{
		if ($value === '') {
			throw self::fieldError($field, 'is required');
		}
		if (! self::isTxId($value)) {
			throw self::fieldError($field, 'must be a transaction id (pay@…, sub@…, sub-hist@… or a UUID)');
		}
	}

	/** Checks a free-form path parameter (a reference, an email, an event id): only its presence. */
	public static function pathRequired(string $field, string $value): void
	{
		if (self::isBlank($value)) {
			throw self::fieldError($field, 'is required');
		}
		// "." and ".." would be resolved as relative path segments (by URL parsers and by the
		// server's path cleaning) and reach another route: refused, identically in every SDK.
		if ($value === '.' || $value === '..') {
			throw self::fieldError($field, 'cannot be "." or ".."');
		}
	}

	/** Whether a string is valid UTF-8. */
	public static function isUtf8(string $value): bool
	{
		return preg_match('//u', $value) === 1;
	}

	/** A string's length in Unicode code points (bytes for invalid UTF-8). */
	public static function codePoints(string $value): int
	{
		return self::isUtf8($value) ? (int) preg_match_all('/./su', $value) : strlen($value);
	}

	/** Whether the shortest decimal form of `$value` has at most 2 decimals. */
	private static function hasAtMostTwoDecimals(float $value): bool
	{
		return round($value * 100) / 100 === $value;
	}

	private static function formatNumber(float $value): string
	{
		return $value == floor($value) ? (string) (int) $value : (string) $value;
	}
}
