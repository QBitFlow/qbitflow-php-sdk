<?php

declare(strict_types=1);

namespace QBitFlow\Tests\Support;

use Psr\Http\Client\NetworkExceptionInterface;
use Psr\Http\Message\RequestInterface;
use RuntimeException;

/**
 * Stands in for a transport-level failure inside {@see MockHttpClient}.
 */
final class MockNetworkException extends RuntimeException implements NetworkExceptionInterface
{
	public function getRequest(): RequestInterface
	{
		throw new RuntimeException('Not available in tests.');
	}
}
