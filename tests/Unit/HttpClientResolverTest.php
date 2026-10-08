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
	public function it_prefers_guzzle_with_the_timeout_and_no_redirects(): void
	{
		$client = HttpClientResolver::resolve(12.5);

		$this->assertInstanceOf(GuzzleClient::class, $client);
		$this->assertSame(12.5, $client->getConfig('timeout'));
		$this->assertFalse($client->getConfig('http_errors'), 'non-2xx responses are classified by the transport');
		$this->assertFalse($client->getConfig('allow_redirects'), 'a 3xx is a ServerException, never followed with the API key attached');
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
