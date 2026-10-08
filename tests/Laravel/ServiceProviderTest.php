<?php

declare(strict_types=1);

namespace QBitFlow\Tests\Laravel;

use Illuminate\Config\Repository as ConfigRepository;
use Illuminate\Container\Container;
use Illuminate\Events\Dispatcher;
use Illuminate\Routing\Router;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use QBitFlow\Exceptions\ValidationException;
use QBitFlow\Laravel\Http\Controllers\WebhookController;
use QBitFlow\Laravel\Http\Middleware\VerifyQBitFlowWebhook;
use QBitFlow\Laravel\QBitFlowServiceProvider;
use QBitFlow\QBitFlow;
use QBitFlow\Tests\Support\MockHttpClient;

final class ServiceProviderTest extends TestCase
{
	private Container $app;

	protected function setUp(): void
	{
		parent::setUp();
		$this->app = new Container();
		Container::setInstance($this->app);
		$this->app->instance('config', new ConfigRepository());
		$this->app->singleton('router', static fn ($app) => new Router(new Dispatcher($app), $app));
	}

	protected function tearDown(): void
	{
		Container::setInstance(null);
		parent::tearDown();
	}

	/** @param array<string,mixed> $config */
	private function register(array $config = []): QBitFlowServiceProvider
	{
		$this->app->make('config')->set('qbitflow', array_merge([
			'api_key' => 'sk_test_key',
			'base_url' => null,
			'timeout' => 30,
			'max_retries' => 3,
			'webhook_secret' => null,
			'webhook_tolerance' => 300,
		], $config));

		$provider = new QBitFlowServiceProvider($this->app);
		$provider->register();

		return $provider;
	}

	#[Test]
	public function it_binds_the_client_as_a_singleton(): void
	{
		$this->register();
		$client = $this->app->make(QBitFlow::class);
		$this->assertInstanceOf(QBitFlow::class, $client);
		$this->assertSame($client, $this->app->make(QBitFlow::class));
		$this->assertSame($client, $this->app->make('qbitflow'));
		$this->assertSame('https://api.qbitflow.app/v2', $client->getBaseUrl());
	}

	#[Test]
	public function it_applies_the_configured_base_url(): void
	{
		$this->register(['base_url' => 'https://sandbox.example.com/v2/']);
		$this->assertSame('https://sandbox.example.com/v2', $this->app->make(QBitFlow::class)->getBaseUrl());
	}

	#[Test]
	public function it_explains_itself_when_the_api_key_is_missing(): void
	{
		$this->register(['api_key' => null]);
		$this->expectException(ValidationException::class);
		$this->expectExceptionMessage('Set QBITFLOW_API_KEY');
		$this->app->make(QBitFlow::class);
	}

	#[Test]
	public function it_refuses_a_key_that_is_not_an_sk_key(): void
	{
		$this->register(['api_key' => 'pk_live_x']);
		$this->expectException(ValidationException::class);
		$this->app->make(QBitFlow::class);
	}

	#[Test]
	public function it_ships_a_config_file_with_every_documented_key(): void
	{
		$config = require dirname(__DIR__, 2) . '/config/qbitflow.php';
		foreach (['api_key', 'base_url', 'timeout', 'max_retries', 'webhook_secret', 'webhook_tolerance'] as $key) {
			$this->assertArrayHasKey($key, $config);
		}
	}

	#[Test]
	public function it_wires_the_webhook_secret_into_the_middleware(): void
	{
		$this->register(['webhook_secret' => 'whsec_x']);
		$this->assertInstanceOf(VerifyQBitFlowWebhook::class, $this->app->make(VerifyQBitFlowWebhook::class));
	}

	#[Test]
	public function it_registers_the_middleware_alias_and_the_route_macro(): void
	{
		$this->register()->boot();
		/** @var Router $router */
		$router = $this->app->make('router');
		$this->assertSame(VerifyQBitFlowWebhook::class, $router->getMiddleware()['qbitflow.webhook'] ?? null);
		$this->assertTrue(Router::hasMacro('qbitflowWebhooks'));

		$route = $router->qbitflowWebhooks('hooks/qbitflow');
		$this->assertSame(['POST'], $route->methods());
		$this->assertSame('hooks/qbitflow', $route->uri());
		$this->assertSame('qbitflow.webhooks', $route->getName());
		$this->assertContains(VerifyQBitFlowWebhook::class, $route->middleware());
		$this->assertSame(WebhookController::class, $route->getAction('controller'));
	}

	#[Test]
	public function it_uses_a_psr18_client_bound_in_the_container(): void
	{
		$http = MockHttpClient::static(200, '{"credential":"apiKey"}');
		$this->app->instance(ClientInterface::class, $http);
		$this->register();

		$this->assertSame('apiKey', $this->app->make(QBitFlow::class)->me()->credential);
		$this->assertSame(1, $http->count());
	}
}
