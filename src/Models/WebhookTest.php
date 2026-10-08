<?php

declare(strict_types=1);

namespace QBitFlow\Models;

use QBitFlow\Support\Cast;

/**
 * `webhook.test`'s data (the dashboard's test delivery).
 */
final readonly class WebhookTest extends Model
{
	/**
	 * The endpoint tested.
	 */
	public string $endpointUuid;

	/**
	 * The test's message.
	 */
	public string $message;

	/**
	 * @param array<string,mixed> $data
	 */
	protected function __construct(array $data)
	{
		$this->endpointUuid = Cast::string($data, 'endpointUuid');
		$this->message = Cast::string($data, 'message');
	}
}
