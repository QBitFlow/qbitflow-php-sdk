<?php

declare(strict_types=1);

namespace QBitFlow\Models;

use QBitFlow\Support\Cast;

/**
 * `subscription.created`'s data (status active, or trial).
 */
final readonly class SubscriptionCreated extends Subscription
{
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
		$this->managementPageLink = Cast::nullableString($data, 'managementPageLink');
	}
}
