<?php

declare(strict_types=1);

namespace QBitFlow\Webhooks;

use Closure;
use DateTimeInterface;
use JsonException;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamInterface;
use QBitFlow\Events\Event;
use QBitFlow\Exceptions\FieldError;
use QBitFlow\Exceptions\ServerException;
use QBitFlow\Exceptions\ValidationException;
use QBitFlow\Exceptions\WebhookSignatureException;
use QBitFlow\Support\Validator;
use stdClass;

/**
 * Verifies and parses webhook deliveries, without a client (a webhook receiver may not hold an
 * API key). `$client->webhooks->verify()`, `constructEvent()` and `parseEvent()` delegate here.
 * The lower level of {@see WebhookRouter}, which also dispatches the event and answers.
 *
 * ```php
 * $event = Webhook::constructEvent(
 *     file_get_contents('php://input'),          // the raw body, never re-serialized
 *     $_SERVER['HTTP_QBITFLOW_SIGNATURE'] ?? '',
 *     getenv('QBITFLOW_WEBHOOK_SECRET'),          // the endpoint's whsec_… secret
 * );
 * ```
 */
final class Webhook
{
	/** Carries `t=<unix seconds>,v1=<hex>` (two `v1` during a secret rotation). */
	public const SIGNATURE_HEADER = 'QBitFlow-Signature';

	/** Carries the event's id (`evt_…`): deduplicate on it. */
	public const EVENT_ID_HEADER = 'QBitFlow-Event-Id';

	/** Carries the event's type. */
	public const EVENT_TYPE_HEADER = 'QBitFlow-Event-Type';

	/** Carries the payload version (`v1` or `v2`). */
	public const VERSION_HEADER = 'QBitFlow-Webhook-Version';

	/** How far (seconds) a signature's timestamp may be from now, by default. */
	public const DEFAULT_TOLERANCE = 300;

	/** The largest body {@see Webhook::verifyRequest()} reads (the API's own limit): 1 MiB. */
	public const MAX_BODY_BYTES = 1048576;

	private const INT64_MAX = '9223372036854775807';

	private function __construct()
	{
	}

	/**
	 * Checks a delivery's signature: `$signatureHeader` is the `QBitFlow-Signature` header,
	 * `$secret` the endpoint's `whsec_…` secret, `$rawBody` the body exactly as received.
	 *
	 * It accepts the delivery when `t` is within `$tolerance` seconds of now (either direction;
	 * 0 or less = 300) and any `v1` equals `hex(HMAC-SHA256(secret, t + "." + rawBody))`,
	 * compared in constant time: during a secret rotation either secret's signature matches.
	 *
	 * @param (Closure(): (int|DateTimeInterface))|null $now The clock (unix seconds or a time); tests, replays.
	 *
	 * @throws WebhookSignatureException With `reason` saying why.
	 * @throws ValidationException       For an empty secret (a configuration error).
	 */
	public static function verify(string $rawBody, string $signatureHeader, string $secret, int $tolerance = self::DEFAULT_TOLERANCE, ?Closure $now = null): void
	{
		if ($secret === '') {
			throw Validator::fieldError('secret', "is required (the endpoint's whsec_… secret)");
		}
		if (trim($signatureHeader) === '') {
			throw new WebhookSignatureException(WebhookSignatureException::REASON_MISSING_HEADER, 'missing QBitFlow-Signature header');
		}

		$parsed = self::parseHeader($signatureHeader);
		if ($parsed === null) {
			throw new WebhookSignatureException(
				WebhookSignatureException::REASON_MALFORMED_HEADER,
				'malformed QBitFlow-Signature header: it needs one t=<unix seconds> and at least one v1=<signature>',
			);
		}
		[$timestamp, $signatures] = $parsed;

		if ($tolerance <= 0) {
			$tolerance = self::DEFAULT_TOLERANCE;
		}
		$clock = $now === null ? time() : $now();
		$nowSeconds = $clock instanceof DateTimeInterface ? $clock->getTimestamp() : (int) $clock;
		$age = $nowSeconds - (int) $timestamp; // int64 arithmetic; PHP widens to float instead of overflowing
		if ($age > $tolerance || $age < -$tolerance) {
			throw new WebhookSignatureException(
				WebhookSignatureException::REASON_TIMESTAMP_OUTSIDE_TOLERANCE,
				sprintf('webhook timestamp is outside the tolerance (%ds)', $tolerance),
			);
		}

		// The signed text is t exactly as received (leading zeros kept), a dot, the raw body.
		$expected = hash_hmac('sha256', $timestamp . '.' . $rawBody, $secret);

		$matched = false;
		foreach ($signatures as $signature) {
			// Every v1 is compared, in constant time: never stop at the first.
			if (hash_equals($expected, $signature)) {
				$matched = true;
			}
		}
		if (! $matched) {
			throw new WebhookSignatureException(WebhookSignatureException::REASON_NO_MATCHING_SIGNATURE, 'no webhook signature matches');
		}
	}

	/**
	 * Reads a PSR-7 request's body (at most 1 MiB) and verifies it with its `QBitFlow-Signature`
	 * header. Returns the raw body, for {@see Webhook::parseEvent()}; the body stream is rewound
	 * when it can be.
	 *
	 * @param (Closure(): (int|DateTimeInterface))|null $now
	 *
	 * @throws WebhookSignatureException
	 * @throws ValidationException For a body over 1 MiB, or an empty secret.
	 */
	public static function verifyRequest(RequestInterface $request, string $secret, int $tolerance = self::DEFAULT_TOLERANCE, ?Closure $now = null): string
	{
		$body = self::readBody($request->getBody());
		if ($body === null) {
			throw Validator::fieldError('body', 'must be at most 1 MiB');
		}

		self::verify($body, $request->getHeaderLine(self::SIGNATURE_HEADER), $secret, $tolerance, $now);

		return $body;
	}

	/**
	 * Parses a webhook body (or an event of the log) **without** verifying it: use it on a body
	 * already verified ({@see Webhook::verify()}, `$client->webhooks->verifyRemote()`).
	 *
	 * Returns the {@see Event} subclass of its type (an {@see \QBitFlow\Events\UnknownEvent} for a
	 * type this SDK does not know). A body that is not a JSON object, whose version is not `v2`,
	 * or whose values have the wrong JSON type, is a {@see ValidationException}.
	 *
	 * @throws ValidationException
	 */
	public static function parseEvent(string $rawBody): Event
	{
		$trimmed = trim($rawBody);
		if ($trimmed === '' || $trimmed[0] !== '{') {
			throw Validator::fieldError('body', 'must be a JSON object (a webhook event)');
		}
		try {
			$decoded = json_decode($trimmed, false, 512, JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING);
		} catch (JsonException $e) {
			throw new ValidationException('validation failed', previous: $e, fieldErrors: [new FieldError('body', 'body is not a valid webhook event')]);
		}
		if (! $decoded instanceof stdClass) {
			throw Validator::fieldError('body', 'must be a JSON object (a webhook event)');
		}

		/** @var array<string,mixed> $fields */
		$fields = get_object_vars($decoded);
		$version = $fields['version'] ?? null;
		if ($version !== null && ! is_string($version)) {
			throw Validator::fieldError('body', 'is not a valid webhook event ("version" must be a string)');
		}
		if ($version !== 'v2') {
			throw Validator::fieldError('version', 'must be v2: the endpoint is still on v1, move it to v2 in the dashboard');
		}

		try {
			return Event::fromArray($fields);
		} catch (ServerException $e) {
			// The caller's input, not a server response: a validation error.
			throw new ValidationException('validation failed', fieldErrors: [
				new FieldError('body', 'body is not a valid webhook event: ' . $e->getErrorMessage()),
			]);
		}
	}

	/**
	 * Verifies a delivery ({@see Webhook::verify()}) and parses it ({@see Webhook::parseEvent()}).
	 *
	 * @param (Closure(): (int|DateTimeInterface))|null $now
	 *
	 * @throws WebhookSignatureException
	 * @throws ValidationException
	 */
	public static function constructEvent(string $rawBody, string $signatureHeader, string $secret, int $tolerance = self::DEFAULT_TOLERANCE, ?Closure $now = null): Event
	{
		self::verify($rawBody, $signatureHeader, $secret, $tolerance, $now);

		return self::parseEvent($rawBody);
	}

	/**
	 * The `QBitFlow-Signature` header QBitFlow would send for `$rawBody`: `t=<timestamp>,v1=<hex>`
	 * with `hex(HMAC-SHA256(secret, t + "." + rawBody))`. For your tests: a body signed with it
	 * passes {@see Webhook::verify()} (and a {@see WebhookRouter}) with the same secret.
	 *
	 * ```php
	 * $header = Webhook::sign($body, 'whsec_test');
	 * $result = $router->handle($body, $header); // status 200
	 * ```
	 *
	 * @param int|null $timestamp Unix seconds (default: now).
	 *
	 * @throws ValidationException For an empty secret or a negative timestamp.
	 */
	public static function sign(string $rawBody, string $secret, ?int $timestamp = null): string
	{
		if ($secret === '') {
			throw Validator::fieldError('secret', "is required (the endpoint's whsec_… secret)");
		}
		$timestamp ??= time();
		if ($timestamp < 0) {
			throw Validator::fieldError('timestamp', 'must not be negative');
		}

		return 't=' . $timestamp . ',v1=' . hash_hmac('sha256', $timestamp . '.' . $rawBody, $secret);
	}

	/**
	 * Reads a body stream, at most 1 MiB; null when it is larger. The stream is rewound before
	 * and after when it can be.
	 *
	 * @internal
	 */
	public static function readBody(StreamInterface $stream): ?string
	{
		if ($stream->isSeekable()) {
			$stream->rewind();
		}
		$body = '';
		while (! $stream->eof() && strlen($body) <= self::MAX_BODY_BYTES) {
			$chunk = $stream->read(self::MAX_BODY_BYTES + 1 - strlen($body));
			if ($chunk === '') {
				break;
			}
			$body .= $chunk;
		}
		if ($stream->isSeekable()) {
			$stream->rewind();
		}

		return strlen($body) > self::MAX_BODY_BYTES ? null : $body;
	}

	/**
	 * Splits `t=…,v1=…[,v1=…]`: parts on `,`, each on its first `=`, spaces trimmed, other keys
	 * ignored. It needs exactly one `t` of ASCII digits fitting a signed 64-bit integer, and a `v1`.
	 *
	 * @return array{0: string, 1: list<string>}|null The `t` text and the signatures; null when malformed.
	 */
	private static function parseHeader(string $header): ?array
	{
		$timestamp = null;
		$signatures = [];
		foreach (explode(',', $header) as $part) {
			$eq = strpos($part, '=');
			if ($eq === false) {
				continue;
			}
			$key = trim(substr($part, 0, $eq));
			$value = trim(substr($part, $eq + 1));
			if ($key === 't') {
				if ($timestamp !== null || ! self::isInt64Digits($value)) {
					return null;
				}
				$timestamp = $value;
			} elseif ($key === 'v1') {
				$signatures[] = $value;
			}
		}

		return $timestamp !== null && $signatures !== [] ? [$timestamp, $signatures] : null;
	}

	/** ASCII digits whose value fits a signed 64-bit integer. */
	private static function isInt64Digits(string $value): bool
	{
		if (preg_match('/^[0-9]+$/D', $value) !== 1) {
			return false;
		}
		$significant = ltrim($value, '0');

		return strlen($significant) < 19 || (strlen($significant) === 19 && strcmp($significant, self::INT64_MAX) <= 0);
	}
}
