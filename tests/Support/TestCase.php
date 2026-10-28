<?php

declare(strict_types=1);

namespace QBitFlow\Tests\Support;

use Closure;
use DateTimeImmutable;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase as BaseTestCase;
use Psr\Http\Message\RequestInterface;
use QBitFlow\Exceptions\FieldError;
use QBitFlow\Exceptions\ValidationException;
use QBitFlow\Http\Transport;
use QBitFlow\QBitFlow;

/**
 * Wires clients to a {@see MockHttpClient}: no test touches the network, and the retry
 * back-off is recorded instead of slept.
 */
abstract class TestCase extends BaseTestCase
{
	protected const API_KEY = 'sk_test_key_123';

	protected const BASE_URL = 'https://api.test/v2';

	protected const MEMBER_UUID = '019eca82-5680-7b00-8000-0000000000b1';

	protected MockHttpClient $http;

	/** @var list<float> Seconds each retry would have slept. */
	protected array $sleeps = [];

	protected ?DateTimeImmutable $now = null;

	protected function setUp(): void
	{
		parent::setUp();
		$this->http = new MockHttpClient();
		$this->sleeps = [];
		$this->now = null;
	}

	/** A transport on `$http` (default: `$this->http`) recording its sleeps. */
	protected function transport(?MockHttpClient $http = null, int $maxRetries = 3, float $timeout = 30.0): Transport
	{
		if ($http !== null) {
			$this->http = $http;
		}
		$factory = new Psr17Factory();

		return new Transport(
			self::API_KEY,
			self::BASE_URL,
			$timeout,
			$maxRetries,
			$this->http,
			$factory,
			$factory,
			function (float $seconds): void {
				$this->sleeps[] = $seconds;
			},
			fn (): DateTimeImmutable => $this->now ?? new DateTimeImmutable(),
		);
	}

	/** A client on `$http` (default: `$this->http`) recording its sleeps. */
	protected function client(?MockHttpClient $http = null, int $maxRetries = 3, ?string $onBehalfOf = null): QBitFlow
	{
		return QBitFlow::fromTransport($this->transport($http, $maxRetries), $onBehalfOf);
	}

	/** The request's path below the base URL's `/v2`, as sent (escaped). */
	protected static function pathOf(RequestInterface $request): string
	{
		$path = $request->getUri()->getPath();

		return str_starts_with($path, '/v2') ? substr($path, 3) : $path;
	}

	/**
	 * The failing field names of a client-side validation error thrown by `$call`.
	 *
	 * @return list<string>
	 */
	protected function failingFields(Closure $call): array
	{
		try {
			$call();
		} catch (ValidationException $e) {
			$this->assertSame(0, $e->status, 'client-side errors have no status');
			foreach ($e->fieldErrors as $fieldError) {
				$this->assertStringStartsWith($fieldError->field . ' ', $fieldError->message);
			}

			return array_map(static fn (FieldError $f): string => $f->field, $e->fieldErrors);
		}

		return [];
	}
}
