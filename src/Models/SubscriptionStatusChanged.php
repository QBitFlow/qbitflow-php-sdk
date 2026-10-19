<?php

declare(strict_types=1);

namespace QBitFlow\Models;

use QBitFlow\Support\Cast;

/**
 * `subscription.statusChanged`'s data: the subscription with its new `status`, and the one
 * before. Every status change sends it, once.
 */
final readonly class SubscriptionStatusChanged extends Subscription
{
	/**
	 * The status before the change ({@see \QBitFlow\Enums\SubscriptionStatus}).
	 */
	public string $previousStatus;

	/**
	 * @param array<string,mixed> $data
	 */
	protected function __construct(array $data)
	{
		parent::__construct($data);
		$this->previousStatus = Cast::string($data, 'previousStatus');
	}
}
