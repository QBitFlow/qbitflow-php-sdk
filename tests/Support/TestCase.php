<?php

declare(strict_types=1);

namespace QBitFlow\Tests\Support;

use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase as BaseTestCase;
use QBitFlow\Http\Transport;
use QBitFlow\QBitFlow;

/**
 * Base test case wiring the SDK to a {@see MockHttpClient} so that no test touches the
 * network, and retry backoff never actually sleeps.
 */
abstract class TestCase extends BaseTestCase
{
	protected MockHttpClient $http;

	/** Seconds each retry would have slept, recorded instead of waited. */
	protected array $sleeps = [];

	protected function setUp(): void
	{
		parent::setUp();

		$this->http = new MockHttpClient();
		$this->sleeps = [];
	}

	/**
	 * A client wired to the mock transport.
	 *
	 * Retries default to 0 so that tests never spend real time in backoff; retry
	 * behaviour itself is covered against {@see TestCase::transport()}, whose sleeps are
	 * recorded rather than performed.
	 */
	protected function client(string $apiKey = 'test-api-key', int $maxRetries = 0): QBitFlow
	{
		$factory = new Psr17Factory();

		return new QBitFlow(
			$apiKey,
			maxRetries: $maxRetries,
			httpClient: $this->http,
			requestFactory: $factory,
			streamFactory: $factory,
		);
	}

	/** A transport wired to the mock client, with sleeps recorded rather than performed. */
	protected function transport(int $maxRetries = 3, string $apiKey = 'test-api-key'): Transport
	{
		$factory = new Psr17Factory();

		return new Transport(
			$apiKey,
			'https://api.qbitflow.app/v1',
			30.0,
			$maxRetries,
			[],
			$this->http,
			$factory,
			$factory,
			function (float $seconds): void {
				$this->sleeps[] = $seconds;
			},
		);
	}
}
