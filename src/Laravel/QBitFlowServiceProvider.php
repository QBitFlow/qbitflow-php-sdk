<?php

declare(strict_types=1);

namespace QBitFlow\Laravel;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use QBitFlow\Exceptions\ValidationException;
use QBitFlow\Laravel\Console\InstallCommand;
use QBitFlow\Laravel\Console\VerifyCommand;
use QBitFlow\Laravel\Http\Controllers\WebhookController;
use QBitFlow\Laravel\Http\Middleware\VerifyQBitFlowWebhook;
use QBitFlow\QBitFlow;

/**
 * Wires the QBitFlow SDK into a Laravel application.
 *
 * Registered automatically through package discovery. It binds the client as a
 * singleton, publishes the config file, registers the `qbitflow.webhook` middleware
 * alias, and adds the webhook route macros.
 */
final class QBitFlowServiceProvider extends ServiceProvider
{
	public function register(): void
	{
		$this->mergeConfigFrom($this->configPath(), 'qbitflow');

		$this->app->singleton(QBitFlow::class, function ($app): QBitFlow {
			/** @var ConfigRepository $config */
			$config = $app['config'];

			$apiKey = (string) ($config->get('qbitflow.api_key') ?? '');

			if (trim($apiKey) === '') {
				throw new ValidationException(
					'QBitFlow API key is not configured. Set QBITFLOW_API_KEY in your .env file.',
				);
			}

			return new QBitFlow(
				$apiKey,
				$config->get('qbitflow.base_url') ? (string) $config->get('qbitflow.base_url') : null,
				$config->get('qbitflow.timeout') !== null ? (float) $config->get('qbitflow.timeout') : null,
				$config->get('qbitflow.max_retries') !== null ? (int) $config->get('qbitflow.max_retries') : null,
				// Honour a PSR-18 client bound in the container, so an application can add
				// its own proxy, logging or retry middleware — and so tests can swap in a
				// fake without rebuilding the client. Falls back to auto-detection.
				httpClient: $app->bound(ClientInterface::class)
					? $app->make(ClientInterface::class)
					: null,
				requestFactory: $app->bound(RequestFactoryInterface::class)
					? $app->make(RequestFactoryInterface::class)
					: null,
				streamFactory: $app->bound(StreamFactoryInterface::class)
					? $app->make(StreamFactoryInterface::class)
					: null,
			);
		});

		$this->app->alias(QBitFlow::class, 'qbitflow');
	}

	public function boot(): void
	{
		// `runningInConsole()` and `configPath()` come from the full framework; guard them
		// so the provider also works in a bare container.
		if ($this->app instanceof Application && $this->app->runningInConsole()) {
			$this->publishes([
				$this->configPath() => $this->app->configPath('qbitflow.php'),
			], 'qbitflow-config');

			$this->commands([
				InstallCommand::class,
				VerifyCommand::class,
			]);
		}

		$this->registerMiddlewareAlias();
		$this->registerRouteMacros();
	}

	/**
	 * @return list<string>
	 */
	public function provides(): array
	{
		return [QBitFlow::class, 'qbitflow'];
	}

	private function configPath(): string
	{
		return dirname(__DIR__, 2) . '/config/qbitflow.php';
	}

	/**
	 * Expose the signature-verification middleware as `qbitflow.webhook`.
	 */
	private function registerMiddlewareAlias(): void
	{
		if (! $this->app->bound('router')) {
			return;
		}

		$router = $this->app->make('router');

		if ($router instanceof Router) {
			$router->aliasMiddleware('qbitflow.webhook', VerifyQBitFlowWebhook::class);
		}
	}

	/**
	 * Register `Route::qbitflowTransactionWebhook()` and `Route::qbitflowSubscriptionWebhook()`.
	 *
	 * Both register a POST route already wrapped in signature verification. Declare them
	 * in `routes/api.php`, or exclude the paths from CSRF protection if you put them in
	 * `routes/web.php` — QBitFlow does not send a CSRF token.
	 */
	private function registerRouteMacros(): void
	{
		if (! class_exists(Router::class) || Router::hasMacro('qbitflowTransactionWebhook')) {
			return;
		}

		Router::macro('qbitflowTransactionWebhook', function (
			string $uri = 'webhooks/qbitflow/transaction',
			string $name = 'qbitflow.webhook.transaction',
		): Route {
			/** @var Router $this */
			return $this->post($uri, [WebhookController::class, 'transaction'])
				->middleware(VerifyQBitFlowWebhook::class)
				->name($name);
		});

		Router::macro('qbitflowSubscriptionWebhook', function (
			string $uri = 'webhooks/qbitflow/subscription',
			string $name = 'qbitflow.webhook.subscription',
		): Route {
			/** @var Router $this */
			return $this->post($uri, [WebhookController::class, 'subscription'])
				->middleware(VerifyQBitFlowWebhook::class)
				->name($name);
		});
	}
}
