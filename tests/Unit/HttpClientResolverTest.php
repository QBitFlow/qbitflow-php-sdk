<?php

declare(strict_types=1);

namespace QBitFlow\Tests\Unit;

use GuzzleHttp\Client as GuzzleClient;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use QBitFlow\Http\HttpClientResolver;
use QBitFlow\QBitFlow;

final class HttpClientResolverTest extends TestCase
{
	#[Test]
	public function it_prefers_guzzle_and_applies_the_timeout(): void
	{
		$client = HttpClientResolver::resolve(12.5);

		$this->assertInstanceOf(GuzzleClient::class, $client);
		$this->assertSame(12.5, $client->getConfig('timeout'));
		$this->assertFalse(
			$client->getConfig('http_errors'),
			'Non-2xx responses are classified by the transport, not thrown by Guzzle.',
		);
		$this->assertFalse(
			$client->getConfig('allow_redirects'),
			'A 3xx must reach the transport (the API never redirects) instead of being followed with the API key attached.',
		);
	}

	#[Test]
	public function it_discovers_psr17_factories(): void
	{
		$this->assertInstanceOf(RequestFactoryInterface::class, HttpClientResolver::requestFactory());
		$this->assertInstanceOf(StreamFactoryInterface::class, HttpClientResolver::streamFactory());
	}

	#[Test]
	public function a_client_built_with_no_injected_dependencies_discovers_everything_it_needs(): void
	{
		// The zero-config path most consumers take: nothing but an API key. Clear the env
		// override so the assertion on the default base URL is hermetic.
		$previous = getenv('QBITFLOW_BASE_URL');
		putenv('QBITFLOW_BASE_URL');

		try {
			$client = new QBitFlow('test-api-key');
		} finally {
			if (is_string($previous)) {
				putenv('QBITFLOW_BASE_URL=' . $previous);
			}
		}

		$this->assertSame('https://api.qbitflow.app/v1', $client->getBaseUrl());
		$this->assertSame('test-api-key', $client->getApiKey());
	}
}
