<?php

declare(strict_types=1);

namespace QBitFlow\Models;

use QBitFlow\Support\Cast;

/**
 * `subscription.actionRequiredChanged`'s data: the subscription with its new `actionRequired`
 * (null: nothing to do anymore), and the one before. Its `managementPageLink` is where the
 * customer acts.
 */
final readonly class SubscriptionActionRequiredChanged extends Subscription
{
	/**
	 * What its customer had to do before (null: nothing).
	 */
	public ?string $previousActionRequired;

	/**
	 * @param array<string,mixed> $data
	 */
	protected function __construct(array $data)
	{
		parent::__construct($data);
		$this->previousActionRequired = Cast::nullableString($data, 'previousActionRequired');
	}
}
