<?php

declare(strict_types=1);

namespace QBitFlow\Http;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use QBitFlow\Exceptions\QBitFlowException;

/**
 * Finds the PSR-18 client and PSR-17 factories the SDK will use.
 *
 * `composer.json` requires `psr/http-client-implementation` and
 * `psr/http-factory-implementation`, so Composer refuses to install the SDK into a project
 * with no implementation at all — the error arrives at install time rather than on the
 * first API call. This class then picks one of the common implementations at runtime.
 *
 * Guzzle is preferred because it is the one implementation the SDK can hand a timeout to,
 * and because every Laravel application already has it. Pass your own client to
 * {@see \QBitFlow\QBitFlow::__construct()} to bypass all of this.
 *
 * @internal
 */
final class HttpClientResolver
{
	/**
	 * PSR-17 factory classes that implement both the request and stream factory
	 * interfaces, in preference order. Names, not `::class` constants: each is an optional
	 * package the project may or may not have installed.
	 *
	 * @var list<string>
	 */
	private const FACTORIES = [
		'GuzzleHttp\Psr7\HttpFactory',
		'Nyholm\Psr7\Factory\Psr17Factory',
		'Laminas\Diactoros\RequestFactory',
		'Slim\Psr7\Factory\RequestFactory',
		'Http\Discovery\Psr17Factory',
	];

	/** Symfony's PSR-18 adapter (optional package). */
	private const SYMFONY_PSR18 = 'Symfony\Component\HttpClient\Psr18Client';

	/** Symfony's HTTP client factory (optional package). */
	private const SYMFONY_HTTP_CLIENT = 'Symfony\Component\HttpClient\HttpClient';

	/**
	 * Resolve a PSR-18 client.
	 *
	 * @param float  $timeout Request timeout in seconds, applied where the implementation
	 *                        allows it.
	 * @param string $guzzle  The Guzzle client class (for the SDK's tests).
	 *
	 * @throws QBitFlowException If no known implementation is installed.
	 */
	public static function resolve(float $timeout, string $guzzle = \GuzzleHttp\Client::class): ClientInterface
	{
		// Guzzle 6's client is not a PSR-18 client (Guzzle implements it from 7.0): skipped, so
		// the PSR-18 adapter the project installed next to it is discovered below.
		if (class_exists($guzzle) && is_a($guzzle, ClientInterface::class, true)) {
			return new $guzzle([
				'timeout' => $timeout,
				'connect_timeout' => $timeout,
				// Non-2xx responses are classified by the transport, not thrown by Guzzle.
				'http_errors' => false,
				// The API never redirects. Following one would replay the X-API-Key header
				// to whatever host the redirect names; surfacing the 3xx instead lets the
				// transport report a misconfigured base URL.
				'allow_redirects' => false,
			]);
		}

		$symfony = self::SYMFONY_PSR18;
		if (class_exists($symfony) && is_a($symfony, ClientInterface::class, true)) {
			$create = self::SYMFONY_HTTP_CLIENT . '::create';
			if (! is_callable($create)) {
				return new $symfony();
			}

			// `timeout` is Symfony's idle timeout; `max_duration` bounds the whole request.
			return new $symfony($create([
				'timeout' => $timeout,
				'max_duration' => $timeout,
				'max_redirects' => 0,
			]));
		}

		// Anything else the project happens to have, if php-http/discovery is available.
		if (class_exists(\Http\Discovery\Psr18ClientDiscovery::class)) {
			try {
				return \Http\Discovery\Psr18ClientDiscovery::find();
			} catch (\Throwable) {
				// Fall through to the explanatory error below.
			}
		}

		throw new QBitFlowException(
			'No PSR-18 HTTP client found. Install one (for example "composer require guzzlehttp/guzzle") '
				. 'or pass your own client to the QBitFlow constructor via the $httpClient argument.',
		);
	}

	/**
	 * Resolve a PSR-17 request factory.
	 *
	 * @throws QBitFlowException If no known implementation is installed.
	 */
	public static function requestFactory(): RequestFactoryInterface
	{
		$factory = self::findFactory(RequestFactoryInterface::class);

		if ($factory instanceof RequestFactoryInterface) {
			return $factory;
		}

		throw self::missingPsr17();
	}

	/**
	 * Resolve a PSR-17 stream factory.
	 *
	 * @throws QBitFlowException If no known implementation is installed.
	 */
	public static function streamFactory(): StreamFactoryInterface
	{
		$factory = self::findFactory(StreamFactoryInterface::class);

		if ($factory instanceof StreamFactoryInterface) {
			return $factory;
		}

		throw self::missingPsr17();
	}

	/**
	 * Resolve a PSR-17 response factory (for the webhook router's PSR-7/PSR-15 adapters).
	 *
	 * @throws QBitFlowException If no known implementation is installed.
	 */
	public static function responseFactory(): ResponseFactoryInterface
	{
		$factory = self::findFactory(ResponseFactoryInterface::class);

		if ($factory instanceof ResponseFactoryInterface) {
			return $factory;
		}

		throw self::missingPsr17();
	}

	/**
	 * Instantiate the first known factory implementing the given interface.
	 *
	 * @param class-string $interface
	 */
	private static function findFactory(string $interface): ?object
	{
		foreach (self::FACTORIES as $candidate) {
			if (class_exists($candidate) && is_a($candidate, $interface, true)) {
				return new $candidate();
			}
		}

		// Laminas and Slim split the two factories across separate classes.
		foreach (self::splitFactories($interface) as $candidate) {
			if (class_exists($candidate) && is_a($candidate, $interface, true)) {
				return new $candidate();
			}
		}

		if (class_exists(\Http\Discovery\Psr17FactoryDiscovery::class)) {
			try {
				return match ($interface) {
					RequestFactoryInterface::class => \Http\Discovery\Psr17FactoryDiscovery::findRequestFactory(),
					ResponseFactoryInterface::class => \Http\Discovery\Psr17FactoryDiscovery::findResponseFactory(),
					default => \Http\Discovery\Psr17FactoryDiscovery::findStreamFactory(),
				};
			} catch (\Throwable) {
				return null;
			}
		}

		return null;
	}

	/**
	 * Implementations that ship one class per factory interface.
	 *
	 * @param class-string $interface
	 *
	 * @return list<string> Class names of optional packages.
	 */
	private static function splitFactories(string $interface): array
	{
		return match ($interface) {
			RequestFactoryInterface::class => ['Laminas\Diactoros\RequestFactory', 'Slim\Psr7\Factory\RequestFactory'],
			ResponseFactoryInterface::class => ['Laminas\Diactoros\ResponseFactory', 'Slim\Psr7\Factory\ResponseFactory'],
			default => ['Laminas\Diactoros\StreamFactory', 'Slim\Psr7\Factory\StreamFactory'],
		};
	}

	private static function missingPsr17(): QBitFlowException
	{
		return new QBitFlowException(
			'No PSR-17 HTTP factory found. Install one (for example "composer require nyholm/psr7") '
				. 'or pass your own factories to the QBitFlow constructor via the $requestFactory '
				. 'and $streamFactory arguments (to a webhook router: withResponseFactory()).',
		);
	}
}
