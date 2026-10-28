<?php

declare(strict_types=1);

namespace QBitFlow\Tests\Unit;

use GuzzleHttp\Client as GuzzleClient;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use QBitFlow\Http\HttpClientResolver;
use QBitFlow\QBitFlow;
use QBitFlow\Tests\Support\LegacyGuzzleClient;

final class HttpClientResolverTest extends TestCase
{
	#[Test]
	public function it_prefers_guzzle_with_the_timeout_and_no_redirects(): void
	{
		$client = HttpClientResolver::resolve(12.5);

		$this->assertInstanceOf(GuzzleClient::class, $client);
		$this->assertSame(12.5, $client->getConfig('timeout'));
		$this->assertFalse($client->getConfig('http_errors'), 'non-2xx responses are classified by the transport');
		$this->assertFalse($client->getConfig('allow_redirects'), 'a 3xx is a ServerException, never followed with the API key attached');
	}

	/**
	 * `timeout` bounds the whole attempt, through the last byte of the body: a server that sends
	 * its headers then trickles its body can't hold a call past it (a local `php -S` server).
	 */
	#[Test]
	public function the_timeout_bounds_a_trickled_body(): void
	{
		$dir = sys_get_temp_dir() . '/qbitflow-trickle-' . bin2hex(random_bytes(4));
		mkdir($dir);
		file_put_contents($dir . '/router.php', '<?php header("Content-Type: application/json"); header("Content-Length: 43"); '
			. 'echo "{\\"credential\\":\\"apiKey\\""; flush(); for ($i = 0; $i < 20; $i++) { usleep(250000); echo " "; flush(); } echo "}";');
		$port = 20000 + random_int(0, 20000);
		$server = proc_open([PHP_BINARY, '-S', '127.0.0.1:' . $port, $dir . '/router.php'], [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']], $pipes);
		try {
			$up = false;
			for ($i = 0; $i < 50 && ! $up; $i++) {
				usleep(50000);
				$socket = @fsockopen('127.0.0.1', $port, $errno, $errstr, 0.1);
				if ($socket !== false) {
					fclose($socket);
					$up = true;
				}
			}
			if (! $up) {
				$this->markTestSkipped('could not start a local php -S server');
			}

			$client = new QBitFlow('sk_test_x', baseUrl: 'http://127.0.0.1:' . $port . '/v2', timeout: 0.75, maxRetries: 0);
			$started = microtime(true);
			try {
				$client->me();
				$this->fail('want a NetworkException');
			} catch (\QBitFlow\Exceptions\NetworkException $e) {
				$this->assertStringStartsWith('request timed out after 0.75s', $e->getMessage());
			}
			$this->assertLessThan(3.0, microtime(true) - $started, 'the 5 s body did not hold the call');
		} finally {
			proc_terminate($server);
			proc_close($server);
			@unlink($dir . '/router.php');
			@rmdir($dir);
		}
	}

	/**
	 * Guzzle 6's client is no PSR-18 client: with it installed next to a PSR-18 adapter
	 * (`php-http/guzzle6-adapter`, which satisfies `psr/http-client-implementation`), the
	 * resolver skips it and discovers the adapter, instead of failing with a TypeError.
	 */
	#[Test]
	public function it_skips_a_guzzle_client_that_is_not_psr18(): void
	{
		$client = HttpClientResolver::resolve(5.0, LegacyGuzzleClient::class);

		$this->assertInstanceOf(ClientInterface::class, $client);
		$this->assertNotInstanceOf(LegacyGuzzleClient::class, $client);
	}

	#[Test]
	public function it_discovers_psr17_factories(): void
	{
		$this->assertInstanceOf(RequestFactoryInterface::class, HttpClientResolver::requestFactory());
		$this->assertInstanceOf(StreamFactoryInterface::class, HttpClientResolver::streamFactory());
	}

	#[Test]
	public function a_client_with_only_a_key_discovers_everything(): void
	{
		$client = new QBitFlow('sk_test_key');
		$this->assertSame('https://api.qbitflow.app/v2', $client->getBaseUrl());
	}
}
