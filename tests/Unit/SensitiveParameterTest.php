<?php

declare(strict_types=1);

namespace QBitFlow\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use QBitFlow\Exceptions\QBitFlowException;
use QBitFlow\Exceptions\WebhookSignatureException;
use QBitFlow\Http\Transport;
use QBitFlow\Laravel\Http\Middleware\VerifyQBitFlowWebhook;
use QBitFlow\QBitFlow;
use QBitFlow\Services\WebhooksService;
use QBitFlow\Tests\Support\TestCase;
use QBitFlow\Webhooks\Webhook;
use QBitFlow\Webhooks\WebhookRouter;
use ReflectionParameter;
use SensitiveParameter;
use SensitiveParameterValue;

/**
 * The API key and the webhook secret never appear in a stack trace: an exception's trace (logged
 * by frameworks, sent to Sentry or Flare with its frames' arguments) shows them as a
 * `SensitiveParameterValue`. A `WebhookSignatureException` is thrown on every bad delivery, so
 * `Webhook::verify()`'s secret would otherwise reach the logs all the time.
 */
final class SensitiveParameterTest extends TestCase
{
	private const SECRET = 'whsec_must_not_leak';

	/** @return array<string,array{0: class-string, 1: string, 2: string}> */
	public static function sensitiveParameters(): array
	{
		return [
			'QBitFlow::__construct $apiKey' => [QBitFlow::class, '__construct', 'apiKey'],
			'QBitFlow::fromEnv $apiKey' => [QBitFlow::class, 'fromEnv', 'apiKey'],
			'Transport::__construct $apiKey' => [Transport::class, '__construct', 'apiKey'],
			'Webhook::verify $secret' => [Webhook::class, 'verify', 'secret'],
			'Webhook::verifyRequest $secret' => [Webhook::class, 'verifyRequest', 'secret'],
			'Webhook::constructEvent $secret' => [Webhook::class, 'constructEvent', 'secret'],
			'Webhook::sign $secret' => [Webhook::class, 'sign', 'secret'],
			'WebhooksService::verify $secret' => [WebhooksService::class, 'verify', 'secret'],
			'WebhooksService::constructEvent $secret' => [WebhooksService::class, 'constructEvent', 'secret'],
			'WebhooksService::router $secret' => [WebhooksService::class, 'router', 'secret'],
			'WebhookRouter::__construct $secret' => [WebhookRouter::class, '__construct', 'secret'],
			'VerifyQBitFlowWebhook::__construct $secret' => [VerifyQBitFlowWebhook::class, '__construct', 'secret'],
		];
	}

	#[Test]
	#[DataProvider('sensitiveParameters')]
	public function the_parameter_is_marked_sensitive(string $class, string $method, string $parameter): void
	{
		$this->assertNotEmpty(
			(new ReflectionParameter([$class, $method], $parameter))->getAttributes(SensitiveParameter::class),
			sprintf('%s::%s() $%s needs #[\SensitiveParameter]', $class, $method, $parameter),
		);
	}

	#[Test]
	public function a_bad_signature_does_not_put_the_secret_in_the_trace(): void
	{
		$previous = ini_set('zend.exception_ignore_args', '0'); // the default without a php.ini
		try {
			Webhook::verify('{}', 't=1,v1=00', self::SECRET, 300, static fn (): int => 1);
			$this->fail('expected a WebhookSignatureException');
		} catch (WebhookSignatureException $e) {
			$this->assertNotContains(self::SECRET, self::stringArguments($e));
			$this->assertInstanceOf(SensitiveParameterValue::class, $e->getTrace()[0]['args'][2] ?? null);
		} finally {
			ini_set('zend.exception_ignore_args', (string) $previous);
		}
	}

	#[Test]
	public function a_bad_option_does_not_put_the_api_key_in_the_trace(): void
	{
		$previous = ini_set('zend.exception_ignore_args', '0');
		try {
			new QBitFlow('sk_live_must_not_leak', baseUrl: 'not a url');
			$this->fail('expected a ValidationException');
		} catch (QBitFlowException $e) {
			$this->assertNotContains('sk_live_must_not_leak', self::stringArguments($e));
		} finally {
			ini_set('zend.exception_ignore_args', (string) $previous);
		}
	}

	/**
	 * A network failure's trace (the SDK's own frames: error trackers such as Flare and Sentry
	 * record the array arguments of each frame) never carries the X-API-Key header.
	 */
	#[Test]
	public function a_network_error_does_not_put_the_api_key_in_the_trace(): void
	{
		$previous = ini_set('zend.exception_ignore_args', '0');
		try {
			$http = new \QBitFlow\Tests\Support\MockHttpClient(static fn () => new \QBitFlow\Tests\Support\MockNetworkException('Connection refused'));
			$factory = new \Nyholm\Psr7\Factory\Psr17Factory();
			$client = QBitFlow::fromTransport(new Transport('sk_live_must_not_leak', self::BASE_URL, 1.0, 0, $http, $factory, $factory));
			$client->me();
			$this->fail('expected a NetworkException');
		} catch (\QBitFlow\Exceptions\NetworkException $e) {
			$this->assertStringNotContainsString('must_not_leak', print_r(array_map(static fn (array $f): array => $f['args'] ?? [], $e->getTrace()), true));
		} finally {
			ini_set('zend.exception_ignore_args', (string) $previous);
		}
	}

	/**
	 * Debug output of any object that holds the key or a secret (a service, a derived client, the
	 * webhook router, the Laravel middleware) never shows them: `print_r`, `var_dump`,
	 * `var_export`, an array cast (what some error trackers and dumpers walk), `serialize`.
	 */
	#[Test]
	public function no_dump_of_an_object_holding_a_secret_shows_it(): void
	{
		$client = new QBitFlow('sk_live_must_not_leak');
		$objects = [
			'client' => $client,
			'derived client' => $client->onBehalfOf(self::MEMBER_UUID),
			'a service' => $client->products,
			'webhooks service' => $client->webhooks,
			'router' => new WebhookRouter(self::SECRET),
			'middleware' => new VerifyQBitFlowWebhook(self::SECRET),
		];
		$dumps = [
			'print_r' => static fn (object $o): string => print_r($o, true),
			'var_dump' => static function (object $o): string {
				ob_start();
				var_dump($o);

				return (string) ob_get_clean();
			},
			'var_export' => static fn (object $o): string => var_export($o, true),
			'array cast' => static fn (object $o): string => print_r((array) $o, true),
			'serialize' => static function (object $o): string {
				try {
					return serialize($o);
				} catch (\Throwable) {
					return ''; // refusing to serialize is fine
				}
			},
		];
		foreach ($objects as $name => $object) {
			foreach ($dumps as $how => $dump) {
				$out = $dump($object);
				$this->assertStringNotContainsString('must_not_leak', $out, $name . ', ' . $how);
			}
		}
		$requester = (new \ReflectionProperty(QBitFlow::class, 'requester'))->getValue($client);
		$this->assertSame('sk_live_must_not_leak', $requester->transport->apiKey(), 'the key is still what is sent');
	}

	/**
	 * `serialize()` of anything holding the key or a secret throws (a `SensitiveParameterValue`
	 * refuses serialization): a key never lands in a cache, a session or a queued job by accident.
	 */
	#[Test]
	public function an_object_holding_a_secret_cannot_be_serialized(): void
	{
		$client = new QBitFlow('sk_live_must_not_leak');
		$objects = [
			'client' => $client,
			'derived client' => $client->onBehalfOf(self::MEMBER_UUID),
			'a service' => $client->products,
			'webhooks service' => $client->webhooks,
			'router' => new WebhookRouter(self::SECRET),
			'middleware' => new VerifyQBitFlowWebhook(self::SECRET),
		];
		foreach ($objects as $name => $object) {
			try {
				serialize($object);
				$this->fail($name . ' was serialized');
			} catch (\Exception $e) {
				$this->assertStringContainsString('SensitiveParameterValue', $e->getMessage(), $name);
			}
		}
	}

	/**
	 * Every string argument of the exception's stack frames.
	 *
	 * @return list<string>
	 */
	private static function stringArguments(\Throwable $e): array
	{
		$out = [];
		foreach ($e->getTrace() as $frame) {
			foreach ($frame['args'] ?? [] as $arg) {
				if (is_string($arg)) {
					$out[] = $arg;
				}
			}
		}

		return $out;
	}
}
