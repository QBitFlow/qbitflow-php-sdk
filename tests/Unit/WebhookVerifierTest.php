<?php

declare(strict_types=1);

namespace QBitFlow\Tests\Unit;

use DateTimeImmutable;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use QBitFlow\Enums\EventType;
use QBitFlow\Enums\LedgerEntryType;
use QBitFlow\Enums\RefundStatus;
use QBitFlow\Enums\SubscriptionStatus;
use QBitFlow\Enums\TransferType;
use QBitFlow\Events;
use QBitFlow\Events\Event;
use QBitFlow\Exceptions\ApiException;
use QBitFlow\Exceptions\ValidationException;
use QBitFlow\Exceptions\WebhookSignatureException;
use QBitFlow\Models;
use QBitFlow\QBitFlow;
use QBitFlow\Tests\Support\TestCase;
use QBitFlow\Webhooks\Webhook;

/**
 * Webhook verification (behaviour §7, §10, §10.1) and event parsing (types §4).
 */
final class WebhookVerifierTest extends TestCase
{
	private const BODY = '{"createdAt":"2026-10-01T12:00:00Z","data":{},"id":"evt_3f1c2d4e-5a6b-5c7d-8e9f-0a1b2c3d4e5f","test":false,"type":"webhook.test","version":"v2"}';

	private const T = 1790856000;

	private const NEW = '4a158046f55556e922bdec376a917c3ac338ba575b495f825427541a60bd2f4d';

	private const OLD = '1cc78a6297ad96dd282d9d0399def50ed42a4e651885ac79f8528194227bc4f5';

	private const HEADER = 't=1790856000,v1=' . self::NEW . ',v1=' . self::OLD;

	private static function at(int $seconds): \Closure
	{
		return static fn (): int => self::T + $seconds;
	}

	private static function sign(string $secret, int|string $t, string $body): string
	{
		return hash_hmac('sha256', $t . '.' . $body, $secret);
	}

	private static function reason(\Closure $verify): string
	{
		try {
			$verify();
		} catch (WebhookSignatureException $e) {
			return $e->reason;
		}

		return '';
	}

	#[Test]
	public function the_docs_vector_with_a_secret_rotation(): void
	{
		$this->assertSame(self::NEW, self::sign('whsec_new_secret', self::T, self::BODY));
		$this->assertSame(self::OLD, self::sign('whsec_old_secret', self::T, self::BODY));

		foreach (['whsec_new_secret', 'whsec_old_secret'] as $secret) {
			Webhook::verify(self::BODY, self::HEADER, $secret, now: self::at(60));
		}
		foreach (['whsec_other', 'new_secret', 'whsec_new_secret '] as $secret) {
			$this->assertSame('noMatchingSignature', self::reason(static fn () => Webhook::verify(self::BODY, self::HEADER, $secret, now: self::at(60))), $secret);
		}
		foreach (['whsec_new_secret', 'whsec_old_secret', 'whsec_other'] as $secret) {
			$this->assertSame('timestampOutsideTolerance', self::reason(static fn () => Webhook::verify(self::BODY, self::HEADER, $secret, now: self::at(360))), $secret);
		}
	}

	#[Test]
	public function the_tolerance(): void
	{
		$header = 't=1790856000,v1=' . self::NEW;
		$cases = [[0, 300, true], [300, 300, true], [301, 300, false], [-300, 300, true], [-301, 300, false], [60, 10, false], [10, 10, true],
			[299, 0, true], [301, -1, false], [3000, 3600, true]];
		foreach ($cases as [$offset, $tolerance, $ok]) {
			$reason = self::reason(static fn () => Webhook::verify(self::BODY, $header, 'whsec_new_secret', $tolerance, self::at($offset)));
			$this->assertSame($ok ? '' : 'timestampOutsideTolerance', $reason, "offset $offset tolerance $tolerance");
		}
		$this->assertSame('timestampOutsideTolerance', self::reason(static fn () => Webhook::verify(self::BODY, 't=9223372036854775807,v1=' . self::NEW, 'whsec_new_secret', now: self::at(0))));
		Webhook::verify(self::BODY, $header, 'whsec_new_secret', now: static fn () => new DateTimeImmutable('@' . (self::T + 5)));
	}

	/** @return array<string,array{0: string, 1: string}> */
	public static function headers(): array
	{
		$ts = '1790856000';

		return [
			'rotation' => [self::HEADER, ''],
			'new only' => ["t=$ts,v1=" . self::NEW, ''],
			'v1 first' => ['v1=' . self::NEW . ",t=$ts", ''],
			'spaces around parts' => ["  t = $ts ,  v1 = " . self::NEW . '  ', ''],
			'bad v1 then good' => ["t=$ts,v1=deadbeef,v1=" . self::NEW, ''],
			'good v1 then bad' => ["t=$ts,v1=" . self::NEW . ',v1=deadbeef', ''],
			'unknown scheme ignored' => ["t=$ts,v0=" . self::NEW . ',v1=' . self::NEW . ',v2=x', ''],
			'parts without =' => ["t=$ts,junk,v1=" . self::NEW . ',', ''],
			'empty' => ['', 'missingHeader'],
			'blank' => ['   ', 'missingHeader'],
			'garbage' => ['garbage', 'malformedHeader'],
			'no t' => ['v1=' . self::NEW, 'malformedHeader'],
			'no v1' => ["t=$ts", 'malformedHeader'],
			'only unknown scheme' => ["t=$ts,v0=" . self::NEW, 'malformedHeader'],
			'sha256 legacy scheme' => ['sha256=' . self::NEW, 'malformedHeader'],
			't not a number' => ['t=abc,v1=' . self::NEW, 'malformedHeader'],
			't negative' => ["t=-$ts,v1=" . self::NEW, 'malformedHeader'],
			't with sign' => ["t=+$ts,v1=" . self::NEW, 'malformedHeader'],
			't decimal' => ["t=$ts.0,v1=" . self::NEW, 'malformedHeader'],
			't empty' => ['t=,v1=' . self::NEW, 'malformedHeader'],
			't non-ASCII digits' => ['t=１７９０８５６０００,v1=' . self::NEW, 'malformedHeader'],
			't overflow' => ['t=99999999999999999999,v1=' . self::NEW, 'malformedHeader'],
			't just over int64' => ['t=9223372036854775808,v1=' . self::NEW, 'malformedHeader'],
			'duplicate t' => ["t=$ts,t=$ts,v1=" . self::NEW, 'malformedHeader'],
			'uppercase hex' => ["t=$ts,v1=" . strtoupper(self::NEW), 'noMatchingSignature'],
			'truncated hex' => ["t=$ts,v1=" . substr(self::NEW, 0, 63), 'noMatchingSignature'],
			'value with =' => ["t=$ts,v1=" . self::NEW . '=', 'noMatchingSignature'],
			'empty v1' => ["t=$ts,v1=", 'noMatchingSignature'],
			'other t' => ['t=1790856001,v1=' . self::NEW, 'noMatchingSignature'],
			'leading zeros are signed as sent' => ["t=0$ts,v1=" . self::NEW, 'noMatchingSignature'],
		];
	}

	#[Test]
	#[DataProvider('headers')]
	public function it_parses_the_header(string $header, string $reason): void
	{
		try {
			Webhook::verify(self::BODY, $header, 'whsec_new_secret', now: self::at(30));
			$this->assertSame('', $reason);
		} catch (WebhookSignatureException $e) {
			$this->assertSame($reason, $e->reason);
			$this->assertSame($reason, $e->getReason());
			$this->assertInstanceOf(ApiException::class, $e);
			$this->assertSame(0, $e->status);
		}
	}

	#[Test]
	public function the_t_text_is_signed_as_received(): void
	{
		$header = 't=0' . self::T . ',v1=' . self::sign('whsec_new_secret', '0' . self::T, self::BODY);
		Webhook::verify(self::BODY, $header, 'whsec_new_secret', now: self::at(0));
		$this->addToAssertionCount(1);
	}

	#[Test]
	public function the_raw_body_is_what_is_signed(): void
	{
		$header = 't=1790856000,v1=' . self::NEW;
		foreach ([str_replace('"test":false', '"test":true', self::BODY), str_replace(',"id"', ', "id"', self::BODY), self::BODY . "\n"] as $body) {
			$this->assertSame('noMatchingSignature', self::reason(static fn () => Webhook::verify($body, $header, 'whsec_new_secret', now: self::at(0))));
		}
		foreach (['{"name":"Zoë – 李小龙 ☃"}', "\xff\xfe{}", ''] as $body) {
			Webhook::verify($body, 't=1790856000,v1=' . self::sign('whsec_new_secret', self::T, $body), 'whsec_new_secret', now: self::at(0));
		}
		$h = 't=1790856000,v1=' . self::sign('new_secret', self::T, self::BODY);
		$this->assertSame('noMatchingSignature', self::reason(static fn () => Webhook::verify(self::BODY, $h, 'whsec_new_secret', now: self::at(0))), 'the whsec_ prefix is part of the key');

		$this->assertSame(['secret'], $this->failingFields(static fn () => Webhook::verify(self::BODY, $header, '', now: self::at(0))));
	}

	#[Test]
	public function it_verifies_a_psr7_request(): void
	{
		$request = new ServerRequest('POST', '/webhooks', [Webhook::SIGNATURE_HEADER => self::HEADER], self::BODY);
		$this->assertSame(self::BODY, Webhook::verifyRequest($request, 'whsec_old_secret', now: self::at(60)));
		$this->assertSame(self::BODY, (string) $request->getBody(), 'the body can be read again');

		$this->assertSame('missingHeader', self::reason(static fn () => Webhook::verifyRequest(new ServerRequest('POST', '/', [], self::BODY), 'whsec_new_secret', now: self::at(0))));

		$big = str_repeat('x', Webhook::MAX_BODY_BYTES + 1);
		$this->assertSame(['body'], $this->failingFields(static fn () => Webhook::verifyRequest(
			new ServerRequest('POST', '/', [Webhook::SIGNATURE_HEADER => 't=1790856000,v1=' . self::sign('whsec_new_secret', self::T, $big)], $big), 'whsec_new_secret', now: self::at(0))));
		$exact = str_repeat('x', Webhook::MAX_BODY_BYTES);
		$this->assertSame($exact, Webhook::verifyRequest(
			new ServerRequest('POST', '/', [Webhook::SIGNATURE_HEADER => 't=1790856000,v1=' . self::sign('whsec_new_secret', self::T, $exact)], $exact), 'whsec_new_secret', now: self::at(0)));
	}

	#[Test]
	public function construct_event_verifies_then_parses(): void
	{
		$event = Webhook::constructEvent(self::BODY, self::HEADER, 'whsec_new_secret', now: self::at(60));
		$this->assertInstanceOf(Events\WebhookTestEvent::class, $event);
		$this->assertSame('evt_3f1c2d4e-5a6b-5c7d-8e9f-0a1b2c3d4e5f', $event->id);
		$this->assertSame(EventType::WEBHOOK_TEST, $event->type);
		$this->assertSame('v2', $event->version);
		$this->assertFalse($event->test);
		$this->assertSame('2026-10-01T12:00:00+00:00', $event->createdAt->format(DATE_ATOM));
		$this->assertNull($event->userUuid);
		$this->assertSame('noMatchingSignature', self::reason(static fn () => Webhook::constructEvent(self::BODY, self::HEADER, 'whsec_other', now: self::at(60))));

		// The client's webhooks service delegates to the same functions.
		$client = new QBitFlow('sk_x');
		$client->webhooks->verify(self::BODY, self::HEADER, 'whsec_old_secret', now: self::at(60));
		$this->assertInstanceOf(Events\WebhookTestEvent::class, $client->webhooks->constructEvent(self::BODY, self::HEADER, 'whsec_old_secret', now: self::at(60)));
		$this->assertInstanceOf(Events\WebhookTestEvent::class, $client->webhooks->parseEvent(self::BODY));

		// A v1 body signed correctly is still refused: the endpoint must move to v2.
		$v1 = '{"uuid":"pay@x","txType":"payment","status":{"status":"completed"}}';
		$this->assertSame(['version'], $this->failingFields(static fn () => Webhook::constructEvent($v1, 't=1790856000,v1=' . self::sign('whsec_new_secret', self::T, $v1), 'whsec_new_secret', now: self::at(0))));
	}

	private static function envelope(string $type, string $data, string $extra = ''): string
	{
		return '{"id":"evt_1","type":"' . $type . '","version":"v2","createdAt":"2026-10-01T12:00:00Z","test":true' . $extra . ',"data":' . $data . '}';
	}

	#[Test]
	public function every_event_type_is_typed(): void
	{
		$sub = '"uuid":"sub@1","status":"pastDue","frequency":{"value":1,"unit":"months"},"priceUsd":"9.99","currentPeriodEnd":"2026-10-31T12:00:00Z"';
		$cases = [
			EventType::PAYMENT_COMPLETED => [Events\PaymentCompletedEvent::class, Models\PaymentCompleted::class,
				'{"uuid":"pay@1","amount":10,"amountMinUnits":"10000000","reference":"order-1042","chain":"BASE","paidMinUnits":"10004200","paidUsd":10.0042,"managementPageLink":"https://m","metadata":{"feePercent":1.5}}'],
			EventType::SUBSCRIPTION_CREATED => [Events\SubscriptionCreatedEvent::class, Models\SubscriptionCreated::class, '{' . $sub . ',"managementPageLink":"https://m"}'],
			EventType::SUBSCRIPTION_BILLED => [Events\SubscriptionBilledEvent::class, Models\SubscriptionBilled::class,
				'{"uuid":"sub-hist@1","subscriptionUuid":"sub@1","subscriptionReference":"r","subscriptionStatus":"active","periodEnd":"2026-10-31T12:00:00Z"}'],
			EventType::SUBSCRIPTION_STATUS_CHANGED => [Events\SubscriptionStatusChangedEvent::class, Models\SubscriptionStatusChanged::class, '{' . $sub . ',"previousStatus":"active"}'],
			EventType::SUBSCRIPTION_ACTION_REQUIRED_CHANGED => [Events\SubscriptionActionRequiredChangedEvent::class, Models\SubscriptionActionRequiredChanged::class,
				'{' . $sub . ',"actionRequired":"topUpAllowance"}'],
			EventType::SUBSCRIPTION_BILLING_FAILED => [Events\SubscriptionBillingFailedEvent::class, Models\SubscriptionBillingFailed::class,
				'{' . $sub . ',"reason":"insufficientBalance","failureCode":"insufficient_funds","billUuid":"sub-hist@1","amountUsd":"10","attempt":1,"remainingAttempts":4,"nextAttemptAt":"2026-10-02T12:00:00Z"}'],
			EventType::SUBSCRIPTION_UPCOMING_BILL => [Events\SubscriptionUpcomingBillEvent::class, Models\SubscriptionUpcomingBill::class,
				'{' . $sub . ',"billingDate":"2026-10-31T12:00:00Z","amountUsd":9.99,"trialEnding":false,"balanceSufficient":true,"allowanceSufficient":false}'],
			EventType::REFUND_REQUESTED => [Events\RefundRequestedEvent::class, Models\Refund::class, '{"uuid":"refund@1","status":"pending","txUuid":"pay@1","refundPercent":100}'],
			EventType::REFUND_COMPLETED => [Events\RefundCompletedEvent::class, Models\Refund::class, '{"uuid":"refund@1","status":"approved","respondedAt":"2026-10-02T12:00:00Z"}'],
			EventType::REFUND_DENIED => [Events\RefundDeniedEvent::class, Models\Refund::class, '{"uuid":"refund@1","status":"rejected"}'],
			EventType::MEMBER_JOINED => [Events\MemberJoinedEvent::class, Models\MemberJoined::class, '{"userUuid":"u-1","invitationUuid":"i-1","joinedAt":"2026-10-01T12:00:00Z"}'],
			EventType::MEMBER_REMOVED => [Events\MemberRemovedEvent::class, Models\Member::class, '{"userUuid":"u-1"}'],
			EventType::HELD_FUNDS_RELEASED => [Events\HeldFundsReleasedEvent::class, Models\HeldFundsReleased::class,
				'{"uuid":"transfer@1","received":true,"type":"heldFundsRelease","txMetadata":{"mainCurrencyPriceUsd":4512.37},"ledgers":[{"txUuid":"pay@1","type":"payment","owedMinUnits":"98500000","owedUsd":98.5,"metadata":{"feePercent":1.5}},{"txUuid":"refund@1","type":"refund","refundedTxUuid":"pay@1","owedMinUnits":"-1","metadata":null}]}'],
			EventType::CHECKOUT_EXPIRED => [Events\CheckoutExpiredEvent::class, Models\PaymentSessionData::class,
				'{"uuid":"pay@1","txType":"payment","price":10,"availableCurrencyIds":[2,5,8],"organizationName":"Example Shop","expiresAt":"2026-10-01T12:00:00Z"}'],
			EventType::WEBHOOK_TEST => [Events\WebhookTestEvent::class, Models\WebhookTest::class, '{"endpointUuid":"e-1","message":"hi"}'],
		];
		$this->assertCount(count(EventType::values()), $cases);
		foreach ($cases as $type => [$eventClass, $dataClass, $data]) {
			$event = Webhook::parseEvent(self::envelope($type, $data));
			$this->assertSame($eventClass, $event::class, $type);
			$this->assertInstanceOf($dataClass, $event->data, $type);
		}

		$failed = Webhook::parseEvent(self::envelope(EventType::SUBSCRIPTION_BILLING_FAILED, $cases[EventType::SUBSCRIPTION_BILLING_FAILED][2]))->data;
		$this->assertSame('10', $failed->amountUsd, 'billingFailed amountUsd is a decimal string');
		$this->assertSame(SubscriptionStatus::PAST_DUE, $failed->status);
		$this->assertSame(4, $failed->remainingAttempts);
		$upcoming = Webhook::parseEvent(self::envelope(EventType::SUBSCRIPTION_UPCOMING_BILL, $cases[EventType::SUBSCRIPTION_UPCOMING_BILL][2]))->data;
		$this->assertSame(9.99, $upcoming->amountUsd, 'upcomingBill amountUsd is a number');
		$this->assertFalse($upcoming->allowanceSufficient);

		$released = Webhook::parseEvent(self::envelope(EventType::HELD_FUNDS_RELEASED, $cases[EventType::HELD_FUNDS_RELEASED][2], ',"userUuid":"u-1"'));
		$this->assertSame('u-1', $released->userUuid);
		$this->assertSame(TransferType::HELD_FUNDS_RELEASE, $released->data->type);
		$this->assertSame(LedgerEntryType::PAYMENT, $released->data->ledgers[0]->type);
		$this->assertNull($released->data->ledgers[1]->metadata);

		$refund = Webhook::parseEvent(self::envelope(EventType::REFUND_REQUESTED, $cases[EventType::REFUND_REQUESTED][2]))->data;
		$this->assertSame(RefundStatus::PENDING, $refund->status);
		$this->assertNull($refund->respondedAt);
		$this->assertNull($refund->approval, 'webhook data never carries the API-only fields');
	}

	#[Test]
	public function checkout_expired_picks_its_shape(): void
	{
		$payment = Webhook::parseEvent(self::envelope(EventType::CHECKOUT_EXPIRED, '{"uuid":"pay@1","txType":"payment","price":10,"availableCurrencyIds":[2,5,8]}'));
		$this->assertInstanceOf(Models\PaymentSessionData::class, $payment->data);
		$this->assertNotInstanceOf(Models\SubscriptionSessionData::class, $payment->data);
		$this->assertSame(10.0, $payment->data->price);

		$sub = Webhook::parseEvent(self::envelope(EventType::CHECKOUT_EXPIRED,
			'{"uuid":"sub@1","txType":"createSubscription","organizationName":"Shop","test":true,"availableCurrencyIds":[8],"frequency":{"value":1,"unit":"weeks"},"trialPeriod":{"value":14,"unit":"days"},"minPeriods":3}'));
		$this->assertInstanceOf(Models\SubscriptionSessionData::class, $sub->data);
		$this->assertSame(3, $sub->data->minPeriods);
		$this->assertSame('weeks', $sub->data->frequency->unit);

		$unknown = Webhook::parseEvent(self::envelope(EventType::CHECKOUT_EXPIRED, '{"uuid":"x@1","txType":"payAsYouGo","organizationName":"Shop"}'));
		$this->assertInstanceOf(Models\PaymentSessionData::class, $unknown->data);
		$this->assertSame('payAsYouGo', $unknown->data->txType);
	}

	#[Test]
	public function unknown_types_keep_their_raw_data(): void
	{
		$event = Webhook::parseEvent('{"id":"evt_9","type":"invoice.paid","version":"v2","createdAt":"2026-10-01T14:00:00.5+02:00","test":true,"userUuid":"u-1","data":{"anything":[1,2]},"extra":1}');
		$this->assertInstanceOf(Events\UnknownEvent::class, $event);
		$this->assertSame('invoice.paid', $event->type);
		$this->assertSame(['anything' => [1, 2]], $event->data);
		$this->assertSame($event->data, $event->rawData);
		$this->assertSame('u-1', $event->userUuid);
		$this->assertSame('2026-10-01T12:00:00.500000', $event->createdAt->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.u'));
	}

	#[Test]
	public function null_or_absent_data_is_zero(): void
	{
		foreach ([',"data":null', ''] as $data) {
			$event = Webhook::parseEvent('{"id":"evt_1","type":"webhook.test","version":"v2"' . $data . '}');
			$this->assertInstanceOf(Events\WebhookTestEvent::class, $event);
			$this->assertSame('', $event->data->endpointUuid);
		}
	}

	#[Test]
	public function decode_data_reads_another_shape(): void
	{
		$event = Webhook::parseEvent(self::envelope(EventType::SUBSCRIPTION_BILLING_FAILED, '{"uuid":"sub@1","status":"pastDue","amountUsd":"10"}'));
		$this->assertSame(SubscriptionStatus::PAST_DUE, $event->decodeData(Models\Subscription::fromArray(...))->status);
		$this->assertSame(['data'], $this->failingFields(static fn () => $event->decodeData(Models\SubscriptionUpcomingBill::fromArray(...))), 'a float target refuses the billingFailed string');
	}

	/** @return array<string,array{0: string}> */
	public static function invalidBodies(): array
	{
		return [
			'empty' => [''],
			'array' => ['[{"version":"v2"}]'],
			'string' => ['"v2"'],
			'not json' => ['{"version":'],
			'v1' => ['{"id":"evt_1","type":"payment.completed","version":"v1","data":{}}'],
			'no version' => ['{"id":"evt_1","type":"payment.completed","data":{}}'],
			'wrong type in the envelope' => ['{"id":"evt_1","type":"payment.completed","version":"v2","test":"yes"}'],
			'bad createdAt' => ['{"id":"evt_1","type":"webhook.test","version":"v2","createdAt":"today"}'],
			'wrong type in data' => ['{"id":"evt_1","type":"subscription.upcomingBill","version":"v2","data":{"amountUsd":"10"}}'],
			'data a list' => ['{"id":"evt_1","type":"webhook.test","version":"v2","data":[1]}'],
		];
	}

	#[Test]
	#[DataProvider('invalidBodies')]
	public function invalid_bodies_are_validation_errors(string $body): void
	{
		$this->expectException(ValidationException::class);
		Webhook::parseEvent($body);
	}

	#[Test]
	public function api_events_go_through_event_from_array(): void
	{
		$event = Event::fromArray(['id' => 'evt_1', 'type' => 'refund.denied', 'version' => 'v2', 'data' => ['uuid' => 'refund@1', 'status' => 'rejected']]);
		$this->assertInstanceOf(Events\RefundDeniedEvent::class, $event);
		$this->assertSame(RefundStatus::REJECTED, $event->data->status);
	}
}
