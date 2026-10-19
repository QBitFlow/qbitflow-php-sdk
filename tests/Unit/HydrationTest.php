<?php

declare(strict_types=1);

namespace QBitFlow\Tests\Unit;

use DateTimeImmutable;
use DateTimeInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use QBitFlow\Enums\AccountingEventType;
use QBitFlow\Enums\FeeLineType;
use QBitFlow\Enums\NetworkFeesPayer;
use QBitFlow\Enums\NotRefundableReason;
use QBitFlow\Enums\Role;
use QBitFlow\Exceptions\ServerException;
use QBitFlow\Http\RawResponse;
use QBitFlow\Http\Requester;
use QBitFlow\Http\Transport;
use QBitFlow\Models;
use QBitFlow\Support\Time;
use QBitFlow\Tests\Support\DecodeTarget;
use QBitFlow\Tests\Support\Fixtures;
use QBitFlow\Tests\Support\TestCase;
use stdClass;

/**
 * The models match the wire (property names are the JSON keys, every key modelled), and the
 * decoding policy (behaviour §6).
 */
final class HydrationTest extends TestCase
{
	/** @return array<string,array{0: string, 1: string}> */
	public static function fixtures(): array
	{
		$out = [];
		foreach (Fixtures::models() as $name => $json) {
			$out[$name] = [$name, $json];
		}

		return $out;
	}

	#[Test]
	#[DataProvider('fixtures')]
	public function every_wire_key_is_a_property_and_back(string $name, string $json): void
	{
		$class = 'QBitFlow\\Models\\' . $name;
		$model = Transport::decode(new RawResponse(200, [], $json), Requester::one($class::fromArray(...)));
		$wire = json_decode($json, false, 512, JSON_THROW_ON_ERROR);

		$this->assertSameKeys($wire, $model, $name);
	}

	private function assertSameKeys(mixed $wire, mixed $model, string $path): void
	{
		if ($wire instanceof stdClass) {
			$this->assertIsObject($model, $path);
			$keys = array_keys(get_object_vars($wire));
			$props = array_keys(get_object_vars($model));
			sort($keys);
			sort($props);
			$this->assertSame($keys, $props, $path . ': the model\'s properties are the wire keys');
			foreach (get_object_vars($wire) as $key => $value) {
				$this->assertSameKeys($value, $model->{$key}, $path . '.' . $key);
			}
		} elseif (is_array($wire)) {
			$this->assertIsArray($model, $path);
			$this->assertCount(count($wire), $model, $path);
			foreach ($wire as $i => $item) {
				$this->assertSameKeys($item, $model[$i], $path . '[]');
			}
		} elseif (is_string($wire) && preg_match('/^\d{4}-\d{2}-\d{2}T/', $wire) === 1) {
			$this->assertInstanceOf(DateTimeInterface::class, $model, $path);
			$this->assertSame((new DateTimeImmutable($wire))->format('U.u'), $model->format('U.u'), $path);
		} elseif (is_int($wire) || is_float($wire)) {
			$this->assertEquals($wire, $model, $path);
		} else {
			$this->assertSame($wire, $model, $path);
		}
	}

	#[Test]
	public function model_values(): void
	{
		$payment = Models\Payment::fromArray(get_object_vars(json_decode(Fixtures::model('Payment'))));
		$this->assertTrue($payment->customer?->deleted);
		$this->assertNull($payment->refund?->respondedAt);
		$this->assertFalse($payment->refundable);
		$this->assertSame(NotRefundableReason::REFUND_EXISTS, $payment->notRefundableReason);
		$this->assertSame(10.0, $payment->metadata->organizationFee?->feePercent);
		$this->assertSame(0.98, $payment->metadata->txAmounts->usd->organization);
		$this->assertSame('10004200', $payment->paidMinUnits, 'decimal strings stay strings');

		$team = Models\InvitationCreated::fromArray(['invitation' => (object) ['test' => null, 'trustLayer' => null, 'role' => 'admin'], 'link' => 'x']);
		$this->assertNull($team->invitation->test);
		$this->assertNull($team->invitation->trustLayer);
		$this->assertSame(Role::ADMIN, $team->invitation->role);

		$member = Models\Member::fromArray(['organizationFeePercent' => 2, 'acceptedCurrencyIds' => [8.0]]);
		$this->assertSame(2.0, $member->organizationFeePercent, 'an integer into a float');
		$this->assertSame([8], $member->acceptedCurrencyIds, 'an integral float into an integer');

		$held = Models\HeldFunds::fromArray(['ledgers' => [['txUuid' => 'refund@1', 'metadata' => null, 'owedMinUnits' => '-10004200']], 'totalAmount' => -10.0042]);
		$this->assertNull($held->ledgers[0]->metadata);
		$this->assertSame('-10004200', $held->ledgers[0]->owedMinUnits);
	}

	#[Test]
	public function checkout_fees(): void
	{
		$decode = static fn (string $class, string $json): object => Transport::decode(new RawResponse(200, [], $json), Requester::one($class::fromArray(...)));

		$payment = $decode(Models\Payment::class, Fixtures::model('Payment'));
		$this->assertSame(9.5, $payment->price);
		$this->assertCount(1, $payment->fees);
		$this->assertInstanceOf(Models\FeeLine::class, $payment->fees[0]);
		$this->assertSame([FeeLineType::CUSTOM, 'Shipping', 'Standard, 3 to 5 days', '0.5'],
			[$payment->fees[0]->type, $payment->fees[0]->label, $payment->fees[0]->description, $payment->fees[0]->amountUsd], 'amountUsd stays a decimal string');

		$withFees = $decode(Models\Payment::class, '{"uuid":"pay@1","amount":5.6,"price":3.99,"fees":['
			. '{"type":"custom","label":"Shipping","description":"Standard, 3 to 5 days","amountUsd":"0.75"},'
			. '{"type":"processingFee","label":"Processing fee","amountUsd":"0.86"},'
			. '{"type":"giftCard","label":"Future line","amountUsd":"0"}]}');
		$this->assertSame(3.99, $withFees->price);
		$this->assertSame([FeeLineType::CUSTOM, FeeLineType::PROCESSING_FEE, 'giftCard'], array_map(static fn (Models\FeeLine $f): string => $f->type, $withFees->fees), 'an unknown type is kept');
		$this->assertNull($withFees->fees[1]->description, 'no description: null');
		$this->assertSame('0.86', $withFees->fees[1]->amountUsd);

		$before = $decode(Models\Payment::class, '{"uuid":"pay@1","amount":10}');
		$this->assertSame(0.0, $before->price, 'absent price: zero (the API sends it, = amount, on older payments)');
		$this->assertSame([], $before->fees, 'absent fees: []');
		$this->assertSame([], $decode(Models\Payment::class, '{"fees":null}')->fees);

		$completed = Models\PaymentCompleted::fromArray(['uuid' => 'pay@1', 'amount' => 10, 'price' => 10]);
		$this->assertSame(10.0, $completed->price, 'payment.completed carries the price');

		$session = $decode(Models\PaymentSessionData::class, '{"uuid":"pay@1","price":3.99,"amount":5.6,"fees":[{"type":"processingFee","label":"Processing fee","amountUsd":"0.86"}],"txType":"payment"}');
		$this->assertSame(5.6, $session->amount);
		$this->assertSame(3.99, $session->price);
		$this->assertSame(FeeLineType::PROCESSING_FEE, $session->fees[0]->type);
		$plain = $decode(Models\PaymentSessionData::class, '{"uuid":"pay@1","price":4.99,"txType":"payment"}');
		$this->assertNull($plain->amount);
		$this->assertSame([], $plain->fees);

		$this->assertSame(['custom', 'processingFee'], FeeLineType::values());
		foreach (['{"fees":{}}', '{"fees":[{"amountUsd":0.75}]}', '{"price":"3.99"}'] as $bad) {
			try {
				$decode(Models\Payment::class, $bad);
				$this->fail('want a ServerException for ' . $bad);
			} catch (ServerException) {
				$this->addToAssertionCount(1);
			}
		}
	}

	#[Test]
	public function subscription_fees(): void
	{
		$decode = static fn (string $class, string $json): object => Transport::decode(new RawResponse(200, [], $json), Requester::one($class::fromArray(...)));

		$sub = $decode(Models\Subscription::class, Fixtures::model('Subscription'));
		$this->assertSame('9.99', $sub->priceUsd, 'priceUsd stays the price');
		$this->assertCount(2, $sub->fees);
		$this->assertInstanceOf(Models\FeeLine::class, $sub->fees[0]);
		$this->assertSame([FeeLineType::CUSTOM, 'VAT (20%)', 'French VAT', '2.00'],
			[$sub->fees[0]->type, $sub->fees[0]->label, $sub->fees[0]->description, $sub->fees[0]->amountUsd]);
		$this->assertSame(FeeLineType::PROCESSING_FEE, $sub->fees[1]->type);

		$this->assertSame([], $decode(Models\Subscription::class, '{"uuid":"sub@1","priceUsd":"9.99"}')->fees, 'absent fees: []');
		$this->assertSame([], $decode(Models\Subscription::class, '{"fees":null}')->fees);

		// Every subscription.* event's data is a Subscription: the fees come along.
		$upcoming = Models\SubscriptionUpcomingBill::fromArray(['priceUsd' => '9.99', 'amountUsd' => 12.12,
			'fees' => [['type' => 'custom', 'label' => 'VAT (20%)', 'amountUsd' => '2.00'], ['type' => 'processingFee', 'label' => 'Processing fee', 'amountUsd' => '0.13']]]);
		$this->assertSame(['2.00', '0.13'], array_map(static fn (Models\FeeLine $f): string => $f->amountUsd, $upcoming->fees));
		$this->assertNull($upcoming->fees[1]->description, 'no description: null');

		try {
			$decode(Models\Subscription::class, '{"fees":{"type":"custom"}}');
			$this->fail('want a ServerException for fees as an object');
		} catch (ServerException) {
			$this->addToAssertionCount(1);
		}
	}

	#[Test]
	public function accounting_event(): void
	{
		$decode = static fn (string $json): Models\AccountingEvent => Transport::decode(new RawResponse(200, [], $json), Requester::one(Models\AccountingEvent::fromArray(...)));

		$event = $decode(Fixtures::model('AccountingEvent'));
		$this->assertSame(AccountingEventType::REFUND, $event->type);
		$this->assertSame('USDC', $event->currency);
		$this->assertSame(1.0, $event->currencyPriceUsd);
		$this->assertSame(['-10.0042', '-10.0042'], [$event->grossAmount, $event->netAmount], 'amounts in the currency\'s units, decimal strings');
		$this->assertSame(NetworkFeesPayer::MERCHANT, $event->networkFeesPaidBy);
		$this->assertSame(['0.0000012', 'ETH', 18, 4512.37], [$event->networkFees, $event->networkFeesCurrency, $event->networkFeesCurrencyDecimals, $event->networkFeesCurrencyPriceUsd]);
		$this->assertSame('2026-09-13T19:23:26.620071', $event->txTimeUtc->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.u'));

		// A referralFee row: only what the referrer earned; no network fees, no payer.
		$referral = $decode('{"txTimeUtc":"2026-09-13T19:23:26Z","type":"referralFee","referralFeeUsd":0.02,"chain":"SOL",'
			. '"currency":"SOL","currencyDecimals":9,"currencyPriceUsd":150.5,"referralFee":"0.000132","referralFeePercent":20,"blockNumberOrSlot":"1","tokenContractOrMint":""}');
		$this->assertSame(AccountingEventType::REFERRAL_FEE, $referral->type);
		$this->assertNull($referral->networkFeesPaidBy);
		$this->assertNull($referral->networkFeesCurrencyDecimals);
		$this->assertNull($referral->networkFees);
		$this->assertNull($referral->grossAmountUsd);
		$this->assertSame(['0.000132', 'SOL'], [$referral->referralFee, $referral->currency]);

		$this->assertSame('somebodyElse', $decode('{"networkFeesPaidBy":"somebodyElse"}')->networkFeesPaidBy, 'an unknown payer is kept');
		$this->assertSame(['payer', 'merchant', 'organization'], NetworkFeesPayer::values());
		foreach (['{"networkFeesCurrencyDecimals":"18"}', '{"grossAmount":10}'] as $bad) {
			try {
				$decode($bad);
				$this->fail('want a ServerException for ' . $bad);
			} catch (ServerException) {
				$this->addToAssertionCount(1);
			}
		}
	}

	/** @return array<string,array{0: string}> */
	public static function badBodies(): array
	{
		return [
			'string for int' => ['{"n":"5"}'],
			'number for string' => ['{"s":5}'],
			'bool for string' => ['{"s":true}'],
			'object for list' => ['{"l":{}}'],
			'empty list for object' => ['{"o":[]}'],
			'number for time' => ['{"t":5}'],
			'bad time' => ['{"t":"yesterday"}'],
			'impossible date' => ['{"t":"2026-02-30T00:00:00Z"}'],
			'fractional into int' => ['{"n":2.5}'],
			'negative into uint' => ['{"u":-1}'],
			'array for object' => ['[1]'],
			'big integer' => ['{"n":99999999999999999999}'],
		];
	}

	#[Test]
	#[DataProvider('badBodies')]
	public function a_wrong_json_type_is_a_server_error(string $body): void
	{
		try {
			Transport::decode(new RawResponse(201, ['x-request-id' => 'rid'], $body), Requester::one(DecodeTarget::fromArray(...)));
			$this->fail('want a ServerException');
		} catch (ServerException $e) {
			$this->assertSame(201, $e->status);
			$this->assertSame('rid', $e->requestId);
		}
	}

	#[Test]
	public function the_decoding_policy(): void
	{
		$decode = static fn (string $body): DecodeTarget => Transport::decode(new RawResponse(200, [], $body), Requester::one(DecodeTarget::fromArray(...)));

		foreach (['absent fields are zero' => '{}', 'null fields are zero' => '{"s":null,"n":null,"f":null,"b":null,"t":null,"p":null,"l":null,"o":null}'] as $name => $body) {
			$v = $decode($body);
			$this->assertSame('', $v->s, $name);
			$this->assertSame(0, $v->n, $name);
			$this->assertSame(0.0, $v->f, $name);
			$this->assertFalse($v->b, $name);
			$this->assertTrue(Time::isZero($v->t), $name);
			$this->assertNull($v->p, $name);
			$this->assertSame([], $v->l, $name);
			$this->assertSame(0, $v->o->x, $name);
		}
		$this->assertSame('x', $decode('{"zzz":{"a":[1]},"s":"x"}')->s, 'unknown keys ignored');
		$this->assertSame('hibernating', $decode('{"enum":"hibernating"}')->enum, 'unknown enum kept');
		$this->assertSame(3.0, $decode('{"f":3}')->f, 'int into float');
		$v = $decode('{"n":2.0,"u":1e3,"f":1.5}');
		$this->assertSame([2, 1000, 1.5], [$v->n, $v->u, $v->f], 'integral float into int');
		$this->assertSame('-10004200.000001', $decode('{"dec":"-10004200.000001"}')->dec);
		$t = $decode('{"t":"2026-09-13T21:23:26.620071+02:00"}')->t;
		$this->assertSame('2026-09-13T19:23:26.620071Z', $t->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.u\Z'));
		$this->assertSame('+02:00', $t->format('P'), 'the offset is kept');
	}
}
