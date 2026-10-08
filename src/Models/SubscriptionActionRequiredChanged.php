<?php

declare(strict_types=1);

namespace QBitFlow\Models;

use QBitFlow\Support\Cast;

/**
 * `subscription.actionRequiredChanged`'s data.
 */
final readonly class SubscriptionActionRequiredChanged extends Subscription
{
	/**
	 * What its customer had to do before (null = nothing).
	 */
	public ?string $previousActionRequired;

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
		$this->previousActionRequired = Cast::nullableString($data, 'previousActionRequired');
		$this->managementPageLink = Cast::nullableString($data, 'managementPageLink');
	}
}
