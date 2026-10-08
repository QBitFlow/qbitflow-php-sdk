<?php

declare(strict_types=1);

namespace QBitFlow\Tests\Unit;

use GuzzleHttp\Psr7\HttpFactory;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Server\RequestHandlerInterface;
use QBitFlow\Enums\EventType;
use QBitFlow\Events\Event;
use QBitFlow\Events\PaymentCompletedEvent;
use QBitFlow\Events\UnknownEvent;
use QBitFlow\Exceptions\QBitFlowException;
use QBitFlow\Exceptions\ValidationException;
use QBitFlow\Exceptions\WebhookSignatureException;
use QBitFlow\Models\PaymentCompleted;
use QBitFlow\Tests\Support\TestCase;
use QBitFlow\Webhooks\Psr15WebhookHandler;
use QBitFlow\Webhooks\Webhook;
use QBitFlow\Webhooks\WebhookRouter;
use RuntimeException;

/**
 * The webhook router (helpers H1) and the test signature (H2).
 */
final class WebhookRouterTest extends TestCase
{
	private const SECRET = 'whsec_test_secret';

	/** The docs' example body (the signature vector's). */
	private const DOCS_BODY = '{"createdAt":"2026-10-01T12:00:00Z","data":{},"id":"evt_3f1c2d4e-5a6b-5c7d-8e9f-0a1b2c3d4e5f","test":false,"type":"webhook.test","version":"v2"}';

	/** @var list<string> What ran, in order. */
	private array $ran = [];

	private static function body(string $type = EventType::PAYMENT_COMPLETED, string $version = 'v2', string $data = '{"uuid":"pay@1","reference":"order-1"}'): string
	{
		return '{"id":"evt_1","type":"' . $type . '","version":"' . $version . '","createdAt":"2026-10-01T12:00:00Z","test":true,"data":' . $data . '}';
	}

	/** A router recording what runs. */
	private function router(): WebhookRouter
	{
		$this->ran = [];

		return (new WebhookRouter(self::SECRET))
			->on(EventType::PAYMENT_COMPLETED, function (PaymentCompleted $data, Event $event): void {
				$this->ran[] = 'payment:' . $data->reference . ':' . $event->id;
			})
			->on(EventType::CHECKOUT_EXPIRED, function (): void {
				$this->ran[] = 'expired';
			})
			->onUnknown(function (Event $event): void {
				$this->ran[] = 'unknown:' . $event->type;
			})
			->onAny(function (Event $event): void {
				$this->ran[] = 'any1';
			})
			->onAny(function (Event $event): void {
				$this->ran[] = 'any2';
			});
	}

	// --- H1: handle() ---------------------------------------------------------------------

	#[Test]
	public function a_valid_delivery_runs_its_typed_handler_then_on_any(): void
	{
		$body = self::body();
		$result = $this->router()->handle($body, Webhook::sign($body, self::SECRET));

		$this->assertSame(200, $result->status);
		$this->assertTrue($result->ok());
		$this->assertNull($result->error);
		$this->assertInstanceOf(PaymentCompletedEvent::class, $result->event);
		$this->assertSame(['payment:order-1:evt_1', 'any1', 'any2'], $this->ran);
		$this->assertSame('{"received":true}', $result->responseBody());
	}

	#[Test]
	public function a_bad_signature_is_400_and_runs_nothing(): void
	{
		$body = self::body();
		$cases = [
			'other secret' => Webhook::sign($body, 'whsec_other'),
			'stale' => Webhook::sign($body, self::SECRET, time() - 3600),
			'future' => Webhook::sign($body, self::SECRET, time() + 3600),
			'missing header' => '',
			'malformed header' => 'garbage',
		];
		foreach ($cases as $name => $header) {
			$result = $this->router()->handle($body, $header);
			$this->assertSame(400, $result->status, $name);
			$this->assertInstanceOf(WebhookSignatureException::class, $result->error, $name);
			$this->assertNull($result->event, $name);
			$this->assertSame([], $this->ran, $name);
			$this->assertSame('{"error":"invalid signature"}', $result->responseBody(), $name);
		}

		// A tampered body.
		$result = $this->router()->handle(str_replace('order-1', 'order-2', $body), Webhook::sign($body, self::SECRET));
		$this->assertSame(400, $result->status);
	}

	#[Test]
	public function the_tolerance_is_configurable(): void
	{
		$body = self::body();
		$header = Webhook::sign($body, self::SECRET, time() - 600);
		$this->assertSame(400, (new WebhookRouter(self::SECRET))->handle($body, $header)->status);
		$this->assertSame(200, (new WebhookRouter(self::SECRET, 3600))->handle($body, $header)->status);
		$this->assertSame(400, (new WebhookRouter(self::SECRET, 0))->handle($body, $header)->status, '0 = the default 300 s');
	}

	#[Test]
	public function a_signed_body_that_is_not_a_v2_event_is_400(): void
	{
		$cases = [
			'not JSON' => 'not json',
			'a JSON list' => '[1,2]',
			'v1' => self::body(version: 'v1'),
			'bad shape' => self::body(data: '"a string"'),
		];
		foreach ($cases as $name => $body) {
			$result = $this->router()->handle($body, Webhook::sign($body, self::SECRET));
			$this->assertSame(400, $result->status, $name);
			$this->assertInstanceOf(ValidationException::class, $result->error, $name);
			$this->assertSame([], $this->ran, $name);
			$this->assertSame('{"error":"invalid event"}', $result->responseBody(), $name);
		}
	}

	#[Test]
	public function an_unknown_type_is_200_with_on_unknown_and_on_any(): void
	{
		$body = self::body('invoice.paid', data: '{"x":1}');
		$result = $this->router()->handle($body, Webhook::sign($body, self::SECRET));

		$this->assertSame(200, $result->status);
		$this->assertInstanceOf(UnknownEvent::class, $result->event);
		$this->assertSame(['unknown:invoice.paid', 'any1', 'any2'], $this->ran);
	}

	#[Test]
	public function a_known_type_without_a_handler_is_200(): void
	{
		$body = self::body(EventType::WEBHOOK_TEST, data: '{}');
		$result = $this->router()->handle($body, Webhook::sign($body, self::SECRET));
		$this->assertSame(200, $result->status);
		$this->assertSame(['any1', 'any2'], $this->ran, 'onUnknown is for the types the SDK does not know');

		$bare = new WebhookRouter(self::SECRET);
		$this->assertSame(200, $bare->handle($body, Webhook::sign($body, self::SECRET))->status, 'no handler at all');
	}

	#[Test]
	public function a_throwing_handler_is_500_and_skips_the_later_handlers(): void
	{
		$boom = new RuntimeException('database down: password=hunter2');
		$errors = [];
		$router = $this->router()
			->on(EventType::PAYMENT_COMPLETED, static function () use ($boom): void {
				throw $boom;
			})
			->on(EventType::PAYMENT_COMPLETED, function (): void {
				$this->ran[] = 'second payment handler';
			})
			->onError(static function (?Event $event, \Throwable $e) use (&$errors): void {
				$errors[] = [$e, $event->id];

				throw new RuntimeException('the logger fails too');
			});

		$body = self::body();
		$result = $router->handle($body, Webhook::sign($body, self::SECRET));

		$this->assertSame(500, $result->status);
		$this->assertSame($boom, $result->error);
		$this->assertInstanceOf(PaymentCompletedEvent::class, $result->event);
		$this->assertSame(['payment:order-1:evt_1'], $this->ran, 'the handlers after the failing one did not run');
		$this->assertSame([[$boom, 'evt_1']], $errors);
		$this->assertSame('{"error":"internal error"}', $result->responseBody(), 'never the exception message');
	}

	#[Test]
	public function a_failing_on_any_handler_is_500_too(): void
	{
		$router = (new WebhookRouter(self::SECRET))->onAny(static function (): void {
			throw new \Error('typo');
		});
		$body = self::body();
		$this->assertSame(500, $router->handle($body, Webhook::sign($body, self::SECRET))->status);
	}

	#[Test]
	public function handlers_of_one_type_run_in_registration_order(): void
	{
		$order = [];
		$router = (new WebhookRouter(self::SECRET))
			->onAny(static function () use (&$order): void {
				$order[] = 'any';
			})
			->on(EventType::PAYMENT_COMPLETED, static function () use (&$order): void {
				$order[] = 'a';
			})
			->on(EventType::PAYMENT_COMPLETED, static function () use (&$order): void {
				$order[] = 'b';
			});
		$body = self::body();
		$router->handle($body, Webhook::sign($body, self::SECRET));
		$this->assertSame(['a', 'b', 'any'], $order, 'the type handlers, then onAny, whatever the registration order');
	}

	#[Test]
	public function dispatch_runs_the_handlers_of_a_verified_event(): void
	{
		$result = $this->router()->dispatch(Webhook::parseEvent(self::body()));
		$this->assertSame(200, $result->status);
		$this->assertSame(['payment:order-1:evt_1', 'any1', 'any2'], $this->ran);
	}

	#[Test]
	public function configuration_errors_are_validation_errors(): void
	{
		$this->assertSame(['secret'], $this->failingFields(static fn () => new WebhookRouter('')));
		$this->assertSame(['type'], $this->failingFields(static fn () => (new WebhookRouter(self::SECRET))->on('payment.complete', static fn () => null)));
	}

	#[Test]
	public function the_client_builds_one(): void
	{
		$router = $this->client()->webhooks->router(self::SECRET);
		$this->assertInstanceOf(WebhookRouter::class, $router);
		$body = self::body();
		$this->assertSame(200, $router->handle($body, Webhook::sign($body, self::SECRET))->status);
		$this->assertSame(0, $this->http->count(), 'all local');
		$this->assertFalse(method_exists($this->client()->webhooks, 'sign'), 'sign is standalone only: Webhook::sign()');
	}

	#[Test]
	public function on_error_sees_every_result_carrying_an_error(): void
	{
		$seen = [];
		$router = (new WebhookRouter(self::SECRET))
			->on(EventType::PAYMENT_COMPLETED, static function (): void {
				throw new RuntimeException('boom');
			})
			->onError(static function (?Event $event, \Throwable $e) use (&$seen): void {
				$seen[] = [$e::class, $event?->id];
			});
		$body = self::body();
		$router->handle($body, 'garbage');
		$router->handle('not json', Webhook::sign('not json', self::SECRET));
		$router->handle($body, Webhook::sign($body, self::SECRET));
		$router->handleRequest(self::request('GET', ''));
		$router->handleRequest(self::request('POST', str_repeat(' ', Webhook::MAX_BODY_BYTES + 1)));
		$this->assertSame([
			[WebhookSignatureException::class, null],
			[ValidationException::class, null],
			[RuntimeException::class, 'evt_1'],
		], $seen, 'the 400s (event null) and the 500, never the 405 nor the 413');
	}

	// --- H1: adapters -----------------------------------------------------------------------

	/** @param array<string,string> $headers */
	private static function request(string $method, string $body, array $headers = []): ServerRequest
	{
		return new ServerRequest($method, 'https://shop.example.com/webhooks/qbitflow', $headers, $body);
	}

	#[Test]
	public function the_psr7_adapter_answers_json(): void
	{
		$body = self::body();
		$response = $this->router()->handleRequest(self::request('POST', $body, ['QBitFlow-Signature' => Webhook::sign($body, self::SECRET)]));
		$this->assertSame(200, $response->getStatusCode());
		$this->assertSame('application/json', $response->getHeaderLine('Content-Type'));
		$this->assertSame('{"received":true}', (string) $response->getBody());
		$this->assertSame(['payment:order-1:evt_1', 'any1', 'any2'], $this->ran);

		$response = $this->router()->handleRequest(self::request('POST', $body, ['QBitFlow-Signature' => 't=1,v1=00']));
		$this->assertSame(400, $response->getStatusCode());
		$this->assertSame('{"error":"invalid signature"}', (string) $response->getBody());
	}

	#[Test]
	public function the_psr7_adapter_reads_the_header_case_insensitively(): void
	{
		$body = self::body();
		foreach (['qbitflow-signature', 'QBITFLOW-SIGNATURE', 'Qbitflow-Signature'] as $name) {
			$response = $this->router()->handleRequest(self::request('POST', $body, [$name => Webhook::sign($body, self::SECRET)]));
			$this->assertSame(200, $response->getStatusCode(), $name);
		}
	}

	#[Test]
	public function the_psr7_adapter_accepts_only_post(): void
	{
		foreach (['GET', 'PUT', 'HEAD'] as $method) {
			$response = $this->router()->handleRequest(self::request($method, ''));
			$this->assertSame(405, $response->getStatusCode(), $method);
			$this->assertSame('POST', $response->getHeaderLine('Allow'));
			$this->assertSame('{"error":"method not allowed"}', (string) $response->getBody());
			$this->assertSame([], $this->ran);
		}
	}

	#[Test]
	public function the_psr7_adapter_refuses_a_body_over_1_mib(): void
	{
		$body = str_repeat(' ', Webhook::MAX_BODY_BYTES + 1);
		$response = $this->router()->handleRequest(self::request('POST', $body, ['QBitFlow-Signature' => Webhook::sign($body, self::SECRET)]));
		$this->assertSame(413, $response->getStatusCode());
		$this->assertSame('{"error":"body too large"}', (string) $response->getBody());

		// Exactly 1 MiB is read (and then refused as not JSON, not as too large).
		$body = str_repeat(' ', Webhook::MAX_BODY_BYTES);
		$response = $this->router()->handleRequest(self::request('POST', $body, ['QBitFlow-Signature' => Webhook::sign($body, self::SECRET)]));
		$this->assertSame(400, $response->getStatusCode());
	}

	#[Test]
	public function the_psr7_adapter_refuses_a_declared_length_over_1_mib_early(): void
	{
		$request = self::request('POST', '{}', ['Content-Length' => (string) (Webhook::MAX_BODY_BYTES + 1)]);
		$response = $this->router()->handleRequest($request);
		$this->assertSame(413, $response->getStatusCode());
		$this->assertSame('{"error":"body too large"}', (string) $response->getBody());
		$this->assertSame(413, $this->router()->handleRequest(self::request('POST', '{}', ['Content-Length' => '99999999999999999999999']))->getStatusCode());
	}

	#[Test]
	public function an_unreadable_body_is_400(): void
	{
		$stream = $this->createMock(\Psr\Http\Message\StreamInterface::class);
		$stream->method('isSeekable')->willReturn(false);
		$stream->method('eof')->willReturn(false);
		$stream->method('read')->willThrowException(new RuntimeException('connection reset'));
		$response = $this->router()->handleRequest(self::request('POST', '')->withBody($stream));
		$this->assertSame(400, $response->getStatusCode());
		$this->assertSame('{"error":"cannot read the body"}', (string) $response->getBody());
		$this->assertSame([], $this->ran);
	}

	#[Test]
	public function the_response_factory_can_be_given(): void
	{
		$body = self::body();
		$router = $this->router()->withResponseFactory(new HttpFactory());
		$response = $router->handleRequest(self::request('POST', $body, ['QBitFlow-Signature' => Webhook::sign($body, self::SECRET)]));
		$this->assertInstanceOf(\GuzzleHttp\Psr7\Response::class, $response);
		$this->assertSame('{"received":true}', (string) $response->getBody());

		$factory = new Psr17Factory();
		$response = $this->router()->withResponseFactory($factory, $factory)->handleRequest(self::request('GET', ''));
		$this->assertInstanceOf(\Nyholm\Psr7\Response::class, $response);
	}

	#[Test]
	public function the_psr15_adapter_delegates_to_the_router(): void
	{
		if (! interface_exists(RequestHandlerInterface::class)) {
			$this->expectException(QBitFlowException::class);
			$this->expectExceptionMessage('psr/http-server-handler');
			$this->router()->psr15();

			return;
		}

		$router = $this->router();
		$handler = $router->psr15();
		$this->assertInstanceOf(RequestHandlerInterface::class, $handler);
		$this->assertInstanceOf(Psr15WebhookHandler::class, $handler);
		$this->assertSame($router, $handler->router());
		$body = self::body();
		$response = $handler->handle(self::request('POST', $body, ['qbitflow-signature' => Webhook::sign($body, self::SECRET)]));
		$this->assertSame(200, $response->getStatusCode());
		$this->assertSame(405, $handler->handle(self::request('GET', ''))->getStatusCode());
	}

	#[Test]
	public function the_globals_adapter_answers_from_the_superglobals(): void
	{
		$server = $_SERVER;
		try {
			$_SERVER['REQUEST_METHOD'] = 'GET';
			ob_start();
			$result = $this->router()->handleGlobals();
			$this->assertSame('{"error":"method not allowed"}', ob_get_clean());
			$this->assertSame(405, $result->status);

			// php://input is empty on the CLI: a POST without a signature is refused.
			$_SERVER['REQUEST_METHOD'] = 'POST';
			$_SERVER['CONTENT_LENGTH'] = (string) (Webhook::MAX_BODY_BYTES + 1);
			ob_start();
			$result = $this->router()->handleGlobals();
			$this->assertSame('{"error":"body too large"}', ob_get_clean());
			$this->assertSame(413, $result->status);

			$_SERVER['REQUEST_METHOD'] = 'post';
			unset($_SERVER['HTTP_QBITFLOW_SIGNATURE'], $_SERVER['CONTENT_LENGTH']);
			ob_start();
			$result = $this->router()->handleGlobals();
			$this->assertSame('{"error":"invalid signature"}', ob_get_clean());
			$this->assertSame(400, $result->status);
			$this->assertInstanceOf(WebhookSignatureException::class, $result->error);
			$this->assertSame(WebhookSignatureException::REASON_MISSING_HEADER, $result->error->reason);
			$this->assertSame([], $this->ran);
		} finally {
			$_SERVER = $server;
		}
	}

	// --- H2: sign() ---------------------------------------------------------------------------

	#[Test]
	public function sign_matches_the_docs_vector(): void
	{
		$this->assertSame(
			't=1790856000,v1=4a158046f55556e922bdec376a917c3ac338ba575b495f825427541a60bd2f4d',
			Webhook::sign(self::DOCS_BODY, 'whsec_new_secret', 1790856000),
		);
	}

	#[Test]
	public function a_signed_body_verifies(): void
	{
		$header = Webhook::sign(self::DOCS_BODY, 'whsec_new_secret');
		$this->assertMatchesRegularExpression('/^t=\d+,v1=[0-9a-f]{64}$/', $header);
		Webhook::verify(self::DOCS_BODY, $header, 'whsec_new_secret');
		$this->assertSame(EventType::WEBHOOK_TEST, Webhook::constructEvent(self::DOCS_BODY, $header, 'whsec_new_secret')->type);

		$this->assertSame(['secret'], $this->failingFields(static fn () => Webhook::sign('{}', '')));
		$this->assertSame(['timestamp'], $this->failingFields(static fn () => Webhook::sign('{}', 'whsec_x', -1)));
	}
}
