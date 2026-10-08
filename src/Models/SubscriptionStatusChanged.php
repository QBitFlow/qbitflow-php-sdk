<?php

declare(strict_types=1);

namespace QBitFlow\Models;

use QBitFlow\Support\Cast;

/**
 * `subscription.statusChanged`'s data.
 */
final readonly class SubscriptionStatusChanged extends Subscription
{
	/**
	 * The status before the change.
	 */
	public string $previousStatus;

	/**
	 * The subscription's management page for its customer.
	 */
	public ?string $managementPageLink;

	/**
	 * @param array<string,mixed> $data
	 */
	protected function __construct(array $data)
	{
		parent::__construct($data);
		$this->previousStatus = Cast::string($data, 'previousStatus');
		$this->managementPageLink = Cast::nullableString($data, 'managementPageLink');
	}
}
