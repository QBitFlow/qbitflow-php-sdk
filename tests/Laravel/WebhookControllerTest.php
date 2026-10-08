<?php

declare(strict_types=1);

namespace QBitFlow\Tests\Laravel;

use Illuminate\Events\Dispatcher;
use Illuminate\Http\Request;
use Illuminate\Contracts\Debug\ExceptionHandler;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use QBitFlow\Enums\EventType;
use QBitFlow\Events\UnknownEvent;
use QBitFlow\Laravel\Events;
use QBitFlow\Laravel\Http\Controllers\WebhookController;
use QBitFlow\Laravel\Http\Middleware\VerifyQBitFlowWebhook;
use QBitFlow\Webhooks\Webhook;
use QBitFlow\Webhooks\WebhookRouter;
use RuntimeException;

final class WebhookControllerTest extends TestCase
{
	/** @var list<object> */
	private array $dispatched = [];

	private const SECRET = 'whsec_test_secret';

	/** @var list<\Throwable> */
	private array $reported = [];

	private function controller(?\Closure $listener = null): WebhookController
	{
		$this->dispatched = [];
		$this->reported = [];
		$dispatcher = new Dispatcher();
		$dispatcher->listen('*', function (string $name, array $payload): void {
			$this->dispatched[] = $payload[0];
		});
		if ($listener !== null) {
			$dispatcher->listen(Events\PaymentCompleted::class, $listener);
		}
		$exceptions = $this->createMock(ExceptionHandler::class);
		$exceptions->method('report')->willReturnCallback(function (\Throwable $e): void {
			$this->reported[] = $e;
		});

		return new WebhookController($dispatcher, new WebhookRouter(self::SECRET), $exceptions);
	}

	private static function verified(string $type, string $data = '{}'): Request
	{
		$request = Request::create('/webhooks/qbitflow', 'POST');
		$request->attributes->set(VerifyQBitFlowWebhook::EVENT_ATTRIBUTE, Webhook::parseEvent(
			'{"id":"evt_1","type":"' . $type . '","version":"v2","createdAt":"2026-10-01T12:00:00Z","test":false,"data":' . $data . '}',
		));

		return $request;
	}

	#[Test]
	public function it_dispatches_the_typed_event_then_webhook_received(): void
	{
		$expected = [
			EventType::PAYMENT_COMPLETED => Events\PaymentCompleted::class,
			EventType::SUBSCRIPTION_CREATED => Events\SubscriptionCreated::class,
			EventType::SUBSCRIPTION_BILLED => Events\SubscriptionBilled::class,
			EventType::SUBSCRIPTION_STATUS_CHANGED => Events\SubscriptionStatusChanged::class,
			EventType::SUBSCRIPTION_ACTION_REQUIRED_CHANGED => Events\SubscriptionActionRequiredChanged::class,
			EventType::SUBSCRIPTION_BILLING_FAILED => Events\SubscriptionBillingFailed::class,
			EventType::SUBSCRIPTION_UPCOMING_BILL => Events\SubscriptionUpcomingBill::class,
			EventType::REFUND_REQUESTED => Events\RefundRequested::class,
			EventType::REFUND_COMPLETED => Events\RefundCompleted::class,
			EventType::REFUND_DENIED => Events\RefundDenied::class,
			EventType::MEMBER_JOINED => Events\MemberJoined::class,
			EventType::MEMBER_REMOVED => Events\MemberRemoved::class,
			EventType::HELD_FUNDS_RELEASED => Events\HeldFundsReleased::class,
			EventType::CHECKOUT_EXPIRED => Events\CheckoutExpired::class,
			EventType::WEBHOOK_TEST => Events\WebhookTestReceived::class,
		];
		$this->assertCount(count(EventType::values()), $expected);

		foreach ($expected as $type => $class) {
			$response = $this->controller()(self::verified($type));
			$this->assertSame(200, $response->getStatusCode(), $type);
			$this->assertSame(['received' => true], $response->getData(true));
			$this->assertCount(2, $this->dispatched, $type);
			$this->assertInstanceOf($class, $this->dispatched[0], $type);
			$this->assertSame($this->dispatched[0]->event->data, $this->dispatched[0]->data);
			$this->assertInstanceOf(Events\WebhookReceived::class, $this->dispatched[1]);
			$this->assertSame($this->dispatched[0]->event, $this->dispatched[1]->event);
		}
	}

	#[Test]
	public function a_payment_carries_its_typed_data(): void
	{
		$this->controller()(self::verified(EventType::PAYMENT_COMPLETED, '{"uuid":"pay@1","reference":"order-1042","amount":10}'));
		$this->assertSame('order-1042', $this->dispatched[0]->data->reference);
		$this->assertSame(10.0, $this->dispatched[0]->data->amount);
	}

	#[Test]
	public function an_unknown_type_is_acknowledged_with_webhook_received_only(): void
	{
		$response = $this->controller()(self::verified('invoice.paid', '{"x":1}'));
		$this->assertSame(200, $response->getStatusCode());
		$this->assertCount(1, $this->dispatched);
		$this->assertInstanceOf(Events\WebhookReceived::class, $this->dispatched[0]);
		$this->assertInstanceOf(UnknownEvent::class, $this->dispatched[0]->event);
	}

	#[Test]
	public function without_the_middleware_it_verifies_the_delivery_itself(): void
	{
		$controller = $this->controller();
		$response = $controller(Request::create('/webhooks/qbitflow', 'POST', [], [], [], [], '{"type":"payment.completed"}'));
		$this->assertSame(400, $response->getStatusCode(), 'never acts on an unverified body');
		$this->assertSame(['error' => 'invalid signature'], $response->getData(true));
		$this->assertSame([], $this->dispatched);

		$response = $controller(WebhookMiddlewareTest::signedRequest(WebhookMiddlewareTest::body(), self::SECRET));
		$this->assertSame(200, $response->getStatusCode());
		$this->assertCount(2, $this->dispatched);
		$this->assertInstanceOf(Events\PaymentCompleted::class, $this->dispatched[0]);

		$big = Request::create('/webhooks/qbitflow', 'POST', [], [], [], [], str_repeat('a', Webhook::MAX_BODY_BYTES + 1));
		$this->assertSame(413, $controller($big)->getStatusCode());
	}

	#[Test]
	public function a_failing_listener_answers_500_and_is_reported(): void
	{
		$boom = new RuntimeException('database down');
		$controller = $this->controller(static function () use ($boom): void {
			throw $boom;
		});
		$response = $controller(self::verified(EventType::PAYMENT_COMPLETED));

		$this->assertSame(500, $response->getStatusCode());
		$this->assertSame(['error' => 'internal error'], $response->getData(true), 'never the exception message');
		$this->assertSame([$boom], $this->reported);
		$this->assertNotContains(true, array_map(static fn (object $e): bool => $e instanceof Events\WebhookReceived, $this->dispatched), 'later handlers skipped');
	}
}
