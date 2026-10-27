<?php

declare(strict_types=1);

namespace QBitFlow\Tests\Support;

/**
 * Stands for Guzzle 6's `GuzzleHttp\Client`: constructed with an options array, but not a PSR-18
 * client (Guzzle implements `Psr\Http\Client\ClientInterface` from 7.0).
 */
final class LegacyGuzzleClient
{
	/** @param array<string,mixed> $config */
	public function __construct(public readonly array $config = [])
	{
	}
}
