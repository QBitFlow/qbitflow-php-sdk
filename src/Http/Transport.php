<?php

declare(strict_types=1);

namespace QBitFlow\Http;

use DateTimeImmutable;
use DateTimeZone;
use JsonException;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use QBitFlow\Config;
use QBitFlow\Exceptions\ConflictException;
use QBitFlow\Exceptions\FieldError;
use QBitFlow\Exceptions\ForbiddenException;
use QBitFlow\Exceptions\NetworkException;
use QBitFlow\Exceptions\NotFoundException;
use QBitFlow\Exceptions\QBitFlowException;
use QBitFlow\Exceptions\RateLimitException;
use QBitFlow\Exceptions\ServerException;
use QBitFlow\Exceptions\UnauthorizedException;
use QBitFlow\Exceptions\ValidationException;

/**
 * Performs the HTTP work for every service: authentication, retries and error mapping.
 *
 * The transport speaks PSR-18, so any compliant HTTP client works. When none is supplied
 * one is discovered automatically (Guzzle is the recommended install).
 *
 * **Retries.** Only `GET` requests are retried, and only for failures that say nothing
 * about the request itself: any transport-level failure the PSR-18 client reports (a
 * refused connection, a timeout, a connection reset or a truncated response) or a `5xx`. A
 * `POST`, `PUT` or `DELETE` is sent exactly once — a create that timed out after the server
 * processed it would otherwise be replayed into a duplicate checkout session or a "customer
 * already exists" error. Three `GET` routes are actions rather than reads (force-cancel,
 * execute-billing and the test claim-funds trigger) and are sent once as well, via the
 * `$retry` flag. `4xx`, `429` and `3xx` responses are never retried. Backoff is
 * exponential: 1s, 2s, 4s.
 *
 * **Responses.** A `2xx` must carry a JSON body (an empty `204` excepted); an empty,
 * non-JSON or scalar body is a {@see ServerException}. Numbers are decoded with
 * `JSON_BIGINT_AS_STRING` so an integer beyond PHP's range is reported rather than
 * silently rounded. The optional `$map` callback hydrates the decoded body; a
 * response-shape failure it raises is re-thrown carrying the HTTP status.
 *
 * @internal Consumers interact with the services on {@see \QBitFlow\QBitFlow} instead.
 */
final class Transport
{
	/** Header naming the user an organization-level key acts for. */
	public const ON_BEHALF_OF = 'On-Behalf-Of';

	private readonly ClientInterface $httpClient;

	private readonly RequestFactoryInterface $requestFactory;

	private readonly StreamFactoryInterface $streamFactory;

	/** @var callable(float): void */
	private $sleeper;

	/**
	 * @param string                $apiKey         API key sent as `X-API-Key`.
	 * @param string                $baseUrl        API base URL, without a trailing slash.
	 * @param float                 $timeout        Request timeout in **seconds**. Applied only to
	 *                                              a client the SDK creates itself; configure it on
	 *                                              your own client when you inject one.
	 * @param int                   $maxRetries     Retry attempts for GET requests that hit a 5xx
	 *                                              or a network failure. `0` disables retries.
	 * @param array<string,string>  $headers        Extra headers merged into every request.
	 * @param callable(float): void|null $sleeper   Sleep implementation, overridable in tests.
	 */
	public function __construct(
		private readonly string $apiKey,
		private readonly string $baseUrl = Config::DEFAULT_BASE_URL,
		private readonly float $timeout = Config::DEFAULT_TIMEOUT,
		private readonly int $maxRetries = Config::DEFAULT_MAX_RETRIES,
		private readonly array $headers = [],
		?ClientInterface $httpClient = null,
		?RequestFactoryInterface $requestFactory = null,
		?StreamFactoryInterface $streamFactory = null,
		?callable $sleeper = null,
	) {
		if ($maxRetries < 0) {
			throw new ValidationException('maxRetries must be zero or positive');
		}

		$this->httpClient = $httpClient ?? HttpClientResolver::resolve($this->timeout);
		$this->requestFactory = $requestFactory ?? HttpClientResolver::requestFactory();
		$this->streamFactory = $streamFactory ?? HttpClientResolver::streamFactory();
		$this->sleeper = $sleeper ?? static function (float $seconds): void {
			usleep((int) round($seconds * 1_000_000));
		};
	}

	/**
	 * Derive a copy of this transport carrying one additional header.
	 *
	 * Used by `onBehalfOf()` to scope a service to a specific user without mutating
	 * the original client.
	 */
	public function withHeader(string $name, string $value): self
	{
		return $this->withHeaders([...$this->headers, $name => $value]);
	}

	/**
	 * Derive a copy of this transport without the named header.
	 *
	 * Used by `onBehalfOf(0)` to return a service to organization level.
	 */
	public function withoutHeader(string $name): self
	{
		$headers = $this->headers;
		unset($headers[$name]);

		return $this->withHeaders($headers);
	}

	/**
	 * @param array<string,string> $headers
	 */
	private function withHeaders(array $headers): self
	{
		return new self(
			$this->apiKey,
			$this->baseUrl,
			$this->timeout,
			$this->maxRetries,
			$headers,
			$this->httpClient,
			$this->requestFactory,
			$this->streamFactory,
			$this->sleeper,
		);
	}

	public function getApiKey(): string
	{
		return $this->apiKey;
	}

	public function getBaseUrl(): string
	{
		return $this->baseUrl;
	}

	/**
	 * @param array<string,mixed>   $params
	 * @param bool                  $retry  Whether a network failure or 5xx may be retried.
	 *                                      Pass false for GET routes that perform an action.
	 * @param callable(mixed): mixed|null $map Hydrates the decoded body.
	 *
	 * @return mixed The decoded body (an array, or null for a JSON `null`), or what `$map` returns.
	 */
	public function get(string $endpoint, array $params = [], bool $retry = true, ?callable $map = null): mixed
	{
		return $this->result($this->send('GET', $endpoint, null, $params, $retry), $map);
	}

	/**
	 * @param array<string,mixed>         $data
	 * @param callable(mixed): mixed|null $map
	 */
	public function post(string $endpoint, array $data = [], ?callable $map = null): mixed
	{
		return $this->result($this->send('POST', $endpoint, $this->encode($data)), $map);
	}

	/**
	 * POST a body that is already JSON-encoded, byte for byte.
	 *
	 * Used by webhook verification, whose payload must reach the API exactly as it was
	 * received (so `{}` stays an object and `-0` keeps its sign).
	 *
	 * @param callable(mixed): mixed|null $map
	 */
	public function postJson(string $endpoint, string $json, ?callable $map = null): mixed
	{
		return $this->result($this->send('POST', $endpoint, $json), $map);
	}

	/**
	 * @param array<string,mixed>         $data
	 * @param callable(mixed): mixed|null $map
	 */
	public function put(string $endpoint, array $data = [], ?callable $map = null): mixed
	{
		return $this->result($this->send('PUT', $endpoint, $this->encode($data)), $map);
	}

	/**
	 * @param callable(mixed): mixed|null $map
	 */
	public function delete(string $endpoint, ?callable $map = null): mixed
	{
		return $this->result($this->send('DELETE', $endpoint), $map);
	}

	/**
	 * Perform a request and return the response body untouched.
	 *
	 * Used for endpoints that answer with something other than JSON, such as the CSV
	 * flavour of the accounting export. Error responses are still parsed as JSON.
	 *
	 * @param array<string,mixed> $params
	 */
	public function raw(string $method, string $endpoint, array $params = []): string
	{
		return (string) $this->send($method, $endpoint, null, $params)->getBody();
	}

	/**
	 * Send a request, retrying idempotent ones on server and network failures.
	 *
	 * @param string|null         $body   JSON request body, already encoded.
	 * @param array<string,mixed> $params
	 */
	private function send(
		string $method,
		string $endpoint,
		?string $body = null,
		array $params = [],
		bool $retry = true,
	): ResponseInterface {
		$request = $this->buildRequest($method, $endpoint, $body, $params);

		// Only a GET can be replayed safely; everything else is sent exactly once.
		$attempts = $method === 'GET' && $retry ? $this->maxRetries : 0;

		for ($attempt = 0; ; $attempt++) {
			try {
				$response = $this->httpClient->sendRequest($request);
			} catch (ClientExceptionInterface $e) {
				// Every transport-level failure is retried on a GET. That includes PSR-18
				// RequestExceptionInterface: Guzzle reports a connection reset or a truncated
				// response that way, not only a malformed request — and the SDK builds its
				// own URLs, so a genuinely unsendable request cannot occur here.
				if ($attempt < $attempts) {
					($this->sleeper)($this->backoff($attempt));

					continue;
				}

				throw new NetworkException(
					sprintf('Network request failed: %s', $e->getMessage()),
					previous: $e,
				);
			}

			$status = $response->getStatusCode();

			if ($status >= 200 && $status < 300) {
				return $response;
			}

			if ($status >= 500 && $attempt < $attempts) {
				($this->sleeper)($this->backoff($attempt));

				continue;
			}

			throw $this->error($response, $status, (string) $response->getBody());
		}
	}

	/** Exponential backoff: 1s, 2s, 4s, ... */
	private function backoff(int $attempt): float
	{
		return Config::DEFAULT_RETRY_DELAY * (2 ** $attempt);
	}

	/**
	 * JSON-encode a request body.
	 *
	 * @param array<string,mixed> $data
	 *
	 * @throws ValidationException When the data cannot be encoded (NaN/INF, invalid UTF-8).
	 */
	private function encode(array $data): string
	{
		try {
			return json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
		} catch (JsonException $e) {
			throw new ValidationException(
				sprintf('Request body cannot be encoded as JSON: %s', $e->getMessage()),
				previous: $e,
			);
		}
	}

	/**
	 * @param string|null         $body
	 * @param array<string,mixed> $params
	 */
	private function buildRequest(string $method, string $endpoint, ?string $body, array $params): RequestInterface
	{
		$request = $this->requestFactory->createRequest($method, $this->url($endpoint, $params));

		$headers = [
			'X-API-Key' => $this->apiKey,
			'Accept' => 'application/json',
			'User-Agent' => 'qbitflow-php/' . \QBitFlow\QBitFlow::VERSION,
			...$this->headers,
		];

		foreach ($headers as $name => $value) {
			$request = $request->withHeader($name, $value);
		}

		if ($body !== null) {
			$request = $request
				->withHeader('Content-Type', 'application/json')
				->withBody($this->streamFactory->createStream($body));
		}

		return $request;
	}

	/**
	 * Build the absolute request URL.
	 *
	 * @param array<string,mixed> $params
	 */
	private function url(string $endpoint, array $params): string
	{
		if (! str_starts_with($endpoint, '/')) {
			$endpoint = '/' . $endpoint;
		}

		$url = $this->baseUrl . $endpoint;

		if ($params === []) {
			return $url;
		}

		// http_build_query renders booleans as 1/0; the API expects true/false.
		$normalized = [];

		foreach ($params as $key => $value) {
			if ($value === null) {
				continue;
			}

			$normalized[$key] = is_bool($value) ? ($value ? 'true' : 'false') : $value;
		}

		return $normalized === []
			? $url
			: $url . '?' . http_build_query($normalized, '', '&', PHP_QUERY_RFC3986);
	}

	/**
	 * Map a non-2xx response onto the matching exception type.
	 *
	 * | Status              | Exception                                          |
	 * |---------------------|----------------------------------------------------|
	 * | 400, 422            | {@see ValidationException}                         |
	 * | 401                 | {@see UnauthorizedException}                       |
	 * | 403                 | {@see ForbiddenException}                          |
	 * | 404                 | {@see NotFoundException}                           |
	 * | 409                 | {@see ConflictException}                           |
	 * | 429                 | {@see RateLimitException} (with `Retry-After`)     |
	 * | any other 4xx       | {@see QBitFlowException} (the base type)           |
	 * | 3xx, 5xx            | {@see ServerException}                             |
	 *
	 * A 3xx should never reach the SDK — the API does not redirect — so one means the base
	 * URL points somewhere else. It is reported immediately, without retries.
	 *
	 * @param string $raw Response body, already read so the stream is consumed only once.
	 */
	private function error(ResponseInterface $response, int $status, string $raw): QBitFlowException
	{
		$message = $this->errorMessage($raw, $response);
		$body = $this->decodeSafely($raw);
		$fields = FieldError::listFrom($body['errors'] ?? null);

		return match (true) {
			$status === 400, $status === 422 => new ValidationException($message, $status, $body, fields: $fields),
			$status === 401 => new UnauthorizedException($message, $status, $body, fields: $fields),
			$status === 403 => new ForbiddenException($message, $status, $body, fields: $fields),
			$status === 404 => new NotFoundException($message, $status, $body, fields: $fields),
			$status === 409 => new ConflictException($message, $status, $body, fields: $fields),
			$status === 429 => new RateLimitException(
				$message,
				$status,
				$body,
				retryAfter: self::retryAfter($response->getHeaderLine('Retry-After')),
				fields: $fields,
			),
			$status >= 400 && $status < 500 => new QBitFlowException($message, $status, $body, fields: $fields),
			default => new ServerException($message, $status, $body, fields: $fields),
		};
	}

	/**
	 * Pull a human-readable message out of an error response.
	 *
	 * The API uses two error envelopes: a field-level list for validation failures,
	 * `{"errors":[{"field":"Price","message":"Price is too short"}]}`, and a single
	 * message for everything else, `{"error":"resource not found"}`. Precedence:
	 * `error`, then the joined `errors[]`, then `message`, then a non-JSON body verbatim,
	 * then the HTTP status line.
	 */
	private function errorMessage(string $raw, ResponseInterface $response): string
	{
		$fallback = trim(sprintf('HTTP %d %s', $response->getStatusCode(), $response->getReasonPhrase()));

		if (trim($raw) === '') {
			return $fallback;
		}

		$data = $this->decodeSafely($raw);

		if ($data === null) {
			// Not JSON — the body itself is the best message available.
			return $raw;
		}

		if (isset($data['error']) && is_scalar($data['error'])) {
			return (string) $data['error'];
		}

		// Validation envelope: report every field, not just the first.
		$fields = FieldError::listFrom($data['errors'] ?? null);

		if ($fields !== []) {
			return implode('; ', array_map(static fn (FieldError $f): string => (string) $f, $fields));
		}

		// `message` is the *success* envelope's key and does not appear on error
		// responses, but honour it in case a gateway synthesises one.
		if (isset($data['message']) && is_scalar($data['message'])) {
			return (string) $data['message'];
		}

		return $fallback;
	}

	/**
	 * Decode a successful response and hydrate it.
	 *
	 * @param callable(mixed): mixed|null $map
	 */
	private function result(ResponseInterface $response, ?callable $map): mixed
	{
		$decoded = $this->decode($response);

		if ($map === null) {
			return $decoded;
		}

		try {
			return $map($decoded);
		} catch (ServerException $e) {
			if ($e->getStatusCode() !== null) {
				throw $e;
			}

			// A response-shape failure raised while hydrating: attach the HTTP status.
			throw new ServerException($e->getMessage(), $response->getStatusCode(), $decoded, $e);
		}
	}

	/**
	 * Decode a successful JSON response.
	 *
	 * @return array<array-key,mixed>|null An object or list; null for a JSON `null` (Go's nil slice).
	 *
	 * @throws ServerException When the body is empty (other than a 204), not JSON, or a scalar.
	 */
	private function decode(ResponseInterface $response): ?array
	{
		$status = $response->getStatusCode();
		$raw = (string) $response->getBody();

		if (trim($raw) === '') {
			if ($status === 204) {
				return [];
			}

			throw new ServerException(
				sprintf('Malformed API response: HTTP %d with an empty body where JSON was expected', $status),
				$status,
			);
		}

		try {
			$decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING);
		} catch (JsonException $e) {
			throw new ServerException(
				sprintf('Failed to parse JSON response: %s', $e->getMessage()),
				$status,
				['raw' => $raw],
				$e,
			);
		}

		if ($decoded !== null && ! is_array($decoded)) {
			throw new ServerException(
				sprintf('Malformed API response: expected a JSON object or list, got %s', get_debug_type($decoded)),
				$status,
				['raw' => $raw],
			);
		}

		return $decoded;
	}

	/**
	 * Seconds to wait from a `Retry-After` header: delta-seconds, or an HTTP-date turned
	 * into seconds from now (never negative). Null when absent or unparseable.
	 */
	private static function retryAfter(string $header): ?int
	{
		$header = trim($header);

		if ($header === '') {
			return null;
		}

		if (preg_match('/^\d+$/', $header) === 1) {
			return (int) $header;
		}

		// The three HTTP-date forms of RFC 9110 §5.6.7, all in GMT.
		$utc = new DateTimeZone('UTC');

		foreach (['!D, d M Y H:i:s \\G\\M\\T', '!l, d-M-y H:i:s \\G\\M\\T', '!D M j H:i:s Y'] as $format) {
			$date = DateTimeImmutable::createFromFormat($format, $header, $utc);

			if ($date !== false) {
				return max(0, $date->getTimestamp() - time());
			}
		}

		return null;
	}

	/**
	 * Decode a response body without throwing, for use while building errors.
	 *
	 * @param string $raw Response body that has already been read.
	 *
	 * @return array<array-key,mixed>|null
	 */
	private function decodeSafely(string $raw): ?array
	{
		$decoded = json_decode($raw, true);

		return is_array($decoded) ? $decoded : null;
	}
}
