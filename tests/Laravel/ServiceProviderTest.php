<?php

declare(strict_types=1);

namespace QBitFlow\Tests\Laravel;

use Illuminate\Config\Repository as ConfigRepository;
use Illuminate\Container\Container;
use Illuminate\Events\Dispatcher;
use Illuminate\Routing\Router;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use QBitFlow\Exceptions\ValidationException;
use QBitFlow\Laravel\Http\Controllers\WebhookController;
use QBitFlow\Laravel\Http\Middleware\VerifyQBitFlowWebhook;
use QBitFlow\Laravel\QBitFlowServiceProvider;
use QBitFlow\QBitFlow;
use QBitFlow\Tests\Support\MockHttpClient;
use Psr\Http\Client\ClientInterface;

final class ServiceProviderTest extends TestCase
{
	private Container $app;

	protected function setUp(): void
	{
		parent::setUp();

		$this->app = new Container();
		Container::setInstance($this->app);

		$this->app->instance('config', new ConfigRepository());
		$this->app->singleton('router', fn ($app) => new Router(new Dispatcher($app), $app));
	}

	protected function tearDown(): void
	{
		Container::setInstance(null);

		parent::tearDown();
	}

	private function register(array $config = []): QBitFlowServiceProvider
	{
		$this->app->make('config')->set('qbitflow', array_merge([
			'api_key' => 'test-api-key',
			'base_url' => null,
			'timeout' => 30,
			'max_retries' => 3,
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
		$this->assertSame('test-api-key', $client->getApiKey());
		$this->assertSame($client, $this->app->make(QBitFlow::class), 'The client should be shared.');
		$this->assertSame($client, $this->app->make('qbitflow'), 'The short alias resolves the same client.');
	}

	#[Test]
	public function it_applies_the_configured_base_url(): void
	{
		$this->register(['base_url' => 'https://staging.qbitflow.app/v1']);

		$this->assertSame('https://staging.qbitflow.app/v1', $this->app->make(QBitFlow::class)->getBaseUrl());
	}

	#[Test]
	public function it_falls_back_to_the_production_base_url(): void
	{
		$this->register(['base_url' => null]);

		$this->assertSame('https://api.qbitflow.app/v1', $this->app->make(QBitFlow::class)->getBaseUrl());
	}

	#[Test]
	public function it_explains_itself_when_the_api_key_is_missing(): void
	{
		$this->register(['api_key' => null]);

		$this->expectException(ValidationException::class);
		$this->expectExceptionMessage('QBITFLOW_API_KEY');

		$this->app->make(QBitFlow::class);
	}

	#[Test]
	public function it_ships_a_config_file_with_every_documented_key(): void
	{
		$config = require __DIR__ . '/../../config/qbitflow.php';

		$this->assertSame(
			['api_key', 'base_url', 'timeout', 'max_retries'],
			array_keys($config),
		);
	}

	#[Test]
	public function it_registers_the_middleware_alias_and_route_macros(): void
	{
		$provider = $this->register();
		$provider->boot();

		$router = $this->app->make('router');

		$this->assertArrayHasKey('qbitflow.webhook', $router->getMiddleware());
		$this->assertSame(VerifyQBitFlowWebhook::class, $router->getMiddleware()['qbitflow.webhook']);

		$this->assertTrue(Router::hasMacro('qbitflowTransactionWebhook'));
		$this->assertTrue(Router::hasMacro('qbitflowSubscriptionWebhook'));
	}

	#[Test]
	public function the_route_macros_register_verified_post_routes(): void
	{
		$provider = $this->register();
		$provider->boot();

		$router = $this->app->make('router');
		$router->qbitflowTransactionWebhook('hooks/qbitflow/tx');
		$router->qbitflowSubscriptionWebhook();

		$routes = $router->getRoutes();
		// Names are applied after a route is added, so the lookup table needs rebuilding.
		$routes->refreshNameLookups();

		$transaction = $routes->getByName('qbitflow.webhook.transaction');
		$this->assertNotNull($transaction);
		$this->assertSame('hooks/qbitflow/tx', $transaction->uri());
		$this->assertContains('POST', $transaction->methods());
		$this->assertContains(VerifyQBitFlowWebhook::class, $transaction->gatherMiddleware());
		$this->assertSame(WebhookController::class . '@transaction', $transaction->getActionName());

		$subscription = $routes->getByName('qbitflow.webhook.subscription');
		$this->assertNotNull($subscription);
		$this->assertSame('webhooks/qbitflow/subscription', $subscription->uri(), 'Default URI.');
		$this->assertSame(WebhookController::class . '@subscription', $subscription->getActionName());
	}

	#[Test]
	public function it_uses_a_psr18_client_bound_in_the_container(): void
	{
		// Lets an application supply a client carrying its own proxy or logging middleware,
		// and lets tests swap in a fake without rebuilding the SDK client.
		$mock = new MockHttpClient();
		$this->app->instance(ClientInterface::class, $mock);

		$this->register();
		$client = $this->app->make(QBitFlow::class);

		$mock->push([['id' => 1, 'name' => 'Widget', 'description' => 'd', 'price' => 1.0, 'isActive' => true]]);
		$client->products->getAll();

		$this->assertSame(1, $mock->requestCount(), 'The container-bound client should have been used.');
		$this->assertSame('/v1/product/', $mock->lastPath());
	}

	#[Test]
	public function it_falls_back_to_auto_detection_when_nothing_is_bound(): void
	{
		$this->register();

		// Nothing bound, so the SDK discovers Guzzle on its own and still builds.
		$this->assertInstanceOf(QBitFlow::class, $this->app->make(QBitFlow::class));
	}

	#[Test]
	public function it_declares_what_it_provides(): void
	{
		$this->assertSame(
			[QBitFlow::class, 'qbitflow'],
			(new QBitFlowServiceProvider($this->app))->provides(),
		);
	}
}
