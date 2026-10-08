<?php

declare(strict_types=1);

namespace QBitFlow\Webhooks;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * A {@see WebhookRouter} as a PSR-15 request handler: mount it on your webhook route (Slim,
 * Mezzio, Symfony through its PSR-7 bridge…). Get one with `$router->psr15()`.
 *
 * ```php
 * $app->post('/webhooks/qbitflow', $router->psr15()); // Mezzio: a RequestHandlerInterface is a route handler
 * ```
 *
 * It answers 405 to anything but a POST, 413 to a body over 1 MiB, else the router's status
 * with a JSON body (see {@see WebhookRouter::handleRequest()}).
 */
final class Psr15WebhookHandler implements RequestHandlerInterface
{
	public function __construct(private readonly WebhookRouter $router)
	{
	}

	public function handle(ServerRequestInterface $request): ResponseInterface
	{
		return $this->router->handleRequest($request);
	}

	/** The router it delegates to. */
	public function router(): WebhookRouter
	{
		return $this->router;
	}
}
