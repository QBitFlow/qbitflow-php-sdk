<?php

declare(strict_types=1);

namespace QBitFlow\Events;

use QBitFlow\Models\WebhookTest;
use QBitFlow\Support\Cast;

/**
 * `webhook.test`: The dashboard's test delivery.
 */
final readonly class WebhookTestEvent extends Event
{
	/** The event's data. */
	public WebhookTest $data;

	/**
	 * @param array<string,mixed> $data
	 */
	protected function __construct(array $data)
	{
		parent::__construct($data);
		$this->data = Cast::object($data, 'data', WebhookTest::fromArray(...));
	}
}
