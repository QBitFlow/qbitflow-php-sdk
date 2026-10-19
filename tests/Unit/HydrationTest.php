<?php

declare(strict_types=1);

namespace QBitFlow\Tests\Unit;

use DateTimeImmutable;
use DateTimeInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use QBitFlow\Enums\AccountingEventType;
use QBitFlow\Enums\BillingStage;
use QBitFlow\Enums\Chain;
use QBitFlow\Enums\Credential;
use QBitFlow\Enums\DurationUnit;
use QBitFlow\Enums\FeeLineType;
use QBitFlow\Enums\LedgerEntryType;
use QBitFlow\Enums\NetworkFeesPayer;
use QBitFlow\Enums\NotRefundableReason;
use QBitFlow\Enums\RefundStatus;
use QBitFlow\Enums\Role;
use QBitFlow\Enums\TransactionKind;
use QBitFlow\Enums\TransactionType;
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
 * The models match the wire (property names are the JSON keys, every key modelled; the Go
 * reference's fixtures), and the decoding policy (behaviour §6).
 */
final class HydrationTest extends TestCase
{
	/** @return array<string,array{0: string, 1: string}> */
	public static function fixtures(): array
	{
		$out = [];
		foreach (Fixtures::models() as $name => $json) {
			if ($name === 'Page' || $name === 'EventDetail') {
				continue; // not a Models\* class shaped like its JSON: tested below
			}
			$out[$name] = [$name, $json];
		}

		return $out;
	}

	#[Test]
	#[DataProvider('fixtures')]
	public function every_wire_key_is_a_property_and_back(string $name, string $json): void
	{
		$class = 'QBitFlow\\Models\\' . explode('/', $name)[0];
		$model = Transport::decode(new RawResponse(200, [], $json), Requester::one($class::fromArray(...)));
		$wire = json_decode($json, false, 512, JSON_THROW_ON_ERROR);

		$this->assertSameKeys($wire, $model, $name);
	}

	/**
	 * Every wire key is a property holding the same value; a property the fixture leaves out is
	 * an optional one, so null. A map (`metadata`) is an array keyed like the JSON object.
	 */
	private function assertSameKeys(mixed $wire, mixed $model, string $path): void
	{
		if ($wire instanceof stdClass && is_array($model)) {
			$this->assertSame(array_keys(get_object_vars($wire)), array_keys($model), $path . ': the map keeps the keys');
			foreach (get_object_vars($wire) as $key => $value) {
				$this->assertSameKeys($value, $model[$key], $path . '.' . $key);
			}
		} elseif ($wire instanceof stdClass) {
			$this->assertIsObject($model, $path);
			$keys = array_keys(get_object_vars($wire));
			foreach ($keys as $key) {
				$this->assertTrue(property_exists($model, $key), $path . ': the wire key ' . $key . ' is a property');
			}
			foreach (array_diff(array_keys(get_object_vars($model)), $keys) as $absent) {
				$this->assertNull($model->{$absent}, $path . '.' . $absent . ': a property the fixture leaves out is optional (null)');
			}
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
		} else {
			$this->assertSame($wire, $model, $path);
		}
	}

	/** @return object */
	private static function decode(string $class, string $json): object
	{
		return Transport::decode(new RawResponse(200, [], $json), Requester::one($class::fromArray(...)));
	}

	#[Test]
	public function model_values(): void
	{
		$p = self::decode(Models\Payment::class, Fixtures::model('Payment'));
		$this->assertInstanceOf(Models\Payment::class, $p);
		$this->assertSame(['10', '10000000', '10', '8.5', 'Pro'], [$p->amount, $p->amountMinUnits, $p->amountUsd, $p->priceUsd, $p->productName], 'money stays decimal strings');
		$this->assertSame(['BASE:USDC', Chain::BASE, 3], [$p->currency->code, $p->currency->chain, $p->currency->mainCurrencyId]);
		$this->assertSame(['orderId' => 'order-1', 'crm:customer' => 'C-5521'], $p->metadata);
		$this->assertSame(['10.0042', '10004200', '10.0042'], [$p->paid, $p->paidMinUnits, $p->paidUsd]);
		$this->assertTrue($p->customer?->deleted);
		$this->assertSame('Ada', $p->customer->firstName);
		$this->assertSame([RefundStatus::CANCELLED, '50', '5.0021'], [$p->refund?->status, $p->refund?->refundPercent, $p->refund?->amount]);
		$this->assertNotNull($p->refund->respondedAt);
		$this->assertFalse($p->refundable);
		$this->assertSame(NotRefundableReason::REFUND_EXISTS, $p->notRefundableReason);

		$st = $p->settlement;
		$this->assertSame('0.99', $st->feePercent);
		$this->assertSame(['0xorg', '10'], [$st->organizationFee?->address, $st->organizationFee?->feePercent]);
		$this->assertSame('20', $st->referralFee?->feePercent);
		$this->assertSame(['8.9109', '79200', '0.0042'], [$st->split?->merchant->amount, $st->split?->platform->amountMinUnits, $st->split?->networkFee?->amountUsd]);
		$this->assertSame(['52468103', 95000, '1140000000000', '4512.37'],
			[$st->transaction?->blockNumber, $st->transaction?->gasUsed, $st->transaction?->fee->amountMinUnits, $st->transaction?->nativePriceUsd]);
		$this->assertNotNull($st->transaction->l1Fee);
		$this->assertSame('2026-10-01T11:59:58+00:00', $st->transaction->blockTime?->format(DATE_ATOM));

		$this->assertCount(2, $p->fees);
		$this->assertSame([FeeLineType::CUSTOM, 'VAT (20%)', 'French VAT', '1.30'], [$p->fees[0]->type, $p->fees[0]->label, $p->fees[0]->description, $p->fees[0]->amountUsd]);
		$this->assertSame([FeeLineType::PROCESSING_FEE, 'Processing fee', null, '0.20'], [$p->fees[1]->type, $p->fees[1]->label, $p->fees[1]->description, $p->fees[1]->amountUsd]);

		$native = self::decode(Models\Currency::class, Fixtures::model('Currency/native'));
		$this->assertSame([null, null, 'ETH', 18], [$native->address, $native->mainCurrencyId, $native->code, $native->decimals]);
		$this->assertSame('1.5', self::decode(Models\Currency::class, Fixtures::model('Currency'))->formatAmount('1500000'));

		$ss = self::decode(Models\SubscriptionSessionData::class, Fixtures::model('SubscriptionSessionData'));
		$this->assertSame(['4.81', '3.99', 2, TransactionType::CREATE_SUBSCRIPTION], [$ss->amountUsd, $ss->priceUsd, count($ss->fees), $ss->txType]);
		$this->assertSame(['ETH', 'order-1044', 1, DurationUnit::MONTHS, 3, true],
			[$ss->availableCurrencies[1]->code, $ss->metadata['orderId'], $ss->frequency->value, $ss->frequency->unit, $ss->minPeriods, $ss->upgradingFromTrial]);

		$sb = self::decode(Models\Subscription::class, Fixtures::model('Subscription'));
		$this->assertSame('9.99', $sb->priceUsd);
		$this->assertInstanceOf(Models\Allowance::class, $sb->allowance);
		$this->assertSame(['110', 0, '30', '30000000'], [$sb->allowance->amount, $sb->allowance->remainingPeriods, $sb->allowance->maxPerPeriod, $sb->allowance->maxPerPeriodMinUnits]);
		$this->assertNotNull($sb->lastBilledAt);
		$this->assertNotNull($sb->nextBillingAt);
		$this->assertNotNull($sb->cancellableAt);
		$this->assertSame(['pro', 3, 'USDC'], [$sb->metadata['plan'], $sb->dunning?->remainingAttempts, $sb->currency->symbol]);
		$this->assertNotNull($sb->managementPageLink);

		$tx = self::decode(Models\Transaction::class, Fixtures::model('Transaction'));
		$this->assertSame([TransactionKind::BILL, '8.5', 'v'], [$tx->type, $tx->priceUsd, $tx->metadata['k']]);
		$this->assertNotNull($tx->periodEnd);
		$this->assertNotNull($tx->settlement->split);

		$tb = self::decode(Models\TestBill::class, Fixtures::model('TestBill'));
		$this->assertStringStartsWith('sub-hist@', $tb->billUuid);
		$this->assertSame(BillingStage::WAITING, $tb->stage);
		$this->assertNotNull($tb->nextAttemptAt);

		$r = self::decode(Models\Refund::class, Fixtures::model('Refund'));
		$this->assertSame(['10.0042', '50', '5.0021', '52468103', 8, 'c-1'],
			[$r->paid, $r->refundPercent, $r->amount, $r->settlement?->transaction?->blockNumber, $r->currency->id, $r->customerUuid]);

		$ae = self::decode(Models\AccountingEvent::class, Fixtures::model('AccountingEvent'));
		$this->assertSame(['USDC', '1.00002', NetworkFeesPayer::MERCHANT, 'ETH', 18, '4512.37', '-10.0042', '-10004200', 'refund@1', 'pay@0', '0.99'],
			[$ae->currency, $ae->currencyPriceUsd, $ae->networkFeesPaidBy, $ae->networkFeesCurrency, $ae->networkFeesCurrencyDecimals,
				$ae->networkFeesCurrencyPriceUsd, $ae->netAmount, $ae->grossAmountMinUnits, $ae->txUuid, $ae->relatedTxUuid, $ae->platformFeePercent]);
		$this->assertSame('2026-09-13T19:23:26.620000', $ae->txTimeUtc->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.u'));

		$inv = self::decode(Models\CreatedInvitation::class, Fixtures::model('CreatedInvitation'));
		$this->assertInstanceOf(Models\Invitation::class, $inv, 'flat: the invitation\'s fields and its link');
		$this->assertSame(['i-1', true, false, '0'], [$inv->uuid, $inv->test, $inv->holdFunds, $inv->organizationFeePercent]);
		$this->assertNotSame('', $inv->link);
		// A team invitation: mode-free, so test, holdFunds and the fee are absent.
		$team = self::decode(Models\Invitation::class, '{"uuid":"i-2","email":"a@b.co","role":"admin","expiresAt":"2026-10-08T12:00:00Z","createdAt":"2026-10-01T12:00:00Z","status":"pending"}');
		$this->assertSame([null, null, null, Role::ADMIN, null], [$team->test, $team->holdFunds, $team->organizationFeePercent, $team->role, $team->acceptedAt]);

		$hf = self::decode(Models\HeldFunds::class, Fixtures::model('HeldFunds'));
		$this->assertSame(['-1.0933', LedgerEntryType::REFUND, '-10.0042', 'pay@1'], [$hf->totalUsd, $hf->ledgers[1]->type, $hf->ledgers[1]->owed, $hf->ledgers[1]->refundedTxUuid]);
		$this->assertNotNull($hf->ledgers[0]->settlement);
		$this->assertNull($hf->ledgers[1]->settlement);

		$w = self::decode(Models\Wallet::class, Fixtures::model('Wallet'));
		$this->assertSame(['0xpk', 'ETH', 'BASE:USDC', '12.5', 8], [$w->address, $w->currency->code, $w->tokenWallets[0]->currency->code,
			$w->tokenWallets[0]->balance?->amount, $w->tokenWallets[0]->balance?->currency->id]);
		$this->assertInstanceOf(Models\TokenAmount::class, $w->tokenWallets[0]->balance);

		$m = self::decode(Models\Member::class, Fixtures::model('Member'));
		$this->assertSame(['Ada', '7.5', 1, 'BASE:USDC'], [$m->firstName, $m->organizationFeePercent, count($m->acceptedCurrencies), $m->acceptedCurrencies[0]->code]);

		$me = self::decode(Models\Me::class, Fixtures::model('Me'));
		$this->assertSame(['m-1', Role::ADMIN, '7.5', 'Ada'], [$me->onBehalfOfUserUuid, $me->space?->role, $me->space?->organizationFeePercent, $me->space?->member?->firstName]);
		$this->assertNotNull($me->space->trustedAt);
		// The session-only fields (user, spaces, reauthenticatedUntil) are not modelled: ignored.
		$this->assertSame(Credential::API_KEY, self::decode(Models\Me::class, '{"credential":"apiKey","spaces":[],"user":{"uuid":"u"}}')->credential);

		$page = Transport::decode(new RawResponse(200, [], Fixtures::model('Page')), Requester::page(Models\CustomerSummary::fromArray(...)));
		$this->assertTrue($page->hasMore);
		$this->assertSame('djF8MDE5Y2FkZmQ', $page->nextCursor);
		$this->assertCount(1, $page->items);
		$this->assertInstanceOf(Models\CustomerSummary::class, $page->items[0]);
		$last = Transport::decode(new RawResponse(200, [], '{"items":[],"hasMore":false}'), Requester::page(Models\CustomerSummary::fromArray(...)));
		$this->assertSame([[], false, null], [$last->items, $last->hasMore, $last->nextCursor]);

		$detail = self::decode(Models\EventDetail::class, Fixtures::model('EventDetail'));
		$this->assertSame(['evt_1', 'payment.completed', 'm-1'], [$detail->id, $detail->type, $detail->userUuid]);
		$this->assertInstanceOf(Models\PaymentCompleted::class, $detail->data);
		$this->assertSame(['e-1', true, 2, true, 500, 120], [$detail->deliveries[0]->endpointUuid, $detail->deliveries[0]->delivered,
			$detail->deliveries[0]->attempts[0]->attempt, $detail->deliveries[0]->attempts[0]->skipped,
			$detail->deliveries[0]->attempts[0]->statusCode, $detail->deliveries[0]->attempts[0]->durationMs]);
		$this->assertFalse(self::decode(Models\DeliveryAttempt::class, '{"uuid":"a-1"}')->skipped, 'skipped absent: false');
	}

	/** The minimal shapes the docs allow: every optional field absent is null, every list `[]`. */
	#[Test]
	public function optional_fields(): void
	{
		$sub = self::decode(Models\Subscription::class, '{"uuid":"sub@1","to":"0xt","status":"trial","priceUsd":"9.99","fees":[],"metadata":{},'
			. '"currency":' . Fixtures::CURRENCY . ',"frequency":{"value":1,"unit":"months"},"currentPeriodEnd":"2026-10-15T12:00:00Z"}');
		$this->assertSame([null, null, null, null, null, null, null, null, null],
			[$sub->from, $sub->productUuid, $sub->subscriptionHash, $sub->allowance, $sub->lastBilledAt, $sub->nextBillingAt, $sub->cancellableAt, $sub->dunning, $sub->actionRequired]);
		$this->assertNotNull($sub->currentPeriodEnd);
		$this->assertSame([], $sub->metadata);

		$pay = self::decode(Models\Payment::class, '{"uuid":"pay@1","amount":"10","priceUsd":"10","fees":[],"metadata":{},"settlement":{"feePercent":"0.99"}}');
		$this->assertSame([null, null, null, null, null, null, null, null],
			[$pay->settlement->split, $pay->settlement->transaction, $pay->confirmedAt, $pay->paid, $pay->refund, $pay->refundable, $pay->customer, $pay->productDescription]);

		$ref = self::decode(Models\Refund::class, '{"uuid":"refund@1","txUuid":"pay@1","status":"pending","paid":"10","paidMinUnits":"10000000",'
			. '"refundPercent":"100","amount":"10","amountMinUnits":"10000000"}');
		$this->assertSame([null, null, null, null, null, null, null, null],
			[$ref->reason, $ref->merchantMessage, $ref->respondedAt, $ref->txHash, $ref->paidUsd, $ref->amountUsd, $ref->settlement, $ref->approval]);

		// Lenient on absence and null (behaviour §6): zero values, never an error.
		$nulls = self::decode(Models\Product::class, '{"uuid":"p-1","description":null,"metadata":null,"subscription":null,"priceUsd":null}');
		$this->assertSame([null, [], null, ''], [$nulls->description, $nulls->metadata, $nulls->subscription, $nulls->priceUsd]);
		$this->assertSame(0, self::decode(Models\Payment::class, '{}')->currency->id, 'an absent required object is its zero value');

		$referral = self::decode(Models\AccountingEvent::class, '{"txTimeUtc":"2026-09-13T19:23:26Z","type":"referralFee","referralFeeUsd":"0.02",'
			. '"chain":"SOL","currency":"SOL","currencyDecimals":9,"currencyPriceUsd":"150.5","referralFee":"0.000132","referralFeeMinUnits":"132000",'
			. '"referralFeePercent":"20","blockNumberOrSlot":"1"}');
		$this->assertSame(AccountingEventType::REFERRAL_FEE, $referral->type);
		$this->assertSame([null, null, null, '0.000132', 'SOL', null, null, null, null],
			[$referral->networkFeesPaidBy, $referral->networkFeesCurrencyDecimals, $referral->networkFees, $referral->referralFee, $referral->currency,
				$referral->grossAmountUsd, $referral->organizationFee, $referral->tokenContractOrMint, $referral->txUuid], 'an absent fee is null, never "0"');
	}

	/** @return array<string,array{0: class-string, 1: string}> */
	public static function wrongTypes(): array
	{
		return [
			'payment amount as a number' => [Models\Payment::class, '{"amount":10}'],
			'payment priceUsd as a number' => [Models\Payment::class, '{"priceUsd":8.5}'],
			'fee amountUsd as a number' => [Models\Payment::class, '{"fees":[{"amountUsd":2}]}'],
			'fees as an object' => [Models\Payment::class, '{"fees":{"type":"custom"}}'],
			'settlement feePercent a number' => [Models\Payment::class, '{"settlement":{"feePercent":0.99}}'],
			'product priceUsd as a number' => [Models\Product::class, '{"priceUsd":1e2}'],
			'member fee percent a number' => [Models\Member::class, '{"organizationFeePercent":5}'],
			'refund percent as a number' => [Models\Refund::class, '{"refundPercent":100}'],
			'held funds total as a number' => [Models\HeldFunds::class, '{"totalUsd":-10}'],
			'balance amount as a number' => [Models\Balance::class, '{"amount":12.5}'],
			'accounting gross as a number' => [Models\AccountingEvent::class, '{"grossAmount":-10.0042}'],
			'currency as an id' => [Models\Payment::class, '{"currency":8}'],
			'allowance as a string' => [Models\Subscription::class, '{"allowance":"110000000"}'],
			'metadata value as a number' => [Models\Customer::class, '{"metadata":{"k":1}}'],
			'metadata as a list' => [Models\Customer::class, '{"metadata":[]}'],
			'networkFeesCurrencyDecimals as a string' => [Models\AccountingEvent::class, '{"networkFeesCurrencyDecimals":"18"}'],
		];
	}

	/**
	 * Money, token amounts and rates are decimal strings: a JSON number is refused, never parsed
	 * into a float; an object field refuses a list.
	 *
	 * @param class-string $class
	 */
	#[Test]
	#[DataProvider('wrongTypes')]
	public function a_decimal_or_object_field_refuses_another_json_type(string $class, string $json): void
	{
		$this->expectException(ServerException::class);
		self::decode($class, $json);
	}

	#[Test]
	public function a_page_refuses_a_non_boolean_has_more(): void
	{
		$this->expectException(ServerException::class);
		Transport::decode(new RawResponse(200, [], '{"items":[],"hasMore":"false"}'), Requester::page(Models\Product::fromArray(...)));
	}

	#[Test]
	public function integral_floats_fit_integer_fields(): void
	{
		$f = self::decode(Models\Failure::class, '{"attempt":2.0,"currency":{"id":8.0,"decimals":6.0}}');
		$this->assertSame([2, 8, 6], [$f->attempt, $f->currency->id, $f->currency->decimals]);
		$s = self::decode(Models\Subscription::class, '{"allowance":{"amount":"1","amountMinUnits":"1","remainingPeriods":11.0}}');
		$this->assertSame(11, $s->allowance?->remainingPeriods);
	}

	#[Test]
	public function checkout_fees(): void
	{
		$none = self::decode(Models\Payment::class, '{"uuid":"pay@1","amount":"10","priceUsd":"10","fees":[]}');
		$this->assertSame(['10', []], [$none->priceUsd, $none->fees]);
		$absent = self::decode(Models\Payment::class, '{"uuid":"pay@1","fees":null}');
		$this->assertSame(['', []], [$absent->priceUsd, $absent->fees], 'price absent: ""; fees null: []');

		$p = self::decode(Models\Payment::class, '{"amountUsd":"12.03","priceUsd":"10","fees":[{"type":"tip","label":"Tip","amountUsd":"2"},'
			. '{"type":"processingFee","label":"Processing fee","description":null,"amountUsd":"0.03"}]}');
		$this->assertSame(['tip', '2', null, FeeLineType::PROCESSING_FEE], [$p->fees[0]->type, $p->fees[0]->amountUsd, $p->fees[1]->description, $p->fees[1]->type], 'an unknown line type is kept');
		$this->assertSame(['custom', 'processingFee'], FeeLineType::values());

		// Every subscription.* event's data is a Subscription: the fees come along.
		$upcoming = Models\SubscriptionUpcomingBill::fromArray(['priceUsd' => '9.99', 'amountUsd' => '12.12',
			'fees' => [['type' => 'custom', 'label' => 'VAT (20%)', 'amountUsd' => '2.00'], ['type' => 'processingFee', 'label' => 'Processing fee', 'amountUsd' => '0.13']]]);
		$this->assertSame(['2.00', '0.13'], array_map(static fn (Models\FeeLine $f): string => $f->amountUsd, $upcoming->fees));
		$this->assertSame('12.12', $upcoming->amountUsd);
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
