<?php

declare(strict_types=1);

namespace QBitFlow\Http;

use JsonException;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use QBitFlow\Config;
use QBitFlow\Exceptions\ForbiddenException;
use QBitFlow\Exceptions\NetworkException;
use QBitFlow\Exceptions\NotFoundException;
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
 * @internal Consumers interact with the services on {@see \QBitFlow\QBitFlow} instead.
 */
final class Transport
{
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
	 * @param int                   $maxRetries     Retry attempts for 5xx and network failures.
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
		return new self(
			$this->apiKey,
			$this->baseUrl,
			$this->timeout,
			$this->maxRetries,
			[...$this->headers, $name => $value],
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
	 * @param array<string,mixed> $params
	 *
	 * @return array<array-key,mixed>
	 */
	public function get(string $endpoint, array $params = []): array
	{
		return $this->decode($this->send('GET', $endpoint, null, $params));
	}

	/**
	 * @param array<string,mixed> $data
	 *
	 * @return array<array-key,mixed>
	 */
	public function post(string $endpoint, array $data = []): array
	{
		return $this->decode($this->send('POST', $endpoint, $data));
	}

	/**
	 * @param array<string,mixed> $data
	 *
	 * @return array<array-key,mixed>
	 */
	public function put(string $endpoint, array $data = []): array
	{
		return $this->decode($this->send('PUT', $endpoint, $data));
	}

	/**
	 * @return array<array-key,mixed>
	 */
	public function delete(string $endpoint): array
	{
		return $this->decode($this->send('DELETE', $endpoint));
	}

	/**
	 * Perform a request and return the response body untouched.
	 *
	 * Used for endpoints that answer with something other than JSON, such as the CSV
	 * flavour of the accounting export.
	 *
	 * @param array<string,mixed> $params
	 */
	public function raw(string $method, string $endpoint, array $params = []): string
	{
		return (string) $this->send($method, $endpoint, null, $params)->getBody();
	}

	/**
	 * Send a request, retrying server and network failures.
	 *
	 * @param array<string,mixed>|null $data
	 * @param array<string,mixed>      $params
	 */
	private function send(string $method, string $endpoint, ?array $data = null, array $params = []): ResponseInterface
	{
		$request = $this->buildRequest($method, $endpoint, $data, $params);
		$lastError = null;

		for ($attempt = 0; $attempt <= $this->maxRetries; $attempt++) {
			try {
				$response = $this->httpClient->sendRequest($request);
			} catch (ClientExceptionInterface $e) {
				// The request never reached the API — worth retrying.
				$lastError = $e;

				if ($attempt < $this->maxRetries) {
					($this->sleeper)(Config::DEFAULT_RETRY_DELAY * ($attempt + 1));

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

			// Client errors are the caller's to fix; retrying would only repeat them.
			if ($status >= 400 && $status < 500) {
				throw $this->clientError($response, $status, (string) $response->getBody());
			}

			if ($attempt < $this->maxRetries) {
				($this->sleeper)(Config::DEFAULT_RETRY_DELAY * ($attempt + 1));

				continue;
			}

			$body = (string) $response->getBody();

			throw new ServerException(
				$this->errorMessage($body),
				$status,
				$this->decodeSafely($body),
			);
		}

		throw new NetworkException(sprintf(
			'Request failed after %d retries: %s',
			$this->maxRetries,
			$lastError?->getMessage() ?? 'unknown error',
		), previous: $lastError);
	}

	/**
	 * @param array<string,mixed>|null $data
	 * @param array<string,mixed>      $params
	 */
	private function buildRequest(string $method, string $endpoint, ?array $data, array $params): \Psr\Http\Message\RequestInterface
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

		if ($data !== null) {
			$request = $request
				->withHeader('Content-Type', 'application/json')
				->withBody($this->streamFactory->createStream(
					json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
				));
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
	 * Map a 4xx response onto the matching exception type.
	 *
	 * @param string $raw Response body, already read so the stream is consumed only once.
	 */
	private function clientError(ResponseInterface $response, int $status, string $raw): \QBitFlow\Exceptions\QBitFlowException
	{
		$message = $this->errorMessage($raw);
		$body = $this->decodeSafely($raw);

		return match ($status) {
			401 => new UnauthorizedException($message, $status, $body),
			403 => new ForbiddenException($message, $status, $body),
			404 => new NotFoundException($message, $status, $body),
			429 => new RateLimitException(
				$message,
				$status,
				$body,
				retryAfter: is_numeric($retryAfter = $response->getHeaderLine('Retry-After'))
					? (int) $retryAfter
					: null,
			),
			default => new ValidationException($message, $status, $body),
		};
	}

	/**
	 * Pull a human-readable message out of an error response.
	 *
	 * The API is not perfectly consistent about the shape of error bodies, so the known
	 * variants are tried in turn before falling back to a generic message.
	 */
	private function errorMessage(string $raw): string
	{
		if (trim($raw) === '') {
			return 'An error occurred';
		}

		$data = $this->decodeSafely($raw);

		if ($data === null) {
			// Not JSON — the body itself is the best message available.
			return $raw;
		}

		if (isset($data['error']) && is_scalar($data['error'])) {
			return (string) $data['error'];
		}

		if (isset($data['errors']) && is_array($data['errors']) && $data['errors'] !== []) {
			$first = reset($data['errors']);

			if (is_array($first) && isset($first['message']) && is_scalar($first['message'])) {
				return (string) $first['message'];
			}

			if (is_scalar($first)) {
				return (string) $first;
			}
		}

		if (isset($data['message']) && is_scalar($data['message'])) {
			return (string) $data['message'];
		}

		return 'An error occurred';
	}

	/**
	 * Decode a successful JSON response.
	 *
	 * @return array<array-key,mixed>
	 */
	private function decode(ResponseInterface $response): array
	{
		$raw = (string) $response->getBody();

		if (trim($raw) === '') {
			return [];
		}

		try {
			$decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
		} catch (JsonException $e) {
			throw new ServerException(
				sprintf('Failed to parse JSON response: %s', $e->getMessage()),
				$response->getStatusCode(),
				['raw' => $raw],
				$e,
			);
		}

		return is_array($decoded) ? $decoded : ['data' => $decoded];
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
