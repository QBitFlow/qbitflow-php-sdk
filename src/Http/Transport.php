<?php

declare(strict_types=1);

namespace QBitFlow\Http;

use Closure;
use DateTimeImmutable;
use DateTimeZone;
use JsonException;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use QBitFlow\Exceptions\ApiException;
use QBitFlow\Exceptions\AuthenticationException;
use QBitFlow\Exceptions\BadRequestException;
use QBitFlow\Exceptions\ConflictException;
use QBitFlow\Exceptions\FieldError;
use QBitFlow\Exceptions\GoneException;
use QBitFlow\Exceptions\IdempotencyException;
use QBitFlow\Exceptions\NetworkException;
use QBitFlow\Exceptions\NotFoundException;
use QBitFlow\Exceptions\PermissionDeniedException;
use QBitFlow\Exceptions\QBitFlowException;
use QBitFlow\Exceptions\RateLimitException;
use QBitFlow\Exceptions\ServerException;
use QBitFlow\Exceptions\ValidationException;
use QBitFlow\QBitFlow;
use QBitFlow\RequestOptions;
use QBitFlow\Support\Query;
use QBitFlow\Support\Validator;
use stdClass;
use Throwable;

/**
 * The HTTP layer every client of one configuration shares: headers, body encoding, the retry
 * policy (behaviour §3) and the mapping of error responses onto the typed exceptions (§4).
 *
 * - **Retries:** every GET and the 7 idempotent creates are retried on a network error, a 5xx,
 *   a 429, and (creates only) a 409 `idempotency_key_in_use`; attempt n (0-based) waits
 *   `1 s · 2^n`, a 429 at least its `Retry-After` (a wait above 60 s is not made: the
 *   {@see RateLimitException} is thrown at once). Every other write is sent once.
 * - **Idempotency-Key:** a fresh UUID v4 per call of a create, reused on its every retry
 *   (`RequestOptions::$idempotencyKey` replaces it); never sent on other methods.
 * - **Redirects** are never followed (the client the SDK builds refuses them): a 3xx is a
 *   {@see ServerException}.
 *
 * @internal Build a {@see QBitFlow} client instead. Public for the SDK's own tests, which inject
 *           `$sleep`, `$now` and `$newKey`.
 */
final class Transport
{
	/** The first back-off, in seconds; attempt n (0-based) waits `RETRY_BASE_DELAY · 2^n`. */
	public const RETRY_BASE_DELAY = 1;

	/** The longest wait (seconds) before retrying a 429: a longer `Retry-After` throws at once. */
	public const MAX_RETRY_WAIT = 60;

	/** Bounds a `Retry-After` value (about a year: far above what the SDK waits anyway). */
	private const MAX_RETRY_AFTER = 365 * 24 * 3600;

	private const CODE_VALIDATION_FAILED = 'validation_failed';

	private const CODE_IDEMPOTENCY_KEY_IN_USE = 'idempotency_key_in_use';

	private const CODE_IDEMPOTENCY_KEY_REUSED = 'idempotency_key_reused';

	private readonly ClientInterface $httpClient;

	private readonly RequestFactoryInterface $requestFactory;

	private readonly StreamFactoryInterface $streamFactory;

	/** @var Closure(float): void */
	private readonly Closure $sleep;

	/** @var Closure(): DateTimeImmutable */
	private readonly Closure $now;

	/** @var Closure(): string */
	private readonly Closure $newKey;

	/**
	 * @param string                       $apiKey     Sent as `X-API-Key` (already validated).
	 * @param string                       $baseUrl    The API root, without a trailing slash.
	 * @param float                        $timeout    Seconds per attempt, applied to the HTTP client the SDK builds.
	 * @param int                          $maxRetries Retries of a retryable call; 0 disables them.
	 * @param (Closure(float): void)|null  $sleep      Waits between attempts (seconds).
	 * @param (Closure(): DateTimeImmutable)|null $now The clock (`Retry-After` HTTP-dates).
	 * @param (Closure(): string)|null     $newKey     Generates an `Idempotency-Key`.
	 */
	public function __construct(
		private readonly string $apiKey,
		private readonly string $baseUrl,
		private readonly float $timeout,
		private readonly int $maxRetries,
		?ClientInterface $httpClient = null,
		?RequestFactoryInterface $requestFactory = null,
		?StreamFactoryInterface $streamFactory = null,
		?Closure $sleep = null,
		?Closure $now = null,
		?Closure $newKey = null,
	) {
		$this->httpClient = $httpClient ?? HttpClientResolver::resolve($timeout);
		$this->requestFactory = $requestFactory ?? HttpClientResolver::requestFactory();
		$this->streamFactory = $streamFactory ?? HttpClientResolver::streamFactory();
		$this->sleep = $sleep ?? static function (float $seconds): void {
			usleep((int) round($seconds * 1_000_000));
		};
		$this->now = $now ?? static fn (): DateTimeImmutable => new DateTimeImmutable('now', new DateTimeZone('UTC'));
		$this->newKey = $newKey ?? self::uuidV4(...);
	}

	public function apiKey(): string
	{
		return $this->apiKey;
	}

	public function baseUrl(): string
	{
		return $this->baseUrl;
	}

	public function timeout(): float
	{
		return $this->timeout;
	}

	public function maxRetries(): int
	{
		return $this->maxRetries;
	}

	/** Waits `$seconds` with the transport's (injectable) sleep: the polling helpers use it. */
	public function sleep(float $seconds): void
	{
		if ($seconds > 0) {
			($this->sleep)($seconds);
		}
	}

	/**
	 * Performs one API call: applies the request options, builds the headers, encodes the body
	 * and runs the retry policy. Returns any 2xx response, else throws the typed error.
	 *
	 * @param string                $path       The route below the base URL, segments already escaped.
	 * @param array<string,string>  $query      Sent as the query string (sorted).
	 * @param array<string,mixed>|stdClass|null $body Encoded as JSON (null: no body, no Content-Type).
	 * @param bool                  $idempotent One of the 7 creates: sends an Idempotency-Key, retried like a read.
	 * @param string|null           $onBehalfOf The client's default On-Behalf-Of (already validated).
	 * @param string|null           $accept     Overrides `Accept: application/json`.
	 *
	 * @throws QBitFlowException
	 */
	public function send(
		string $method,
		string $path,
		array $query = [],
		array|stdClass|null $body = null,
		bool $idempotent = false,
		?string $onBehalfOf = null,
		?RequestOptions $options = null,
		?string $accept = null,
	): RawResponse {
		if ($options?->onBehalfOf !== null) {
			$onBehalfOf = self::checkOnBehalfOf($options->onBehalfOf);
		}
		$requestId = $options?->requestId;
		if ($requestId === '') {
			$requestId = null; // none sent
		}
		if ($requestId !== null && ! Validator::isRequestId($requestId)) {
			throw Validator::fieldError('X-Request-Id', 'must be 1 to 128 characters among A-Z a-z 0-9 - _ . :');
		}

		$headers = [
			'X-API-Key' => $this->apiKey,
			'User-Agent' => 'qbitflow-php/' . QBitFlow::VERSION,
			'Accept' => $accept ?? 'application/json',
		];
		if ($onBehalfOf !== null && $onBehalfOf !== '') {
			$headers['On-Behalf-Of'] = $onBehalfOf;
		}
		if ($requestId !== null) {
			$headers['X-Request-Id'] = $requestId;
		}
		if ($idempotent) {
			$key = $options?->idempotencyKey;
			if ($key === null || $key === '') {
				$key = ($this->newKey)(); // one key per call, reused by every retry
			} elseif (! Validator::isIdempotencyKey($key)) {
				throw Validator::fieldError('Idempotency-Key', 'must be 1 to 255 printable ASCII characters without spaces');
			}
			$headers['Idempotency-Key'] = $key;
		}

		$payload = null;
		if ($body !== null) {
			$payload = self::encodeBody($body);
			$headers['Content-Type'] = 'application/json';
		}

		$url = $this->baseUrl . $path;
		if ($query !== []) {
			$url .= '?' . Query::encode($query);
		}

		$maxRetries = $method === 'GET' || $idempotent ? $this->maxRetries : 0;

		for ($attempt = 0; ; $attempt++) {
			try {
				$response = $this->roundTrip($method, $url, $headers, $payload);
				if ($response->status >= 200 && $response->status < 300) {
					return $response;
				}
				$error = $this->errorFromResponse($response);
			} catch (NetworkException $e) {
				$error = $e;
			}

			if ($attempt >= $maxRetries || ! self::shouldRetry($error, $idempotent)) {
				throw $error;
			}
			$delay = self::retryDelay($error, $attempt);
			if ($delay === null) {
				throw $error;
			}
			($this->sleep)((float) $delay);
		}
	}

	/**
	 * Maps a non-2xx response onto the SDK's typed error (behaviour §4).
	 */
	public function errorFromResponse(RawResponse $response): ApiException
	{
		[$message, $code, $details, $requestId, $fields] = self::parseErrorBody($response);
		$status = $response->status;
		$args = [$message, $status, $code, $details, $requestId, $fields, $response->body];

		return match (true) {
			$status === 400 && $code === self::CODE_VALIDATION_FAILED => new ValidationException(...$args),
			$status === 400 => new BadRequestException(...$args),
			$status === 401 => new AuthenticationException(...$args),
			$status === 403 => new PermissionDeniedException(...$args),
			$status === 404 => new NotFoundException(...$args),
			$status === 409 => new ConflictException(...$args),
			$status === 410 => new GoneException(...$args),
			$status === 422 && $code === self::CODE_IDEMPOTENCY_KEY_REUSED => new IdempotencyException(...$args),
			$status === 429 => new RateLimitException(
				...$args,
				retryAfter: $this->retryAfter($response->header('Retry-After'), $details),
				limit: self::detailInt($details, 'limit'),
				periodSeconds: self::detailInt($details, 'periodSeconds'),
			),
			$status >= 500, $status < 200, $status >= 300 && $status < 400 => new ServerException(...$args),
			default => new ApiException(...$args),
		};
	}

	/**
	 * Decodes a 2xx JSON answer (objects as `stdClass`, lists as arrays) and hydrates it. An
	 * empty or non-JSON body, or a value of the wrong JSON type, is a {@see ServerException}
	 * carrying the HTTP status.
	 *
	 * @template T
	 *
	 * @param callable(mixed): T $hydrate
	 *
	 * @return T
	 */
	public static function decode(RawResponse $response, callable $hydrate): mixed
	{
		if (trim($response->body) === '') {
			throw self::serverError('empty response body where JSON was expected', $response);
		}
		try {
			$decoded = json_decode($response->body, false, 512, JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING);
		} catch (JsonException $e) {
			throw self::serverError('unexpected response body', $response, $e);
		}
		try {
			return $hydrate($decoded);
		} catch (ServerException $e) {
			if ($e->status !== 0) {
				throw $e;
			}

			throw self::serverError('unexpected response body', $response, $e);
		}
	}

	/** Whether a failed attempt may be retried (behaviour §3). */
	public static function shouldRetry(QBitFlowException $error, bool $idempotent): bool
	{
		if ($error instanceof ConflictException) {
			return $idempotent && $error->apiCode === self::CODE_IDEMPOTENCY_KEY_IN_USE;
		}

		return $error->isRetryable();
	}

	/**
	 * The wait (seconds) before the retry that follows `$attempt` (0-based): `1 s · 2^attempt`,
	 * and for a 429 at least its Retry-After. Null when a 429's wait exceeds 60 s.
	 */
	public static function retryDelay(QBitFlowException $error, int $attempt): ?int
	{
		$delay = self::RETRY_BASE_DELAY * (2 ** min($attempt, 30));
		if ($error instanceof RateLimitException) {
			$delay = max($delay, $error->retryAfter);
			if ($delay > self::MAX_RETRY_WAIT) {
				return null;
			}
		}

		return $delay;
	}

	/**
	 * Checks an On-Behalf-Of value: `''` (none) or a member's userUuid (a UUID, not the nil UUID).
	 *
	 * @throws ValidationException
	 */
	public static function checkOnBehalfOf(string $userUuid): string
	{
		if ($userUuid !== '' && ! Validator::isMemberUuid($userUuid)) {
			throw Validator::fieldError('onBehalfOf', "must be a member's userUuid (a UUID, not the nil UUID)");
		}

		return $userUuid;
	}

	/**
	 * Encodes a request body. A value JSON cannot represent (NaN, ±INF, invalid UTF-8) is the
	 * caller's: a {@see ValidationException}, and nothing is sent.
	 *
	 * @param array<array-key,mixed>|stdClass $body
	 */
	public static function encodeBody(array|stdClass $body): string
	{
		$field = self::findInvalidUtf8($body, '', 0);
		if ($field !== null) {
			throw Validator::fieldError($field === '' ? 'body' : $field, 'must be valid UTF-8');
		}
		if ($body === []) {
			return '{}';
		}
		try {
			return json_encode($body, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
		} catch (JsonException $e) {
			throw new ValidationException('request body cannot be encoded as JSON', previous: $e);
		}
	}

	/** A random (version 4) UUID: the SDK's default Idempotency-Key. */
	public static function uuidV4(): string
	{
		$bytes = random_bytes(16);
		$bytes[6] = chr((ord($bytes[6]) & 0x0F) | 0x40); // version 4
		$bytes[8] = chr((ord($bytes[8]) & 0x3F) | 0x80); // RFC 4122 variant
		$hex = bin2hex($bytes);

		return sprintf('%s-%s-%s-%s-%s', substr($hex, 0, 8), substr($hex, 8, 4), substr($hex, 12, 4), substr($hex, 16, 4), substr($hex, 20));
	}

	/**
	 * Escapes one path segment as Go's `url.PathEscape` does: a `/` inside a reference is sent
	 * as `%2F`, never as a separator; `@ : + = & $` stay as they are; a `.` or `..` segment is
	 * escaped too.
	 */
	public static function escapeSegment(string $segment): string
	{
		$escaped = strtr(rawurlencode($segment), ['%40' => '@', '%3A' => ':', '%2B' => '+', '%3D' => '=', '%26' => '&', '%24' => '$']);
		if (trim($escaped, '.') === '') {
			$escaped = str_replace('.', '%2E', $escaped);
		}

		return $escaped;
	}

	/**
	 * Performs one HTTP attempt.
	 *
	 * @param array<string,string> $headers
	 *
	 * @throws NetworkException When no response was received.
	 */
	private function roundTrip(string $method, string $url, array $headers, ?string $payload): RawResponse
	{
		$request = $this->requestFactory->createRequest($method, $url);
		foreach ($headers as $name => $value) {
			$request = $request->withHeader($name, $value);
		}
		if ($payload !== null) {
			$request = $request->withBody($this->streamFactory->createStream($payload));
		}

		try {
			$response = $this->httpClient->sendRequest($request);
		} catch (ClientExceptionInterface $e) {
			$timedOut = preg_match('/timed? ?out|cURL error 28/i', $e->getMessage()) === 1;

			throw new NetworkException(
				$timedOut ? sprintf('request timed out after %ss', self::formatSeconds($this->timeout)) : 'request failed',
				previous: $e,
			);
		}

		try {
			$body = (string) $response->getBody();
		} catch (Throwable $e) {
			throw new NetworkException('failed to read the response', previous: $e);
		}

		$lines = [];
		foreach (array_keys($response->getHeaders()) as $name) {
			$lines[strtolower((string) $name)] = $response->getHeaderLine((string) $name);
		}

		return new RawResponse($response->getStatusCode(), $lines, $body);
	}

	/**
	 * Reads the API's error envelope `{error, code, details, requestId}`: each key on its own,
	 * so a malformed one never hides the others; field errors come from `details.errors` only.
	 *
	 * @return array{0: string, 1: string, 2: array<string,mixed>, 3: string, 4: list<FieldError>}
	 */
	private static function parseErrorBody(RawResponse $response): array
	{
		$message = '';
		$code = '';
		$details = [];
		$requestId = '';
		$fields = [];

		$body = json_decode($response->body, true, 512, JSON_BIGINT_AS_STRING);
		if (is_array($body) && ($body === [] || ! array_is_list($body))) {
			$message = is_string($body['error'] ?? null) ? $body['error'] : '';
			$code = is_string($body['code'] ?? null) ? $body['code'] : '';
			$requestId = is_string($body['requestId'] ?? null) ? $body['requestId'] : '';
			if (is_array($body['details'] ?? null) && ($body['details'] === [] || ! array_is_list($body['details']))) {
				/** @var array<string,mixed> $details */
				$details = $body['details'];
			}
			$fields = self::fieldErrors($details);
		}

		if ($message === '') {
			$message = HttpStatus::message($response->status);
		}
		if ($requestId === '') {
			$requestId = $response->header('X-Request-Id');
		}

		return [$message, $code, $details, $requestId, $fields];
	}

	/**
	 * `details.errors` (`[{field, message}]`), malformed entries skipped.
	 *
	 * @param array<string,mixed> $details
	 *
	 * @return list<FieldError>
	 */
	private static function fieldErrors(array $details): array
	{
		$list = $details['errors'] ?? null;
		if (! is_array($list) || ! array_is_list($list)) {
			return [];
		}

		$out = [];
		foreach ($list as $entry) {
			if (! is_array($entry) || ($entry !== [] && array_is_list($entry))) {
				continue;
			}
			$field = is_string($entry['field'] ?? null) ? $entry['field'] : '';
			$message = is_string($entry['message'] ?? null) ? $entry['message'] : '';
			if ($field === '' && $message === '') {
				continue;
			}
			$out[] = new FieldError($field, $message);
		}

		return $out;
	}

	/**
	 * Seconds from the `Retry-After` header (delta-seconds or an HTTP-date, rounded up), else
	 * `details.retryAfterSeconds`; 0 when neither is usable.
	 *
	 * @param array<string,mixed> $details
	 */
	private function retryAfter(string $header, array $details): int
	{
		$header = trim($header);
		if ($header !== '') {
			if (preg_match('/^[+-]?\d+$/D', $header) === 1) {
				$seconds = (int) $header;
				if ($seconds >= 0) {
					return min($seconds, self::MAX_RETRY_AFTER);
				}
			} else {
				$when = self::parseHttpDate($header);
				if ($when !== null) {
					$now = ($this->now)();
					$wait = (float) $when->format('U.u') - (float) $now->format('U.u');

					return $wait <= 0 ? 0 : (int) min(ceil($wait), self::MAX_RETRY_AFTER);
				}
			}
		}

		$seconds = self::detailInt($details, 'retryAfterSeconds');

		return $seconds > 0 ? min($seconds, self::MAX_RETRY_AFTER) : 0;
	}

	/** The three HTTP-date forms of RFC 9110 §5.6.7, in GMT. */
	private static function parseHttpDate(string $value): ?DateTimeImmutable
	{
		$utc = new DateTimeZone('UTC');
		foreach (['!D, d M Y H:i:s \G\M\T', '!l, d-M-y H:i:s \G\M\T', '!D M j H:i:s Y'] as $format) {
			$date = DateTimeImmutable::createFromFormat($format, $value, $utc);
			if ($date !== false) {
				return $date;
			}
		}

		return null;
	}

	/**
	 * An integer from `details` (a JSON number, or a digit string); 0 when absent.
	 *
	 * @param array<string,mixed> $details
	 */
	private static function detailInt(array $details, string $key): int
	{
		$value = $details[$key] ?? null;
		if ((is_int($value) || is_float($value)) && $value >= 0 && $value < 2 ** 31) {
			return (int) $value;
		}
		if (is_string($value) && preg_match('/^[+-]?\d{1,18}$/D', $value) === 1) {
			return (int) $value;
		}

		return 0;
	}

	private static function serverError(string $message, RawResponse $response, ?Throwable $previous = null): ServerException
	{
		return new ServerException($message, $response->status, requestId: $response->header('X-Request-Id'), rawBody: $response->body, previous: $previous);
	}

	/**
	 * The wire path of the first string that is not valid UTF-8 (`''` for the body itself), or null.
	 *
	 * @param array<array-key,mixed>|stdClass|mixed $value
	 */
	private static function findInvalidUtf8(mixed $value, string $path, int $depth): ?string
	{
		if ($depth > 32) {
			return null;
		}
		if (is_string($value)) {
			return Validator::isUtf8($value) ? null : $path;
		}
		if ($value instanceof stdClass) {
			$value = get_object_vars($value);
		}
		if (! is_array($value)) {
			return null;
		}
		$isList = array_is_list($value);
		foreach ($value as $key => $item) {
			if (is_string($key) && ! Validator::isUtf8($key)) {
				return $path;
			}
			$sub = $isList ? sprintf('%s[%d]', $path, $key) : ($path === '' ? (string) $key : $path . '.' . $key);
			$found = self::findInvalidUtf8($item, $sub, $depth + 1);
			if ($found !== null) {
				return $found;
			}
		}

		return null;
	}

	private static function formatSeconds(float $seconds): string
	{
		return $seconds == floor($seconds) ? (string) (int) $seconds : rtrim(rtrim(sprintf('%.3f', $seconds), '0'), '.');
	}
}
