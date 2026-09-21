<?php

declare(strict_types=1);

namespace QBitFlow\Tests\Support;

use Nyholm\Psr7\Response;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;

/**
 * A PSR-18 client that replays queued responses and records what it was asked to send.
 */
final class MockHttpClient implements ClientInterface
{
	/** @var list<ResponseInterface|ClientExceptionInterface> */
	private array $queue = [];

	/** @var list<RequestInterface> */
	public array $requests = [];

	/**
	 * Queue a JSON response.
	 *
	 * @param array<array-key,mixed>|string $body
	 * @param array<string,string>          $headers
	 */
	public function push(array|string $body = [], int $status = 200, array $headers = []): self
	{
		$encoded = is_string($body) ? $body : json_encode($body, JSON_THROW_ON_ERROR);

		$this->queue[] = new Response($status, ['Content-Type' => 'application/json'] + $headers, $encoded);

		return $this;
	}

	/** Queue a raw (non-JSON) response, such as a CSV export. */
	public function pushRaw(string $body, int $status = 200, string $contentType = 'text/csv'): self
	{
		$this->queue[] = new Response($status, ['Content-Type' => $contentType], $body);

		return $this;
	}

	/** Queue a transport-level failure, as if the request never landed. */
	public function pushFailure(string $message = 'Connection refused'): self
	{
		$this->queue[] = new MockNetworkException($message);

		return $this;
	}

	public function sendRequest(RequestInterface $request): ResponseInterface
	{
		$this->requests[] = $request;

		if ($this->queue === []) {
			throw new RuntimeException(sprintf(
				'MockHttpClient received an unexpected request: %s %s',
				$request->getMethod(),
				(string) $request->getUri(),
			));
		}

		$next = array_shift($this->queue);

		if ($next instanceof ClientExceptionInterface) {
			throw $next;
		}

		return $next;
	}

	/** The last request the client was asked to send. */
	public function lastRequest(): RequestInterface
	{
		if ($this->requests === []) {
			throw new RuntimeException('No request was sent.');
		}

		return $this->requests[array_key_last($this->requests)];
	}

	/** Path and query string of the last request, e.g. `/product/id/1?limit=5`. */
	public function lastPath(): string
	{
		$uri = $this->lastRequest()->getUri();
		$query = $uri->getQuery();

		return $uri->getPath() . ($query === '' ? '' : '?' . $query);
	}

	public function lastMethod(): string
	{
		return $this->lastRequest()->getMethod();
	}

	/**
	 * Decoded JSON body of the last request.
	 *
	 * @return array<array-key,mixed>
	 */
	public function lastBody(): array
	{
		$decoded = json_decode((string) $this->lastRequest()->getBody(), true);

		return is_array($decoded) ? $decoded : [];
	}

	/** Number of requests sent so far. */
	public function requestCount(): int
	{
		return count($this->requests);
	}
}
