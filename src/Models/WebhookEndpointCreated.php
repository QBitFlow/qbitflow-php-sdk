<?php

declare(strict_types=1);

namespace QBitFlow\Models;

use QBitFlow\Support\Cast;

/**
 * `webhooks->endpoints->create()`'s answer: the endpoint and its secret.
 */
final readonly class WebhookEndpointCreated extends WebhookEndpoint
{
	/**
	 * The `whsec_…` secret that verifies the signature of its deliveries. Shown once: store it.
	 */
	public string $secret;

	/**
	 * @param array<string,mixed> $data
	 */
	protected function __construct(array $data)
	{
		parent::__construct($data);
		$this->secret = Cast::string($data, 'secret');
	}
}
