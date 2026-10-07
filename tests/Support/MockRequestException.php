<?php

declare(strict_types=1);

namespace QBitFlow\Tests\Support;

use Psr\Http\Client\RequestExceptionInterface;
use Psr\Http\Message\RequestInterface;
use RuntimeException;

/**
 * Stands in for a request that the PSR-18 client refuses to send (malformed URL and the
 * like) inside {@see MockHttpClient}. Unlike a network failure, it is never retried.
 */
final class MockRequestException extends RuntimeException implements RequestExceptionInterface
{
	public function getRequest(): RequestInterface
	{
		throw new RuntimeException('Not available in tests.');
	}
}
