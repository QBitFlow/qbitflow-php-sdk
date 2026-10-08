<?php

declare(strict_types=1);

namespace QBitFlow\Models;

use QBitFlow\Support\Cast;

/**
 * `subscription.billed`'s data: the paid bill.
 */
final readonly class SubscriptionBilled extends Bill
{
	/**
	 * The subscription's merchant reference, if it has one.
	 */
	public ?string $subscriptionReference;

	/**
	 * The subscription's status once the bill is paid.
	 */
	public string $subscriptionStatus;

	/**
	 * @param array<string,mixed> $data
	 */
	protected function __construct(array $data)
	{
		parent::__construct($data);
		$this->subscriptionReference = Cast::nullableString($data, 'subscriptionReference');
		$this->subscriptionStatus = Cast::string($data, 'subscriptionStatus');
	}
}
