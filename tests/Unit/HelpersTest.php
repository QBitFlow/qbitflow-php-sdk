<?php

declare(strict_types=1);

namespace QBitFlow\Tests\Unit;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use QBitFlow\Enums\CheckoutSessionStatusValue;
use QBitFlow\Exceptions\NotFoundException;
use QBitFlow\Exceptions\ValidationException;
use QBitFlow\Models\AccountingEvent;
use QBitFlow\Models\Currency;
use QBitFlow\Models\Subscription;
use QBitFlow\Placeholders;
use QBitFlow\QBitFlow;
use QBitFlow\Support\Amount;
use QBitFlow\Tests\Support\Fixtures;
use QBitFlow\Tests\Support\MockHttpClient;
use QBitFlow\Tests\Support\TestCase;

/**
 * The integration helpers H3 to H8 (H1 and H2: {@see WebhookRouterTest}).
 */
final class HelpersTest extends TestCase
{
	private const SESSION = 'pay@019eca82-5680-7b00-8000-0000000000a1';

	/** @return array{0: int, 1: string} */
	private static function reply(string $status): array
	{
		return [200, '{"uuid":"' . self::SESSION . '","status":"' . $status . '"}'];
	}

	// --- H3: waitForCompletion ------------------------------------------------------------------

	#[Test]
	public function wait_returns_once_completed(): void
	{
		$client = $this->client(MockHttpClient::sequence([self::reply('created'), self::reply('waitingConfirmation'), self::reply('completed')]));
		$status = $client->checkoutSessions->waitForCompletion(self::SESSION);

		$this->assertSame(CheckoutSessionStatusValue::COMPLETED, $status->status);
		$this->assertSame(3, $this->http->count());
		$this->assertSame([3.0, 3.0], $this->sleeps, 'the default interval, with the injected sleep');
		$this->assertSame('/transaction/session-checkout/' . self::SESSION . '/status', self::pathOf($this->http->last()));
	}

	#[Test]
	public function wait_returns_once_expired(): void
	{
		$client = $this->client(MockHttpClient::sequence([self::reply('created'), self::reply('expired')]));
		$this->assertSame(CheckoutSessionStatusValue::EXPIRED, $client->checkoutSessions->waitForCompletion(self::SESSION, interval: 5)->status);
		$this->assertSame([5.0], $this->sleeps);
	}

	#[Test]
	public function wait_returns_the_last_status_seen_on_timeout(): void
	{
		$client = $this->client(MockHttpClient::sequence([self::reply('created'), self::reply('waitingConfirmation')]));
		$status = $client->checkoutSessions->waitForCompletion(self::SESSION, timeout: 10, interval: 3);

		$this->assertSame(CheckoutSessionStatusValue::WAITING_CONFIRMATION, $status->status, 'not final: the caller checks ->status');
		$this->assertSame(5, $this->http->count(), 'polls at 0, 3, 6, 9 and at the deadline');
		$this->assertCount(4, $this->sleeps);
		$this->assertSame([3.0, 3.0, 3.0], array_slice($this->sleeps, 0, 3));
		$this->assertEqualsWithDelta(1.0, $this->sleeps[3], 0.05, 'never waits past the timeout');

	}

	#[Test]
	public function a_timeout_of_zero_or_less_is_the_default(): void
	{
		foreach ([0.0, -1.0] as $timeout) {
			$this->sleeps = [];
			// 600 s at 3 s: the 201st check is the deadline's.
			$client = $this->client(MockHttpClient::static(200, self::reply('created')[1]));
			$status = $client->checkoutSessions->waitForCompletion(self::SESSION, timeout: $timeout);
			$this->assertSame(CheckoutSessionStatusValue::CREATED, $status->status);
			$this->assertSame(201, $this->http->count(), (string) $timeout);
			$this->assertEqualsWithDelta(600.0, array_sum($this->sleeps), 0.05);
		}
	}

	#[Test]
	public function wait_floors_the_interval_at_one_second(): void
	{
		$client = $this->client(MockHttpClient::sequence([self::reply('created'), self::reply('created'), self::reply('completed')]));
		$client->checkoutSessions->waitForCompletion(self::SESSION, interval: 0.1);
		$this->assertSame([1.0, 1.0], $this->sleeps);

		$this->sleeps = [];
		$client = $this->client(MockHttpClient::sequence([self::reply('created'), self::reply('completed')]));
		$client->checkoutSessions->waitForCompletion(self::SESSION, interval: -5);
		$this->assertSame([1.0], $this->sleeps);
	}

	#[Test]
	public function wait_propagates_the_errors(): void
	{
		$client = $this->client(MockHttpClient::sequence([self::reply('created'), [404, '{"error":{"code":"not_found","message":"no such session"}}']]));
		try {
			$client->checkoutSessions->waitForCompletion(self::SESSION);
			$this->fail('want a NotFoundException');
		} catch (NotFoundException $e) {
			$this->assertSame(404, $e->status);
		}
		$this->assertSame(2, $this->http->count());
	}

	#[Test]
	public function wait_checks_its_arguments(): void
	{
		$client = $this->client();
		$this->assertSame(['uuid'], $this->failingFields(static fn () => $client->checkoutSessions->waitForCompletion('nope')));
		$this->assertSame(['timeout'], $this->failingFields(static fn () => $client->checkoutSessions->waitForCompletion(self::SESSION, timeout: INF)));
		$this->assertSame(['interval'], $this->failingFields(static fn () => $client->checkoutSessions->waitForCompletion(self::SESSION, interval: NAN)));
		$this->assertSame(0, $this->http->count());
	}

	// --- H4: hasAccess --------------------------------------------------------------------------

	private static function subscription(?string $currentPeriodEnd): Subscription
	{
		$fields = get_object_vars(json_decode(Fixtures::model('Subscription')));
		$fields['currentPeriodEnd'] = $currentPeriodEnd;

		return Subscription::fromArray($fields);
	}

	#[Test]
	public function access_lasts_until_the_period_end(): void
	{
		$sub = self::subscription('2026-10-31T12:00:00Z');
		$this->assertSame('stopped', $sub->status, 'whatever the status');
		$this->assertTrue($sub->hasAccess(new DateTimeImmutable('2026-10-31T11:59:59Z')));
		$this->assertTrue($sub->hasAccess(new DateTimeImmutable('2026-10-31T13:59:59+02:00')));
		$this->assertFalse($sub->hasAccess(new DateTimeImmutable('2026-10-31T12:00:00Z')), 'equal: no access');
		$this->assertFalse($sub->hasAccess(new DateTimeImmutable('2026-10-31T14:00:00+02:00')), 'the same instant in another zone');
		$this->assertFalse($sub->hasAccess(new DateTimeImmutable('2026-11-01T00:00:00Z')));
		$this->assertFalse($sub->hasAccess(new \DateTime('2026-10-31T12:00:00.000001Z')), 'any DateTimeInterface');

		$this->assertTrue(self::subscription('2999-01-01T00:00:00Z')->hasAccess(), 'default: now');
		$this->assertFalse(self::subscription('2000-01-01T00:00:00Z')->hasAccess());
		$this->assertFalse(self::subscription(null)->hasAccess(new DateTimeImmutable('2000-01-01')), 'no period end: no access');
	}

	// --- H5: amounts ----------------------------------------------------------------------------

	/** @return array<string, array{string, int, string}> */
	public static function formatCases(): array
	{
		return [
			'1.5' => ['1500000', 6, '1.5'],
			'1' => ['1000000', 6, '1'],
			'one min unit' => ['1', 6, '0.000001'],
			'zero' => ['0', 6, '0'],
			'negative' => ['-10004200', 6, '-10.0042'],
			'no decimals' => ['123', 0, '123'],
			'leading zeros' => ['000120', 2, '1.2'],
			'minus zero' => ['-0', 6, '0'],
			'minus zeros' => ['-000', 2, '0'],
			'uint256 max' => ['115792089237316195423570985008687907853269984665640564039457584007913129639935', 18,
				'115792089237316195423570985008687907853269984665640564039457.584007913129639935'],
			'77 decimals' => ['5', 77, '0.' . str_repeat('0', 76) . '5'],
		];
	}

	#[Test]
	#[DataProvider('formatCases')]
	public function format_amount(string $minUnits, int $decimals, string $want): void
	{
		$this->assertSame($want, Amount::format($minUnits, $decimals));
	}

	/** @return array<string, array{string, int, string}> */
	public static function parseCases(): array
	{
		return [
			'1.5' => ['1.5', 6, '1500000'],
			'one min unit' => ['0.000001', 6, '1'],
			'integer' => ['10', 2, '1000'],
			'negative' => ['-0.5', 2, '-50'],
			'minus zero' => ['-0', 2, '0'],
			'minus zero point' => ['-0.00', 2, '0'],
			'all the decimals' => ['1.000001', 6, '1000001'],
			'no decimals' => ['42', 0, '42'],
			'leading zeros' => ['007.5', 1, '75'],
		];
	}

	#[Test]
	#[DataProvider('parseCases')]
	public function parse_amount(string $amount, int $decimals, string $want): void
	{
		$this->assertSame($want, Amount::parse($amount, $decimals));
	}

	#[Test]
	public function amounts_round_trip(): void
	{
		foreach (['1500000', '1', '0', '-10004200', '999999999999999999999999999'] as $minUnits) {
			$this->assertSame($minUnits, Amount::parse(Amount::format($minUnits, 6), 6));
		}
	}

	#[Test]
	public function invalid_amounts_are_validation_errors(): void
	{
		foreach (['', '-', '1.5', '1e6', ' 1', '1 ', '+1', '1,000', '0x10', "1\n"] as $bad) {
			$this->assertSame(['minUnits'], $this->failingFields(static fn () => Amount::format($bad, 6)), var_export($bad, true));
		}
		foreach (['', '-', '.5', '1.', '1e6', '1,5', '1 000', '+1', '--1', '1.2.3', "1\n"] as $bad) {
			$this->assertSame(['amount'], $this->failingFields(static fn () => Amount::parse($bad, 6)), var_export($bad, true));
		}
		$this->assertSame(['amount'], $this->failingFields(static fn () => Amount::parse('1.0000001', 6)), 'more fractional digits than decimals');
		$this->assertSame(['amount'], $this->failingFields(static fn () => Amount::parse('1.5', 0)));
		$this->assertSame(['amount'], $this->failingFields(static fn () => Amount::parse('1.50', 1)), 'trailing zeros count');
		foreach ([-1, 78] as $decimals) {
			$this->assertSame(['decimals'], $this->failingFields(static fn () => Amount::format('1', $decimals)));
			$this->assertSame(['decimals'], $this->failingFields(static fn () => Amount::parse('1', $decimals)));
		}
	}

	#[Test]
	public function a_currency_formats_its_amounts(): void
	{
		$usdc = Currency::fromArray(get_object_vars(json_decode(Fixtures::model('Currency'))));
		$this->assertSame(6, $usdc->decimals);
		$this->assertSame('10.0042', $usdc->formatAmount('10004200'));
		$this->assertSame('0.000000000000001', $usdc->mainCurrency?->formatAmount('1000'), '18 decimals');
		$this->expectException(ValidationException::class);
		$usdc->formatAmount('1.5');
	}

	// --- H6: exports over any range -------------------------------------------------------------

	/**
	 * A client answering each export with `$answer(from, to)` and recording the windows.
	 *
	 * @param \Closure(string, string): string $answer
	 * @param list<array{0: string, 1: string}>|null $windows
	 */
	private function exportClient(\Closure $answer, ?array &$windows): QBitFlow
	{
		$windows = [];

		return $this->client(new MockHttpClient(static function (RequestInterface $r) use ($answer, &$windows): ResponseInterface {
			parse_str($r->getUri()->getQuery(), $q);
			$windows[] = [$q['from'], $q['to']];

			return MockHttpClient::response(200, $answer($q['from'], $q['to']));
		}));
	}

	#[Test]
	public function a_year_is_exported_in_four_windows(): void
	{
		$client = $this->exportClient(static fn () => Fixtures::list(Fixtures::model('AccountingEvent')), $windows);
		$events = $client->accounting->exportJsonRange('2026-01-01', '2026-12-31');

		$this->assertSame([['2026-01-01', '2026-04-06'], ['2026-04-07', '2026-07-11'], ['2026-07-12', '2026-10-15'], ['2026-10-16', '2026-12-31']], $windows);
		$this->assertCount(4, $events);
		$this->assertContainsOnlyInstancesOf(AccountingEvent::class, $events);
		$this->assertTrue(array_is_list($events));
	}

	#[Test]
	public function ninety_five_days_is_one_request_ninety_six_two(): void
	{
		$client = $this->exportClient(static fn () => '[]', $windows);
		$this->assertSame([], $client->accounting->exportJsonRange('2026-01-01', '2026-04-06'));
		$this->assertSame([['2026-01-01', '2026-04-06']], $windows);

		$client = $this->exportClient(static fn () => '[]', $windows);
		$client->accounting->exportJsonRange('2026-01-01', '2026-04-07');
		$this->assertSame([['2026-01-01', '2026-04-06'], ['2026-04-07', '2026-04-07']], $windows);

		$client = $this->exportClient(static fn () => '[]', $windows);
		$client->accounting->exportJsonRange('2026-03-01', '2026-03-01');
		$this->assertSame([['2026-03-01', '2026-03-01']], $windows, 'one day');

		$client = $this->exportClient(static fn () => '[]', $windows);
		$client->accounting->exportJsonRange('2024-02-01', '2024-06-01');
		$this->assertSame([['2024-02-01', '2024-05-06'], ['2024-05-07', '2024-06-01']], $windows, 'calendar dates (a leap year)');
	}

	#[Test]
	public function the_csv_header_is_kept_once(): void
	{
		$answers = [
			'2026-01-01' => "paymentUuid,type\r\npay@1,payment\r\n",
			'2026-04-07' => "paymentUuid,type\r\n", // only a header: adds nothing
			'2026-07-12' => "paymentUuid,type\r\npay@2,refund\r\npay@3,payment\r\n",
			'2026-10-16' => "paymentUuid,type\r\npay@4,payment",
		];
		$client = $this->exportClient(static fn (string $from) => $answers[$from], $windows);
		$this->assertSame(
			"paymentUuid,type\r\npay@1,payment\r\npay@2,refund\r\npay@3,payment\r\npay@4,payment",
			$client->accounting->exportCsvRange('2026-01-01', '2026-12-31'),
		);
		$this->assertCount(4, $windows);

		// A window without a final line break, then rows: the line ending is the header's.
		$answers = ['2026-01-01' => "h\npay@1", '2026-04-07' => "h\npay@2\n"];
		$client = $this->exportClient(static fn (string $from) => $answers[$from], $windows);
		$this->assertSame("h\npay@1\npay@2\n", $client->accounting->exportCsvRange('2026-01-01', '2026-05-01'));

		// An empty first window: the next one's header.
		$answers = ['2026-01-01' => '', '2026-04-07' => "h\npay@2\n"];
		$client = $this->exportClient(static fn (string $from) => $answers[$from], $windows);
		$this->assertSame("h\npay@2\n", $client->accounting->exportCsvRange('2026-01-01', '2026-05-01'));
	}

	#[Test]
	public function the_range_is_checked_before_any_request(): void
	{
		$client = $this->client();
		$this->assertSame(['to'], $this->failingFields(static fn () => $client->accounting->exportJsonRange('2026-09-30', '2026-09-01')));
		$this->assertSame(['from'], $this->failingFields(static fn () => $client->accounting->exportCsvRange('2026-02-30', '2026-03-01')));
		$this->assertSame(['from', 'to'], $this->failingFields(static fn () => $client->accounting->exportJsonRange('', 'x')));
		$this->assertSame(0, $this->http->count());
	}

	// --- H7: fromEnv ----------------------------------------------------------------------------

	private const ENV = ['QBITFLOW_API_KEY', 'QBITFLOW_BASE_URL', 'QBITFLOW_ON_BEHALF_OF'];

	/**
	 * Runs `$test` with only `$vars` set (in `getenv()`, or `$_ENV` / `$_SERVER`), then restores.
	 *
	 * @param array<string,string> $vars
	 */
	private function withEnv(array $vars, \Closure $test, string $where = 'getenv'): void
	{
		$saved = [];
		foreach (self::ENV as $name) {
			$saved[$name] = [getenv($name), $_ENV[$name] ?? null, $_SERVER[$name] ?? null];
			putenv($name);
			unset($_ENV[$name], $_SERVER[$name]);
		}
		foreach ($vars as $name => $value) {
			match ($where) {
				'getenv' => putenv($name . '=' . $value),
				'_ENV' => $_ENV[$name] = $value,
				default => $_SERVER[$name] = $value,
			};
		}
		try {
			$test();
		} finally {
			foreach ($saved as $name => [$env, $e, $s]) {
				putenv($env === false ? $name : $name . '=' . $env);
				unset($_ENV[$name], $_SERVER[$name]);
				if ($e !== null) {
					$_ENV[$name] = $e;
				}
				if ($s !== null) {
					$_SERVER[$name] = $s;
				}
			}
		}
	}

	#[Test]
	public function from_env_reads_the_environment(): void
	{
		foreach (['getenv', '_ENV', '_SERVER'] as $where) {
			$this->withEnv([
				'QBITFLOW_API_KEY' => 'sk_test_env',
				'QBITFLOW_BASE_URL' => 'http://localhost:8080/v2/',
				'QBITFLOW_ON_BEHALF_OF' => self::MEMBER_UUID,
			], function () use ($where): void {
				$client = QBitFlow::fromEnv(httpClient: $this->http);
				$this->assertSame('http://localhost:8080/v2', $client->getBaseUrl(), $where);
				$this->assertSame(self::MEMBER_UUID, $client->getOnBehalfOf(), $where);
				$this->assertSame('***_env', $client->__debugInfo()['apiKey'], $where);
			}, $where);
		}
		$this->assertSame(0, $this->http->count(), 'nothing is sent');
	}

	#[Test]
	public function from_env_defaults_and_overrides(): void
	{
		$this->withEnv(['QBITFLOW_API_KEY' => 'sk_test_env', 'QBITFLOW_BASE_URL' => '  '], function (): void {
			$client = QBitFlow::fromEnv(httpClient: $this->http);
			$this->assertSame(QBitFlow::DEFAULT_BASE_URL, $client->getBaseUrl(), 'blank = unset');
			$this->assertNull($client->getOnBehalfOf());
		});
		$this->withEnv(['QBITFLOW_API_KEY' => 'sk_test_env', 'QBITFLOW_BASE_URL' => 'http://localhost:1/v2', 'QBITFLOW_ON_BEHALF_OF' => self::MEMBER_UUID], function (): void {
			$client = QBitFlow::fromEnv(apiKey: 'sk_test_over', baseUrl: 'http://127.0.0.1:9/v2', onBehalfOf: '', httpClient: $this->http);
			$this->assertSame('http://127.0.0.1:9/v2', $client->getBaseUrl());
			$this->assertSame('***over', $client->__debugInfo()['apiKey']);
			$this->assertNull($client->getOnBehalfOf(), "'' = the organization level");
		});
		$this->withEnv(['QBITFLOW_API_KEY' => 'not_a_key'], function (): void {
			$this->assertSame(['apiKey'], $this->failingFields(fn () => QBitFlow::fromEnv(baseUrl: 'http://localhost:1/v2', httpClient: $this->http)));
		});
	}

	#[Test]
	public function from_env_needs_the_api_key(): void
	{
		$this->withEnv(['QBITFLOW_BASE_URL' => 'http://localhost:1/v2'], function (): void {
			try {
				QBitFlow::fromEnv(httpClient: $this->http);
				$this->fail('want a ValidationException');
			} catch (ValidationException $e) {
				$this->assertSame('QBITFLOW_API_KEY', $e->fieldErrors[0]->field);
				$this->assertStringContainsString('QBITFLOW_API_KEY', $e->fieldErrors[0]->message);
			}
			$this->assertSame(['QBITFLOW_API_KEY'], $this->failingFields(fn () => QBitFlow::fromEnv(apiKey: null, httpClient: $this->http)));
		});
		$this->withEnv(['QBITFLOW_API_KEY' => '   '], function (): void {
			$this->assertSame(['QBITFLOW_API_KEY'], $this->failingFields(fn () => QBitFlow::fromEnv(baseUrl: 'http://localhost:1/v2', httpClient: $this->http)));
		});
	}

	// --- H8: placeholders -----------------------------------------------------------------------

	#[Test]
	public function the_placeholders_are_sent_verbatim(): void
	{
		$this->assertSame('{{UUID}}', Placeholders::UUID);
		$this->assertSame('{{TRANSACTION_TYPE}}', Placeholders::TRANSACTION_TYPE);

		$this->http->push('{"link":"https://x","uuid":"pay@1","expiresAt":null}', 201);
		$this->client()->checkoutSessions->createPayment(new \QBitFlow\Params\CreatePaymentSessionParams(
			productName: 'Pro',
			price: 1,
			successUrl: 'https://shop.example.com/ok?session=' . Placeholders::UUID . '&type=' . Placeholders::TRANSACTION_TYPE,
		));
		$body = json_decode((string) $this->http->last()->getBody(), true);
		$this->assertSame('https://shop.example.com/ok?session={{UUID}}&type={{TRANSACTION_TYPE}}', $body['successUrl']);
	}
}
