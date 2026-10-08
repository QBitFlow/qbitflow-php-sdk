<?php

declare(strict_types=1);

namespace QBitFlow\Webhooks;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use QBitFlow\Enums\EventType;
use QBitFlow\Events\Event;
use QBitFlow\Events\UnknownEvent;
use QBitFlow\Exceptions\QBitFlowException;
use QBitFlow\Exceptions\ValidationException;
use QBitFlow\Exceptions\WebhookSignatureException;
use QBitFlow\Http\HttpClientResolver;
use QBitFlow\Support\Validator;
use Throwable;

/**
 * A webhook endpoint in a few lines: verifies a delivery's signature, parses the event, runs
 * the handlers registered for its type, and tells you what to answer.
 *
 * ```php
 * $router = (new WebhookRouter(getenv('QBITFLOW_WEBHOOK_SECRET')))
 *     ->on(EventType::PAYMENT_COMPLETED, function (PaymentCompleted $data, Event $event): void {
 *         fulfil($data->reference, $event->id); // deduplicate on $event->id
 *     })
 *     ->on(EventType::CHECKOUT_EXPIRED, fn ($data) => release($data->reference));
 *
 * $router->handleGlobals();                     // plain PHP: reads the request, sends the answer
 * $response = $router->handleRequest($request); // PSR-7 in, PSR-7 out (Slim, Mezzio…)
 * $handler = $router->psr15();                  // a PSR-15 RequestHandlerInterface (Symfony bridge, Mezzio…)
 * $result = $router->handle($rawBody, $header); // anything else: answer $result->status
 * ```
 *
 * What it answers ({@see WebhookResult}):
 * - a bad signature (also a stale timestamp) → 400, no handler runs;
 * - a body that is not a v2 event (not JSON, an endpoint still on v1…) → 400;
 * - handled, or ignored (no handler for the type, unknown types included) → 200;
 * - a handler throws → 500 (`internal error`) and QBitFlow retries the delivery: the handlers of that event not run
 *   yet are skipped. Make your handlers idempotent (deliveries are at least once): deduplicate
 *   on `$event->id`.
 *
 * Per delivery the handlers run in this order: those registered with {@see on()} for the
 * event's type (registration order), then {@see onUnknown()} for a type this SDK does not know,
 * then {@see onAny()}.
 */
final class WebhookRouter
{
	/** @var array<string, list<callable(mixed, Event): mixed>> */
	private array $handlers = [];

	/** @var list<callable(Event): mixed> */
	private array $unknownHandlers = [];

	/** @var list<callable(Event): mixed> */
	private array $anyHandlers = [];

	/** @var list<callable(Throwable, ?Event): mixed> */
	private array $errorHandlers = [];

	private ?ResponseFactoryInterface $responseFactory = null;

	private ?StreamFactoryInterface $streamFactory = null;

	private readonly int $tolerance;

	/**
	 * @param string $secret    The endpoint's `whsec_…` secret.
	 * @param int    $tolerance How far (seconds) the signature's timestamp may be from now (0 or less: 300).
	 *
	 * @throws ValidationException For an empty secret (a configuration error).
	 */
	public function __construct(
		#[\SensitiveParameter]
		private readonly string $secret,
		int $tolerance = Webhook::DEFAULT_TOLERANCE,
	) {
		if ($secret === '') {
			throw Validator::fieldError('secret', "is required (the endpoint's whsec_… secret)");
		}
		$this->tolerance = $tolerance > 0 ? $tolerance : Webhook::DEFAULT_TOLERANCE;
	}

	/**
	 * Runs `$handler($data, $event)` for each event of `$type`: `$data` is the event's typed data
	 * (a {@see \QBitFlow\Models\PaymentCompleted} for `payment.completed`…). Several handlers of
	 * one type run in registration order. Throw to answer 500 (QBitFlow retries).
	 *
	 * @param string                       $type    An {@see EventType} value.
	 * @param callable(mixed, Event): mixed $handler
	 *
	 * @throws ValidationException For a type this SDK does not know (use {@see onUnknown()}).
	 */
	public function on(string $type, callable $handler): self
	{
		if (! in_array($type, EventType::values(), true)) {
			throw Validator::fieldError('type', 'must be one of ' . implode(', ', EventType::values()) . ' (use onUnknown() for the others)');
		}
		$this->handlers[$type][] = $handler;

		return $this;
	}

	/**
	 * Runs `$handler($event)` for each event of a type this SDK does not know (an
	 * {@see UnknownEvent}, its `data` raw): added to the API after this release.
	 *
	 * @param callable(Event): mixed $handler
	 */
	public function onUnknown(callable $handler): self
	{
		$this->unknownHandlers[] = $handler;

		return $this;
	}

	/**
	 * Runs `$handler($event)` for every event (after the handlers of its type), e.g. to store
	 * or log them all.
	 *
	 * @param callable(Event): mixed $handler
	 */
	public function onAny(callable $handler): self
	{
		$this->anyHandlers[] = $handler;

		return $this;
	}

	/**
	 * Runs `$listener($event, $error)` (the same order in every QBitFlow SDK) for every result carrying an error: a 400 (bad signature,
	 * not a v2 event, unreadable body; `$event` is null then) or a 500 (a handler threw). Not for
	 * 405 and 413. Log there. An exception thrown by the listener is ignored.
	 *
	 * @param callable(?Event, Throwable): mixed $listener
	 */
	public function onError(callable $listener): self
	{
		$this->errorHandlers[] = $listener;

		return $this;
	}

	/**
	 * The PSR-17 factories {@see handleRequest()} and {@see psr15()} build responses with
	 * (default: the first of Guzzle, Nyholm, Laminas, Slim, php-http/discovery installed).
	 */
	public function withResponseFactory(ResponseFactoryInterface $responseFactory, ?StreamFactoryInterface $streamFactory = null): self
	{
		$this->responseFactory = $responseFactory;
		$this->streamFactory = $streamFactory ?? ($responseFactory instanceof StreamFactoryInterface ? $responseFactory : null);

		return $this;
	}

	/**
	 * Verifies, parses and dispatches one delivery: `$rawBody` exactly as received (never
	 * re-serialized), `$signatureHeader` the `QBitFlow-Signature` header. Never throws: answer
	 * `$result->status` (with `$result->responseBody()`).
	 */
	public function handle(string $rawBody, string $signatureHeader): WebhookResult
	{
		try {
			Webhook::verify($rawBody, $signatureHeader, $this->secret, $this->tolerance);
			$event = Webhook::parseEvent($rawBody);
		} catch (WebhookSignatureException|ValidationException $e) {
			return $this->failed(new WebhookResult(400, null, $e));
		}

		return $this->dispatch($event);
	}

	/**
	 * Runs the handlers for an event already verified and parsed (e.g. by your own middleware,
	 * or one of the event log): 200, or 500 when a handler throws.
	 */
	public function dispatch(Event $event): WebhookResult
	{
		$handlers = [];
		foreach ($this->handlers[$event->type] ?? [] as $handler) {
			$handlers[] = static fn () => $handler(self::dataOf($event), $event);
		}
		if ($event instanceof UnknownEvent) {
			foreach ($this->unknownHandlers as $handler) {
				$handlers[] = static fn () => $handler($event);
			}
		}
		foreach ($this->anyHandlers as $handler) {
			$handlers[] = static fn () => $handler($event);
		}

		foreach ($handlers as $run) {
			try {
				$run();
			} catch (Throwable $e) {
				return $this->failed(new WebhookResult(500, $event, $e));
			}
		}

		return new WebhookResult(200, $event);
	}

	/**
	 * PSR-7 in, PSR-7 out: answers 405 to anything but a POST, 413 to a body over 1 MiB (from
	 * `Content-Length` too), 400 to a body that cannot be read, else {@see handle()}'s status,
	 * with a JSON body. The header is read case-insensitively.
	 *
	 * @throws QBitFlowException When no PSR-17 response factory is installed nor given.
	 */
	public function handleRequest(RequestInterface $request): ResponseInterface
	{
		$result = $this->handleRaw(
			$request->getMethod(),
			$request->getHeaderLine('Content-Length'),
			static fn (): ?string => Webhook::readBody($request->getBody()),
			$request->getHeaderLine(Webhook::SIGNATURE_HEADER),
		);

		$this->responseFactory ??= HttpClientResolver::responseFactory();
		$this->streamFactory ??= $this->responseFactory instanceof StreamFactoryInterface
			? $this->responseFactory
			: HttpClientResolver::streamFactory();

		$response = $this->responseFactory->createResponse($result->status)
			->withHeader('Content-Type', 'application/json')
			->withBody($this->streamFactory->createStream($result->responseBody()));

		return $result->status === 405 ? $response->withHeader('Allow', 'POST') : $response;
	}

	/**
	 * This router as a PSR-15 `RequestHandlerInterface` (Symfony through its PSR-7 bridge, Slim,
	 * Mezzio…): its `handle()` is {@see handleRequest()}.
	 *
	 * @throws QBitFlowException When `psr/http-server-handler` is not installed.
	 */
	public function psr15(): Psr15WebhookHandler
	{
		if (! interface_exists(\Psr\Http\Server\RequestHandlerInterface::class)) {
			throw new QBitFlowException('The PSR-15 adapter needs psr/http-server-handler: run "composer require psr/http-server-handler".');
		}

		return new Psr15WebhookHandler($this);
	}

	/**
	 * Plain PHP: reads the request (`$_SERVER`, `php://input`), handles it like
	 * {@see handleRequest()}, and sends the answer (status, JSON body). Returns the result, e.g.
	 * to log `$result->error`.
	 *
	 * ```php
	 * // public/webhooks/qbitflow.php
	 * $router->handleGlobals();
	 * ```
	 */
	public function handleGlobals(): WebhookResult
	{
		$server = static fn (string $key): string => is_scalar($_SERVER[$key] ?? null) ? (string) $_SERVER[$key] : '';
		$result = $this->handleRaw($server('REQUEST_METHOD'), $server('CONTENT_LENGTH'), self::readInput(...), $server('HTTP_QBITFLOW_SIGNATURE'));

		if (! headers_sent()) {
			http_response_code($result->status);
			header('Content-Type: application/json');
			if ($result->status === 405) {
				header('Allow: POST');
			}
		}
		echo $result->responseBody();

		return $result;
	}

	/**
	 * What every adapter does: 405 unless a POST, 413 for a declared or read body over 1 MiB,
	 * 400 when the body cannot be read, else {@see handle()}.
	 *
	 * @param \Closure(): ?string $readBody The body, null when over 1 MiB; throws when unreadable.
	 */
	private function handleRaw(string $method, string $contentLength, \Closure $readBody, string $signatureHeader): WebhookResult
	{
		if (strtoupper($method) !== 'POST') {
			return new WebhookResult(405);
		}
		$length = trim($contentLength);
		if ($length !== '' && ctype_digit($length) && (strlen(ltrim($length, '0')) > 8 || (int) $length > Webhook::MAX_BODY_BYTES)) {
			return new WebhookResult(413); // early, from Content-Length
		}
		try {
			$body = $readBody();
		} catch (Throwable $e) {
			return $this->failed(WebhookResult::unreadableBody($e));
		}

		return $body === null ? new WebhookResult(413) : $this->handle($body, $signatureHeader);
	}

	/** Calls the {@see onError()} listeners, and returns the result. */
	private function failed(WebhookResult $result): WebhookResult
	{
		if ($result->error !== null) {
			foreach ($this->errorHandlers as $listener) {
				try {
					$listener($result->event, $result->error);
				} catch (Throwable) {
					// A logging failure must not change the answer.
				}
			}
		}

		return $result;
	}

	/** The event's typed data (raw for an unknown type). */
	private static function dataOf(Event $event): mixed
	{
		return property_exists($event, 'data') ? $event->data : $event->rawData;
	}

	/**
	 * `php://input`, at most 1 MiB; null when larger.
	 *
	 * @throws \RuntimeException When it cannot be read.
	 */
	private static function readInput(): ?string
	{
		$input = @fopen('php://input', 'rb');
		if ($input === false) {
			throw new \RuntimeException('php://input cannot be opened');
		}
		$body = stream_get_contents($input, Webhook::MAX_BODY_BYTES + 1);
		fclose($input);
		if ($body === false) {
			throw new \RuntimeException('php://input cannot be read');
		}

		return strlen($body) > Webhook::MAX_BODY_BYTES ? null : $body;
	}
}
