<?php

declare(strict_types=1);

namespace QBitFlow\Webhooks;

use QBitFlow\Exceptions\ValidationException;

/**
 * Local webhook signature verification.
 *
 * Verifying locally needs your webhook secret but no network call, so it keeps working when
 * the API is unreachable and costs nothing per webhook. The alternative,
 * `$client->webhooks->verify()`, asks QBitFlow to check the signature for you: no secret
 * required, but one round-trip per webhook.
 */
final class WebhookVerifier
{
	/** Header carrying the HMAC signature, formatted `sha256=<hex>`. */
	public const HEADER_SIGNATURE = 'X-Webhook-Signature-256';

	/** Header carrying the send time, in unix seconds. */
	public const HEADER_TIMESTAMP = 'X-Webhook-Timestamp';

	/** Header carrying the transaction id, e.g. `pay@<uuid>`. */
	public const HEADER_WEBHOOK_ID = 'X-Webhook-Id';

	/** The dashboard "Test webhook" id. */
	public const TEST_WEBHOOK_ID = 'test-webhook-id';

	/**
	 * How far a webhook's timestamp may be from the current clock before it is rejected as
	 * a replay. Must match the server's MaxTimestampAge.
	 */
	public const DEFAULT_MAX_TIMESTAMP_AGE_SECONDS = 300;

	private const SIGNATURE_PREFIX = 'sha256=';

	/**
	 * Render a webhook payload the way QBitFlow signs it.
	 *
	 * The signature covers a *canonical* rendering rather than the bytes as they arrived,
	 * because JSON key order is not significant and intermediaries (proxies, frameworks,
	 * logging layers) routinely re-serialize a body and reorder keys. Signing raw bytes
	 * would make verification fail for a payload that is in fact untouched.
	 *
	 * Canonical means exactly what Go's `encoding/json` produces for the payload decoded
	 * into `any` (the QBitFlow API is written in Go): object keys sorted by their UTF-8 bytes
	 * at every level, no insignificant whitespace, `<`, `>`, `&`, U+2028 and U+2029 escaped
	 * as `\u003c`, `\u003e`, `\u0026`, `\u2028`, `\u2029`, strings otherwise literal (decimal
	 * amounts such as `"1000000000000000000"` stay strings), and every number rendered from
	 * its float64 value under Go's formatting rules — independent of PHP's
	 * `serialize_precision`. Every QBitFlow SDK produces the same bytes, and each SDK's test
	 * suite pins the same Go-generated reference vectors.
	 *
	 * Pass the raw body when you can. An already-decoded PHP array works too, but an empty
	 * PHP array is ambiguous and renders as `[]` — decode with `json_decode($body, false)` to
	 * keep an empty object `{}`.
	 *
	 * @param string|array<mixed>|object $payload Raw JSON, or an already-decoded value
	 * @throws ValidationException When the payload is missing or not valid JSON
	 */
	public static function canonicalJson(string|array|object|null $payload): string
	{
		if ($payload === null) {
			throw new ValidationException('webhook payload is required');
		}

		return CanonicalJson::canonicalize($payload);
	}

	/**
	 * Compute the signature QBitFlow would send for a payload.
	 *
	 * The signed message is `<timestamp>.<canonical-json>`; the result is the hex-encoded
	 * HMAC-SHA256 of that message under your webhook secret, prefixed with `sha256=` —
	 * exactly the value delivered in the `X-Webhook-Signature-256` header.
	 *
	 * Public mainly so you can generate valid webhooks in your own tests. To check an
	 * incoming webhook use {@see verify()}, which also enforces the replay window and
	 * compares in constant time.
	 *
	 * @param string $secret Your webhook secret, from the QBitFlow dashboard
	 * @param string $timestamp The `X-Webhook-Timestamp` value
	 * @param string|array<mixed>|object $payload The webhook body
	 */
	public static function computeSignature(
		string $secret,
		string $timestamp,
		string|array|object|null $payload
	): string {
		if ($secret === '') {
			throw new ValidationException('webhook secret is required');
		}

		if ($timestamp === '') {
			throw new ValidationException('webhook timestamp is required');
		}

		$message = $timestamp . '.' . self::canonicalJson($payload);

		return self::SIGNATURE_PREFIX . hash_hmac('sha256', $message, $secret);
	}

	/**
	 * Verify a webhook locally, without calling the QBitFlow API.
	 *
	 * **Argument order:** `verify($secret, $timestamp, $signature, $payload)` — timestamp
	 * *before* signature. (The API-backed `$client->webhooks->verify($payload, $signature,
	 * $timestamp)` takes them the other way round; both are plain strings, so a swap fails
	 * verification rather than erroring. Named arguments avoid the trap.)
	 *
	 * Performs the same three checks the server does:
	 *
	 * 1. The timestamp is within `$maxTimestampAgeSeconds` of now, which is what stops a
	 *    captured webhook from being replayed later.
	 * 2. The HMAC-SHA256 of `<timestamp>.<canonical-json>` under your secret matches.
	 * 3. The comparison is constant-time, so a timing side channel cannot be used to guess
	 *    the signature byte by byte.
	 *
	 * The secret comes from the QBitFlow dashboard. Treat it like a password: keep it in
	 * your environment or secret manager, never in source control, and never send it
	 * anywhere.
	 *
	 * Returns normally when the webhook is authentic; throws otherwise.
	 *
	 * @param int $maxTimestampAgeSeconds Replay window; `0` or less means the default (300 s).
	 * @param int|null $nowSeconds Override the clock. Test-only.
	 * @param bool $skipTimestampCheck Disable the replay check. Leave this off in
	 *     production: without it a captured webhook can be replayed forever.
	 * @throws ValidationException When the webhook is not authentic
	 *
	 * ```php
	 * $headers = WebhookVerifier::extractHeaders($_SERVER);
	 * $body = file_get_contents('php://input');
	 *
	 * try {
	 *     WebhookVerifier::verify(
	 *         secret: (string) getenv('QBITFLOW_WEBHOOK_SECRET'),
	 *         timestamp: $headers['timestamp'],
	 *         signature: $headers['signature'],
	 *         payload: $body,
	 *     );
	 * } catch (ValidationException $e) {
	 *     http_response_code(400);
	 *     exit;
	 * }
	 *
	 * $event = json_decode($body, true);
	 * ```
	 */
	public static function verify(
		string $secret,
		string $timestamp,
		string $signature,
		string|array|object|null $payload,
		int $maxTimestampAgeSeconds = self::DEFAULT_MAX_TIMESTAMP_AGE_SECONDS,
		?int $nowSeconds = null,
		bool $skipTimestampCheck = false
	): void {
		if ($signature === '') {
			throw new ValidationException('webhook signature is required');
		}

		if (! $skipTimestampCheck) {
			self::verifyTimestamp(
				$timestamp,
				$maxTimestampAgeSeconds > 0 ? $maxTimestampAgeSeconds : self::DEFAULT_MAX_TIMESTAMP_AGE_SECONDS,
				$nowSeconds,
			);
		}

		$expected = self::computeSignature($secret, $timestamp, $payload);

		// hash_equals does not leak how many leading bytes matched.
		if (! hash_equals($expected, $signature)) {
			throw new ValidationException('webhook signature mismatch');
		}
	}

	/**
	 * Enforce the replay window.
	 *
	 * The timestamp is parsed like Go's `strconv.ParseInt`: ASCII digits with an optional
	 * sign, no surrounding whitespace, within the int64 range. The comparison is absolute
	 * (a webhook from a clock slightly ahead of ours is treated the same as one slightly
	 * behind) and cannot overflow.
	 */
	private static function verifyTimestamp(
		string $timestamp,
		int $maxAgeSeconds,
		?int $nowSeconds
	): void {
		if ($timestamp === '') {
			throw new ValidationException('webhook timestamp is required');
		}

		$sent = self::parseInt64($timestamp);

		if ($sent === null) {
			throw new ValidationException('webhook timestamp is not a unix-seconds integer');
		}

		$now = $nowSeconds ?? time();

		// Compared as floats: exact for any realistic clock, and immune to int overflow.
		$age = abs((float) $now - (float) $sent);

		if ($age > $maxAgeSeconds) {
			throw new ValidationException(sprintf(
				'webhook timestamp expired: age %ss exceeds the maximum of %ds',
				$age >= 1e15 ? sprintf('%.3e', $age) : (string) (int) $age,
				$maxAgeSeconds,
			));
		}
	}

	/**
	 * `strconv.ParseInt(s, 10, 64)`: null when not `[+-]?[0-9]+` or out of the int64 range.
	 */
	private static function parseInt64(string $value): ?int
	{
		if (preg_match('/^([+-]?)([0-9]+)$/', $value, $m) !== 1) {
			return null;
		}

		$digits = ltrim($m[2], '0');
		$digits = $digits === '' ? '0' : $digits;
		$limit = $m[1] === '-' ? '9223372036854775808' : '9223372036854775807';

		if (strlen($digits) > strlen($limit) || (strlen($digits) === strlen($limit) && strcmp($digits, $limit) > 0)) {
			return null;
		}

		if ($m[1] === '-' && $digits === $limit) {
			return PHP_INT_MIN;
		}

		return (int) ($m[1] === '-' ? '-' . $digits : $digits);
	}

	/**
	 * Pull the QBitFlow headers out of an incoming request.
	 *
	 * Accepts any array of headers, so it works across frameworks without the SDK having to
	 * know about them: `$_SERVER` (where PHP exposes headers as `HTTP_X_WEBHOOK_*`), the
	 * result of `getallheaders()`, a PSR-7 `$request->getHeaders()`, or a Laravel
	 * `$request->headers->all()`. Lookup is case-insensitive and tolerates the `HTTP_`
	 * prefix and underscore spelling, since HTTP header names are case-insensitive and PHP
	 * mangles them into `$_SERVER`.
	 *
	 * @param array<string,mixed> $headers
	 * @return array{signature:string,timestamp:string,webhookId:string,isTest:bool}
	 *     Missing headers come back as empty strings rather than throwing, so you can
	 *     report a clear error yourself.
	 */
	public static function extractHeaders(array $headers): array
	{
		$normalised = [];
		foreach ($headers as $key => $value) {
			$name = strtolower((string) $key);
			// $_SERVER exposes "X-Webhook-Id" as "HTTP_X_WEBHOOK_ID".
			$name = preg_replace('/^http_/', '', $name) ?? $name;
			$name = str_replace('_', '-', $name);

			$normalised[$name] = is_array($value)
				? (string) ($value[0] ?? '')
				: (string) $value;
		}

		$get = static fn (string $header): string
			=> $normalised[strtolower($header)] ?? '';

		$webhookId = $get(self::HEADER_WEBHOOK_ID);

		return [
			'signature' => $get(self::HEADER_SIGNATURE),
			'timestamp' => $get(self::HEADER_TIMESTAMP),
			'webhookId' => $webhookId,
			'isTest' => $webhookId === self::TEST_WEBHOOK_ID,
		];
	}
}
