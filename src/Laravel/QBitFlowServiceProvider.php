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
use QBitFlow\Exceptions\FieldError;
use QBitFlow\Exceptions\QBitFlowException;
use QBitFlow\Exceptions\ValidationException;
use QBitFlow\Laravel\Console\InstallCommand;
use QBitFlow\Laravel\Console\VerifyCommand;
use QBitFlow\Laravel\Http\Controllers\WebhookController;
use QBitFlow\Laravel\Http\Middleware\VerifyQBitFlowWebhook;
use QBitFlow\QBitFlow;
use QBitFlow\Webhooks\Webhook;
use QBitFlow\Webhooks\WebhookRouter;

/**
 * Wires the QBitFlow SDK into a Laravel application (registered by package discovery).
 *
 * It binds the client as a singleton (also as `qbitflow`), publishes the config file, registers
 * a {@see WebhookRouter} on the webhook secret, the `qbitflow.webhook` middleware alias and the
 * `Route::qbitflowWebhooks()` macro, and the Artisan commands `qbitflow:install` and
 * `qbitflow:verify`.
 */
final class QBitFlowServiceProvider extends ServiceProvider
{
	public function register(): void
	{
		$this->mergeConfigFrom($this->configPath(), 'qbitflow');

		$this->app->singleton(QBitFlow::class, static function ($app): QBitFlow {
			/** @var ConfigRepository $config */
			$config = $app['config'];

			$apiKey = $config->get('qbitflow.api_key');
			if (! is_string($apiKey) || trim($apiKey) === '') {
				throw new ValidationException(
					'QBitFlow API key is not configured. Set QBITFLOW_API_KEY in your .env file.',
					fieldErrors: [new FieldError('apiKey', 'apiKey is required')],
				);
			}
			$baseUrl = $config->get('qbitflow.base_url');

			return new QBitFlow(
				apiKey: $apiKey,
				baseUrl: is_string($baseUrl) && trim($baseUrl) !== '' ? $baseUrl : null,
				timeout: $config->get('qbitflow.timeout') !== null ? (float) $config->get('qbitflow.timeout') : null,
				maxRetries: $config->get('qbitflow.max_retries') !== null ? (int) $config->get('qbitflow.max_retries') : null,
				// A PSR-18 client bound in the container (proxies, logging, a fake in tests) wins
				// over auto-detection.
				httpClient: $app->bound(ClientInterface::class) ? $app->make(ClientInterface::class) : null,
				requestFactory: $app->bound(RequestFactoryInterface::class) ? $app->make(RequestFactoryInterface::class) : null,
				streamFactory: $app->bound(StreamFactoryInterface::class) ? $app->make(StreamFactoryInterface::class) : null,
			);
		});
		$this->app->alias(QBitFlow::class, 'qbitflow');

		$this->app->bind(VerifyQBitFlowWebhook::class, static function ($app): VerifyQBitFlowWebhook {
			/** @var ConfigRepository $config */
			$config = $app['config'];
			$secret = $config->get('qbitflow.webhook_secret');
			$tolerance = $config->get('qbitflow.webhook_tolerance');

			return new VerifyQBitFlowWebhook(
				is_string($secret) && $secret !== '' ? $secret : null,
				is_numeric($tolerance) ? (int) $tolerance : Webhook::DEFAULT_TOLERANCE,
			);
		});

		// A fresh router per resolution, on QBITFLOW_WEBHOOK_SECRET: the WebhookController's,
		// and yours (app(WebhookRouter::class)->on(…)) for a route of your own.
		$this->app->bind(WebhookRouter::class, static function ($app): WebhookRouter {
			/** @var ConfigRepository $config */
			$config = $app['config'];
			$secret = $config->get('qbitflow.webhook_secret');
			$tolerance = $config->get('qbitflow.webhook_tolerance');
			if (! is_string($secret) || $secret === '') {
				throw new QBitFlowException('QBitFlow webhook secret is not configured: set QBITFLOW_WEBHOOK_SECRET (the endpoint\'s whsec_… secret).');
			}

			return new WebhookRouter($secret, is_numeric($tolerance) ? (int) $tolerance : Webhook::DEFAULT_TOLERANCE);
		});
	}

	public function boot(): void
	{
		if ($this->app instanceof Application && $this->app->runningInConsole()) {
			$this->publishes([$this->configPath() => $this->app->configPath('qbitflow.php')], 'qbitflow-config');
			$this->commands([InstallCommand::class, VerifyCommand::class]);
		}

		if ($this->app->bound('router')) {
			$router = $this->app->make('router');
			if ($router instanceof Router) {
				$router->aliasMiddleware('qbitflow.webhook', VerifyQBitFlowWebhook::class);
			}
		}

		if (class_exists(Router::class) && ! Router::hasMacro('qbitflowWebhooks')) {
			/*
			 * Route::qbitflowWebhooks('webhooks/qbitflow') registers the POST route of your webhook
			 * endpoint, verified by the middleware, answered by the WebhookController (which
			 * dispatches the Laravel events). Declare it in routes/api.php, or exclude it from CSRF
			 * protection: QBitFlow sends no CSRF token.
			 */
			Router::macro('qbitflowWebhooks', function (string $uri = 'webhooks/qbitflow', string $name = 'qbitflow.webhooks'): Route {
				/** @var Router $this */
				return $this->post($uri, WebhookController::class)
					->middleware(VerifyQBitFlowWebhook::class)
					->name($name);
			});
		}
	}

	private function configPath(): string
	{
		return dirname(__DIR__, 2) . '/config/qbitflow.php';
	}
}
