<?php

declare(strict_types=1);

namespace QBitFlow\Tests\Support;

use Closure;
use Nyholm\Psr7\Response;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;

/**
 * A PSR-18 client answering with a scripted handler (or a queue) and recording every request.
 */
final class MockHttpClient implements ClientInterface
{
	/** @var list<RequestInterface> */
	public array $requests = [];

	/** @var list<ResponseInterface|ClientExceptionInterface> */
	private array $queue = [];

	/**
	 * @param (Closure(RequestInterface, int): (ResponseInterface|ClientExceptionInterface))|null $handler
	 *        Answers the n-th request (0-based); the queue is used when null.
	 */
	public function __construct(private ?Closure $handler = null)
	{
	}

	/** @param array<string,string> $headers */
	public static function response(int $status, string $body = '', array $headers = []): ResponseInterface
	{
		return new Response($status, $headers + ['Content-Type' => 'application/json'], $body);
	}

	/**
	 * Answers every request with the same status and body.
	 *
	 * @param array<string,string> $headers
	 */
	public static function static(int $status, string $body, array $headers = []): self
	{
		return new self(static fn (): ResponseInterface => self::response($status, $body, $headers));
	}

	/**
	 * Answers the scripted `[status, body]` pairs in order, then repeats the last one.
	 *
	 * @param list<array{0: int, 1: string}> $replies
	 */
	public static function sequence(array $replies): self
	{
		return new self(static function (RequestInterface $r, int $n) use ($replies): ResponseInterface {
			[$status, $body] = $replies[min($n, count($replies) - 1)];

			return self::response($status, $body);
		});
	}

	/** @param array<string,string> $headers */
	public function push(string $body, int $status = 200, array $headers = []): self
	{
		$this->queue[] = self::response($status, $body, $headers);

		return $this;
	}

	public function pushFailure(string $message = 'Connection refused'): self
	{
		$this->queue[] = new MockNetworkException($message);

		return $this;
	}

	public function sendRequest(RequestInterface $request): ResponseInterface
	{
		$n = count($this->requests);
		$this->requests[] = $request;

		if ($this->handler !== null) {
			$answer = ($this->handler)($request, $n);
		} elseif ($this->queue !== []) {
			$answer = array_shift($this->queue);
		} else {
			throw new RuntimeException(sprintf('unexpected request: %s %s', $request->getMethod(), (string) $request->getUri()));
		}

		if ($answer instanceof ClientExceptionInterface) {
			throw $answer;
		}

		return $answer;
	}

	public function count(): int
	{
		return count($this->requests);
	}

	public function last(): RequestInterface
	{
		if ($this->requests === []) {
			throw new RuntimeException('no request was sent');
		}

		return $this->requests[array_key_last($this->requests)];
	}
}
