<?php

declare(strict_types=1);

namespace QBitFlow\Tests\Laravel;

use Illuminate\Events\Dispatcher;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use QBitFlow\Enums\SubscriptionStatus;
use QBitFlow\Enums\TransactionStatusValue;
use QBitFlow\Enums\TransactionType;
use QBitFlow\Laravel\Events\SubscriptionBilled;
use QBitFlow\Laravel\Events\SubscriptionStatusChanged;
use QBitFlow\Laravel\Events\TransactionWebhookReceived;
use QBitFlow\Laravel\Http\Controllers\WebhookController;
use QBitFlow\Support\Enums;

final class WebhookControllerTest extends TestCase
{
	private Dispatcher $events;

	/** @var list<object> */
	private array $dispatched = [];

	protected function setUp(): void
	{
		parent::setUp();

		$this->dispatched = [];
		$this->events = new Dispatcher();

		foreach ([
			TransactionWebhookReceived::class,
			SubscriptionStatusChanged::class,
			SubscriptionBilled::class,
		] as $event) {
			$this->events->listen($event, function (object $e): void {
				$this->dispatched[] = $e;
			});
		}
	}

	/**
	 * @param array<string,mixed> $payload
	 */
	private function request(array $payload): Request
	{
		return Request::create('/webhooks/qbitflow', 'POST', [], [], [], [], json_encode($payload, JSON_THROW_ON_ERROR));
	}

	private function controller(): WebhookController
	{
		return new WebhookController($this->events);
	}

	/** @return array<string,mixed> */
	private static function billingData(): array
	{
		return [
			'uuid' => 'sub-hist@h1',
			'subscriptionUUID' => 'sub@01a0',
			'from' => '0xa',
			'to' => '0xb',
			'name' => 'Premium',
			'description' => 'Monthly',
			'amount' => 29.99,
			'currencyId' => 1,
			'transactionHash' => '0xhash',
			'customerUUID' => 'c1',
			'test' => false,
			'createdAt' => '2026-03-01T12:00:00Z',
		];
	}

	#[Test]
	public function it_dispatches_a_transaction_event_for_a_completed_payment(): void
	{
		$response = $this->controller()->transaction($this->request([
			'uuid' => 's1',
			'txType' => 'payment',
			'managementPageLink' => 'https://qbitflow.app/manage/s1',
			'status' => ['status' => 'completed', 'txHash' => '0xabc'],
			'session' => ['uuid' => 's1', 'txType' => 'payment', 'reference' => 'order-1234', 'price' => 29.99],
		]));

		$this->assertSame(200, $response->getStatusCode());
		$this->assertCount(1, $this->dispatched);

		$event = $this->dispatched[0];
		$this->assertInstanceOf(TransactionWebhookReceived::class, $event);
		$this->assertSame('order-1234', $event->reference());
		$this->assertFalse($event->isSubscription());
		$this->assertSame(TransactionType::ONE_TIME_PAYMENT, $event->payload->txType);
		$this->assertSame(TransactionStatusValue::COMPLETED, $event->payload->status?->status);
		$this->assertSame('https://qbitflow.app/manage/s1', $event->payload->managementPageLink);
	}

	#[Test]
	public function it_flags_a_transaction_event_that_announces_a_new_subscription(): void
	{
		$this->controller()->transaction($this->request([
			'uuid' => 's2',
			'txType' => 'createSubscription',
			'session' => ['uuid' => 's2', 'txType' => 'createSubscription', 'frequency' => 2592000],
		]));

		$event = $this->dispatched[0];
		$this->assertInstanceOf(TransactionWebhookReceived::class, $event);
		$this->assertTrue($event->isSubscription());
		$this->assertNull($event->payload->status, 'No status in this delivery.');
	}

	#[Test]
	public function it_dispatches_a_status_change_event(): void
	{
		// The real envelope carries the subscription identity at the TOP level only;
		// `data` holds just the transition.
		$response = $this->controller()->subscription($this->request([
			'subscriptionUUID' => 'sub@01a0',
			'subscriptionReference' => 'sub-1234',
			'type' => 'status_transition',
			'data' => [
				'previousStatus' => 'active',
				'currentStatus' => 'past_due',
				'updatedAt' => '2026-03-01T12:00:00Z',
			],
		]));

		$this->assertSame(200, $response->getStatusCode());

		$event = $this->dispatched[0];
		$this->assertInstanceOf(SubscriptionStatusChanged::class, $event);
		$this->assertSame(SubscriptionStatus::ACTIVE, $event->transition->previousStatus);
		$this->assertSame(SubscriptionStatus::PAST_DUE, $event->transition->currentStatus);
		$this->assertSame('sub@01a0', $event->subscriptionUUID);
		$this->assertSame('sub-1234', $event->reference());
		$this->assertSame('2026-03-01', $event->transition->updatedAt->format('Y-m-d'));
	}

	#[Test]
	public function it_dispatches_a_billing_event(): void
	{
		$this->controller()->subscription($this->request([
			'subscriptionUUID' => 'sub@01a0',
			'subscriptionReference' => 'sub-1234',
			'type' => 'billing',
			'data' => self::billingData(),
		]));

		$event = $this->dispatched[0];
		$this->assertInstanceOf(SubscriptionBilled::class, $event);
		$this->assertSame('sub-hist@h1', $event->billing->uuid);
		$this->assertSame(29.99, $event->billing->amount);
		$this->assertSame('sub@01a0', $event->subscriptionUUID);
		$this->assertSame('sub-1234', $event->reference());
	}

	#[Test]
	public function a_status_this_sdk_does_not_know_is_delivered_as_a_string_not_a_500(): void
	{
		// A 500 here would make QBitFlow retry the delivery forever. The unknown value is
		// preserved on the event so the listener can still act on it.
		$response = $this->controller()->subscription($this->request([
			'subscriptionUUID' => 'sub@01a0',
			'type' => 'status_transition',
			'data' => [
				'previousStatus' => 'active',
				'currentStatus' => 'paused',
				'updatedAt' => '2026-03-01T12:00:00Z',
			],
		]));

		$this->assertSame(200, $response->getStatusCode());

		$event = $this->dispatched[0];
		$this->assertInstanceOf(SubscriptionStatusChanged::class, $event);
		$this->assertSame('paused', $event->transition->currentStatus);
		$this->assertSame('paused', Enums::value($event->transition->currentStatus));
		$this->assertSame('', $event->reference(), 'No reference on the envelope.');
	}

	#[Test]
	public function an_unknown_transaction_type_is_delivered_as_a_string_not_a_500(): void
	{
		$response = $this->controller()->transaction($this->request([
			'uuid' => 's9',
			'txType' => 'somethingNew',
			'status' => ['status' => 'brand_new_state'],
			'session' => ['uuid' => 's9'],
		]));

		$this->assertSame(200, $response->getStatusCode());

		$event = $this->dispatched[0];
		$this->assertInstanceOf(TransactionWebhookReceived::class, $event);
		$this->assertSame('somethingNew', $event->payload->txType);
		$this->assertSame('brand_new_state', $event->payload->status?->status);
		$this->assertFalse($event->isSubscription());
	}

	#[Test]
	public function it_acknowledges_an_unrecognised_subscription_event_without_dispatching(): void
	{
		// A new event kind shipped by the API should not wedge the endpoint into a
		// permanent retry loop.
		$response = $this->controller()->subscription($this->request([
			'type' => 'something_new',
			'data' => ['subscriptionUUID' => 'sub1'],
		]));

		$this->assertSame(200, $response->getStatusCode());
		$this->assertSame([], $this->dispatched);
	}

	#[Test]
	public function it_tolerates_an_unreadable_body(): void
	{
		$request = Request::create('/webhooks/qbitflow', 'POST', [], [], [], [], 'not json');

		$response = $this->controller()->subscription($request);

		$this->assertSame(200, $response->getStatusCode());
		$this->assertSame([], $this->dispatched);
	}
}
