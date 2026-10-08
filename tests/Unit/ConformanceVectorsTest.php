<?php

declare(strict_types=1);

namespace QBitFlow\Tests\Unit;

use DateTimeImmutable;
use DateTimeInterface;
use Generator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use QBitFlow\Events\Event;
use QBitFlow\Events\UnknownEvent;
use QBitFlow\Exceptions\FieldError;
use QBitFlow\Exceptions\QBitFlowException;
use QBitFlow\Exceptions\ServerException;
use QBitFlow\Exceptions\ValidationException;
use QBitFlow\Exceptions\WebhookSignatureException;
use QBitFlow\Models\Duration;
use QBitFlow\Models\SubscriptionSessionData;
use QBitFlow\Params\CheckoutFees;
use QBitFlow\Params\FeeItem;
use QBitFlow\Params\SubscriptionTermsParams;
use QBitFlow\QBitFlow;
use QBitFlow\RequestOptions;
use QBitFlow\Support\Time;
use QBitFlow\Tests\Support\MockHttpClient;
use QBitFlow\Tests\Support\TestCase;
use QBitFlow\Webhooks\Webhook;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use stdClass;

/**
 * Runs the cross-SDK conformance vectors the Go reference generated
 * (`.claude/cross-sdk-checks/v3/vectors`, see its README): signature, events, validation,
 * decoding and request building. Skipped when the vectors are not present (set
 * QBITFLOW_VECTORS_DIR to run them from elsewhere).
 */
final class ConformanceVectorsTest extends TestCase
{
	private const CONFORMANCE_KEY = 'sk_test_conformance';

	private static function dir(): ?string
	{
		$dir = getenv('QBITFLOW_VECTORS_DIR');
		if (! is_string($dir) || $dir === '') {
			$dir = dirname(__DIR__, 3) . '/.claude/cross-sdk-checks/v3/vectors';
		}

		return is_dir($dir) ? $dir : null;
	}

	/** @return array<string,array{0: array<string,mixed>}> */
	private static function cases(string $file): array
	{
		$dir = self::dir();
		if ($dir === null) {
			return ['vectors not available' => [[]]];
		}
		$data = json_decode((string) file_get_contents($dir . '/' . $file), true, 512, JSON_THROW_ON_ERROR);
		$out = [];
		foreach ($data['cases'] as $case) {
			$out[$case['name']] = [$case];
		}

		return $out;
	}

	public static function signatureCases(): array
	{
		return self::cases('signature.json');
	}

	public static function eventCases(): array
	{
		return self::cases('events.json');
	}

	public static function validationCases(): array
	{
		return self::cases('validation.json');
	}

	public static function decodingCases(): array
	{
		return self::cases('decoding.json');
	}

	public static function requestCases(): array
	{
		return self::cases('requests.json');
	}

	/** @param array<string,mixed> $case */
	#[Test]
	#[DataProvider('signatureCases')]
	public function signature(array $case): void
	{
		$this->requireVectors($case);
		$body = isset($case['rawBodyBase64']) ? base64_decode($case['rawBodyBase64'], true) : $case['rawBody'];
		$now = $case['now'];

		try {
			Webhook::verify($body, $case['header'], $case['secret'], $case['tolerance'], static fn (): int => $now);
			$got = 'ok';
		} catch (WebhookSignatureException $e) {
			$this->assertSame(0, $e->status);
			$got = $e->reason;
		} catch (ValidationException) {
			$got = 'validationError';
		}

		$this->assertSame($case['expect'], $got);
	}

	/** @param array<string,mixed> $case */
	#[Test]
	#[DataProvider('eventCases')]
	public function events(array $case): void
	{
		$this->requireVectors($case);
		$expect = $case['expect'];

		try {
			$event = Webhook::parseEvent($case['rawBody']);
		} catch (ValidationException $e) {
			$this->assertFalse($expect['ok'], 'unexpected ValidationException: ' . $e->getMessage());
			$this->assertSame('ValidationError', $expect['errorClass']);

			return;
		}

		$this->assertTrue($expect['ok'], 'parseEvent accepted a body Go refuses at stage ' . ($expect['stage'] ?? ''));
		$this->assertSame($expect['id'], $event->id);
		$this->assertSame($expect['type'], $event->type);
		$this->assertSame($expect['test'], $event->test);
		$this->assertSame($expect['userUuid'], $event->userUuid ?? '');
		$this->assertSame($expect['known'], ! $event instanceof UnknownEvent);
		if (isset($expect['dataShape'])) {
			$this->assertSame($expect['dataShape'], $event->data instanceof SubscriptionSessionData ? 'subscription' : 'payment');
		}

		$this->checkProbes($event, $case['probes'] ?? []);
	}

	/** @param array<string,mixed> $case */
	#[Test]
	#[DataProvider('validationCases')]
	public function validation(array $case): void
	{
		$this->requireVectors($case);
		$http = MockHttpClient::static(200, '{}');
		$expect = $case['expect'];

		try {
			$this->invoke($http, $case);
			$error = null;
		} catch (QBitFlowException $e) {
			$error = $e;
		}

		if ($expect === 'ok') {
			$this->assertSame(1, $http->count(), 'the request must be sent' . ($error !== null ? ': ' . $error->getMessage() : ''));

			return;
		}

		$this->assertSame(0, $http->count(), 'nothing may be sent');
		if (isset($expect['errorClass'])) {
			$this->assertInstanceOf(WebhookSignatureException::class, $error);
			$this->assertSame($expect['reason'], $error->reason);

			return;
		}

		$this->assertInstanceOf(ValidationException::class, $error, 'want a ValidationException');
		$this->assertSame(0, $error->status);
		$this->assertSame('', $error->apiCode);
		$fields = array_values(array_unique(array_map(static fn (FieldError $f): string => $f->field, $error->fieldErrors)));
		sort($fields);
		$want = $expect['fields'];
		sort($want);
		$this->assertSame($want, $fields, $error->getMessage());
	}

	/** @param array<string,mixed> $case */
	#[Test]
	#[DataProvider('decodingCases')]
	public function decoding(array $case): void
	{
		$this->requireVectors($case);
		$http = MockHttpClient::static($case['status'], $case['body']);

		try {
			$result = $this->invoke($http, $case);
		} catch (ServerException $e) {
			$this->assertSame('serverError', $case['expect'], 'unexpected ServerException: ' . $e->getMessage());

			return;
		}

		$this->assertSame('ok', $case['expect'], 'decoded a body Go refuses');
		$this->checkProbes($result, $case['probes'] ?? []);
	}

	/** @param array<string,mixed> $case */
	#[Test]
	#[DataProvider('requestCases')]
	public function requests(array $case): void
	{
		$this->requireVectors($case);
		$http = MockHttpClient::static(200, '{}');

		try {
			$this->invoke($http, $case);
		} catch (ServerException) {
			// The stub's answer may not fit the method's model: only the request matters here.
		}

		$this->assertSame(1, $http->count(), 'one request');
		$want = $case['request'];
		$request = $http->last();

		$this->assertSame($want['verb'], $request->getMethod());

		$rawPath = $request->getUri()->getPath();
		$this->assertStringStartsWith('/v2/', $rawPath);
		$raw = explode('/', substr($rawPath, 4));
		foreach ($raw as $segment) {
			$this->assertNotContains($segment, ['.', '..'], 'a dot segment must be escaped');
		}
		$this->assertSame($want['pathSegments'], array_map('rawurldecode', $raw));

		$rawQuery = $request->getUri()->getQuery();
		$this->assertStringNotContainsString('+', $rawQuery, 'a literal + must be %2B');
		$this->assertSame(self::normalizeQuery($want['query']), self::normalizeQuery(self::parseQuery($rawQuery)));

		foreach ($want['headers'] as $name => $value) {
			$got = $request->hasHeader($name) ? $request->getHeaderLine($name) : null;
			if ($name === 'X-API-Key') {
				$this->assertSame(self::CONFORMANCE_KEY, $got);
			} elseif ($value === '<uuid-v4>') {
				$this->assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', (string) $got);
			} else {
				$this->assertSame($value, $got, $name);
			}
		}

		$body = (string) $request->getBody();
		if ($want['body'] === null) {
			$this->assertSame('', $body);
		} else {
			$this->assertEqualsCanonicalizing(
				self::canonicalJson($want['body']),
				self::canonicalJson(json_decode($body, true, 512, JSON_THROW_ON_ERROR)),
				$body,
			);
		}
	}

	/** @param array<string,mixed> $case */
	private function requireVectors(array $case): void
	{
		if ($case === []) {
			$this->markTestSkipped('conformance vectors not found (set QBITFLOW_VECTORS_DIR)');
		}
	}

	/**
	 * Calls a vector's method on a client built on `$http`.
	 *
	 * @param array<string,mixed> $case
	 */
	private function invoke(MockHttpClient $http, array $case): mixed
	{
		$transport = new \QBitFlow\Http\Transport(
			self::CONFORMANCE_KEY,
			'https://api.test/v2',
			30.0,
			0,
			$http,
			new \Nyholm\Psr7\Factory\Psr17Factory(),
			new \Nyholm\Psr7\Factory\Psr17Factory(),
			static function (float $s): void {
			},
		);
		$client = QBitFlow::fromTransport($transport);
		if (isset($case['client']['onBehalfOf'])) {
			$client = $client->onBehalfOf($case['client']['onBehalfOf']);
		}

		$parts = explode('.', $case['method']);
		$methodName = array_pop($parts);
		$target = $client;
		foreach ($parts as $part) {
			$target = $target->{$part};
		}

		$args = $case['args'] === [] ? [] : $case['args'];
		$call = [];
		foreach ((new ReflectionMethod($target, $methodName))->getParameters() as $param) {
			$call[$param->getName()] = $this->argument($param, $args, $case['options'] ?? null);
		}

		return $target->{$methodName}(...$call);
	}

	/**
	 * @param array<string,mixed>      $args
	 * @param array<string,mixed>|null $options
	 */
	private function argument(ReflectionParameter $param, array $args, ?array $options): mixed
	{
		$type = $param->getType();
		$class = $type instanceof ReflectionNamedType && ! $type->isBuiltin() ? $type->getName() : null;

		if ($class === RequestOptions::class) {
			return $options === null ? null : new RequestOptions(
				$options['onBehalfOf'] ?? null,
				$options['idempotencyKey'] ?? null,
				$options['requestId'] ?? null,
			);
		}
		if ($class !== null && str_starts_with($class, 'QBitFlow\\Params\\')) {
			if (! array_key_exists('params', $args)) {
				return $param->isOptional() ? null : $this->build($class, []);
			}

			return $this->build($class, $args['params']);
		}
		if (array_key_exists($param->getName(), $args)) {
			return $args[$param->getName()];
		}

		return $param->isOptional() ? $param->getDefaultValue() : self::zero($param);
	}

	/**
	 * Builds a params class from wire-named values (absent required arguments get their zero value).
	 *
	 * @param class-string        $class
	 * @param array<string,mixed> $values
	 */
	private function build(string $class, array $values): object
	{
		$named = [];
		foreach ((new \ReflectionClass($class))->getConstructor()?->getParameters() ?? [] as $param) {
			$name = $param->getName();
			if (! array_key_exists($name, $values)) {
				if (! $param->isOptional()) {
					$named[$name] = self::zero($param);
				}

				continue;
			}
			$named[$name] = self::convert($param, $values[$name]);
		}

		return new $class(...$named);
	}

	private static function convert(ReflectionParameter $param, mixed $value): mixed
	{
		$type = $param->getType();
		$name = $type instanceof ReflectionNamedType ? $type->getName() : 'mixed';

		return match (true) {
			$value === null => null,
			$name === Duration::class => new Duration($value['value'] ?? 0, $value['unit'] ?? null),
			$name === SubscriptionTermsParams::class => new SubscriptionTermsParams(
				isset($value['frequency']) ? new Duration($value['frequency']['value'] ?? 0, $value['frequency']['unit'] ?? null) : null,
				isset($value['trialPeriod']) ? new Duration($value['trialPeriod']['value'] ?? 0, $value['trialPeriod']['unit'] ?? null) : null,
				$value['minPeriods'] ?? null,
			),
			$name === CheckoutFees::class => new CheckoutFees(
				$value['processingFee'] ?? null,
				array_map(static fn (array $item): FeeItem => new FeeItem(
					$item['label'] ?? '',
					self::amount($item['amountUsd'] ?? 0),
					$item['description'] ?? null,
				), $value['items'] ?? []),
			),
			$name === DateTimeInterface::class => new DateTimeImmutable($value),
			$name === 'float' => match ($value) {
				'NaN' => NAN,
				'Infinity' => INF,
				'-Infinity' => -INF,
				default => (float) $value,
			},
			default => $value,
		};
	}

	/** A vector's amount: a number, a string as typed, or a non-finite float's marker (`NaN`, `Infinity`). */
	private static function amount(int|float|string $value): int|float|string
	{
		return match ($value) {
			'NaN' => NAN,
			'Infinity' => INF,
			'-Infinity' => -INF,
			default => $value,
		};
	}

	private static function zero(ReflectionParameter $param): mixed
	{
		$type = $param->getType();

		return match ($type instanceof ReflectionNamedType ? $type->getName() : '') {
			'string' => '',
			'int' => 0,
			'float' => 0.0,
			'bool' => false,
			'array' => [],
			default => null,
		};
	}

	/**
	 * @param list<array<string,mixed>> $probes
	 */
	private function checkProbes(mixed $result, array $probes): void
	{
		foreach ($probes as $probe) {
			[$found, $actual] = self::read($result, $probe['path']);
			$expected = $probe['value'];
			$label = $probe['path'];

			if (($probe['optional'] ?? false) && ($expected === '' || $expected === null) && (! $found || $actual === null || $actual === '')) {
				continue;
			}
			if (($probe['kind'] ?? '') === 'time') {
				if ($expected === null) {
					$this->assertTrue($actual === null || ($actual instanceof DateTimeInterface && Time::isZero($actual)), $label . ': zero time');
				} else {
					$this->assertInstanceOf(DateTimeInterface::class, $actual, $label);
					$want = new DateTimeImmutable($expected);
					$this->assertSame($want->format('U.u'), $actual->format('U.u'), $label);
				}

				continue;
			}
			$this->assertTrue($found, $label . ': not found');
			$this->assertTrue(self::same($expected, $actual), sprintf('%s: want %s, got %s', $label, json_encode($expected), json_encode(self::plain($actual))));
		}
	}

	/** @return array{0: bool, 1: mixed} */
	private static function read(mixed $value, string $path): array
	{
		if ($path === '') {
			return [true, $value];
		}
		preg_match_all('/([^.\[\]]+)|\[(\d+)\]/', $path, $m, PREG_SET_ORDER);
		foreach ($m as $step) {
			if (isset($step[2]) && $step[2] !== '') {
				if (! is_array($value) || ! array_key_exists((int) $step[2], $value)) {
					return [false, null];
				}
				$value = $value[(int) $step[2]];

				continue;
			}
			$key = $step[1];
			if ($value instanceof Event && $key === 'data' && ! property_exists($value, 'data')) {
				$value = $value->rawData;

				continue;
			}
			if (is_object($value)) {
				if (! property_exists($value, $key)) {
					return [false, null];
				}
				$value = $value->{$key};
			} elseif (is_array($value)) {
				if (! array_key_exists($key, $value)) {
					return [false, null];
				}
				$value = $value[$key];
			} else {
				return [false, null];
			}
		}

		return [true, $value];
	}

	private static function same(mixed $expected, mixed $actual): bool
	{
		$actual = self::plain($actual);
		if (is_array($expected)) {
			if (! is_array($actual)) {
				return false;
			}
			if (array_is_list($expected)) {
				if (count($expected) !== count($actual)) {
					return false;
				}
			}
			foreach ($expected as $key => $item) {
				if (! array_key_exists($key, $actual)) {
					if ($item === null) {
						continue;
					}

					return false;
				}
				if (! self::same($item, $actual[$key])) {
					return false;
				}
			}

			return true;
		}
		if ((is_int($expected) || is_float($expected)) && (is_int($actual) || is_float($actual))) {
			return (float) $expected === (float) $actual;
		}

		return $expected === $actual;
	}

	private static function plain(mixed $value): mixed
	{
		if ($value instanceof DateTimeInterface) {
			return $value->format(DATE_RFC3339_EXTENDED);
		}
		if ($value instanceof stdClass || (is_object($value) && ! $value instanceof Generator)) {
			$value = get_object_vars($value);
		}
		if (is_array($value)) {
			return array_map(self::plain(...), $value);
		}

		return $value;
	}

	/** @return list<array{0: string, 1: string}> */
	private static function parseQuery(string $raw): array
	{
		if ($raw === '') {
			return [];
		}
		$out = [];
		foreach (explode('&', $raw) as $pair) {
			[$k, $v] = array_pad(explode('=', $pair, 2), 2, '');
			$out[] = [urldecode($k), urldecode($v)];
		}

		return $out;
	}

	/**
	 * Sorted pairs, with the creation-window times as instants.
	 *
	 * @param list<array{0: string, 1: string}> $pairs
	 *
	 * @return list<array{0: string, 1: string}>
	 */
	private static function normalizeQuery(array $pairs): array
	{
		$out = [];
		foreach ($pairs as [$key, $value]) {
			if ($key === 'createdAfter' || $key === 'createdBefore') {
				$value = (new DateTimeImmutable($value))->format('U.u');
			}
			$out[] = [$key, $value];
		}
		usort($out, static fn (array $a, array $b): int => [$a[0], $a[1]] <=> [$b[0], $b[1]]);

		return $out;
	}

	private static function canonicalJson(mixed $value): mixed
	{
		if (is_array($value)) {
			if (! array_is_list($value)) {
				ksort($value);
			}

			return array_map(self::canonicalJson(...), $value);
		}
		if (is_int($value)) {
			return (float) $value;
		}

		return $value;
	}
}
