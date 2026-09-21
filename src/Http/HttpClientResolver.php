<?php

declare(strict_types=1);

namespace QBitFlow\Http;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
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
	 * interfaces, in preference order.
	 *
	 * @var list<class-string>
	 */
	private const FACTORIES = [
		\GuzzleHttp\Psr7\HttpFactory::class,
		\Nyholm\Psr7\Factory\Psr17Factory::class,
		\Laminas\Diactoros\RequestFactory::class,
		\Slim\Psr7\Factory\RequestFactory::class,
		\Http\Discovery\Psr17Factory::class,
	];

	/**
	 * Resolve a PSR-18 client.
	 *
	 * @param float $timeout Request timeout in seconds, applied where the implementation
	 *                       allows it.
	 *
	 * @throws QBitFlowException If no known implementation is installed.
	 */
	public static function resolve(float $timeout): ClientInterface
	{
		if (class_exists(\GuzzleHttp\Client::class)) {
			return new \GuzzleHttp\Client([
				'timeout' => $timeout,
				'connect_timeout' => $timeout,
				// Non-2xx responses are classified by the transport, not thrown by Guzzle.
				'http_errors' => false,
			]);
		}

		if (class_exists(\Symfony\Component\HttpClient\Psr18Client::class)) {
			return class_exists(\Symfony\Component\HttpClient\HttpClient::class)
				? new \Symfony\Component\HttpClient\Psr18Client(
					\Symfony\Component\HttpClient\HttpClient::create(['timeout' => $timeout]),
				)
				: new \Symfony\Component\HttpClient\Psr18Client();
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
				return $interface === RequestFactoryInterface::class
					? \Http\Discovery\Psr17FactoryDiscovery::findRequestFactory()
					: \Http\Discovery\Psr17FactoryDiscovery::findStreamFactory();
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
	 * @return list<class-string>
	 */
	private static function splitFactories(string $interface): array
	{
		return $interface === RequestFactoryInterface::class
			? [\Laminas\Diactoros\RequestFactory::class, \Slim\Psr7\Factory\RequestFactory::class]
			: [\Laminas\Diactoros\StreamFactory::class, \Slim\Psr7\Factory\StreamFactory::class];
	}

	private static function missingPsr17(): QBitFlowException
	{
		return new QBitFlowException(
			'No PSR-17 HTTP factory found. Install one (for example "composer require nyholm/psr7") '
				. 'or pass your own factories to the QBitFlow constructor via the $requestFactory '
				. 'and $streamFactory arguments.',
		);
	}
}
