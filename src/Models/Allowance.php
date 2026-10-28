<?php

declare(strict_types=1);

namespace QBitFlow\Models;

use QBitFlow\Support\Cast;

/**
 * What a subscriber's wallet still lets QBitFlow bill for their subscription (the allowance left
 * on-chain), in the subscription's token, how many bills that covers at today's price, and the
 * most one bill may charge, as they signed it. `amount`, `amountMinUnits` and `amountUsd` (its
 * value at today's price; null without one) come from {@see TokenAmount}.
 */
final readonly class Allowance extends TokenAmount
{
	/**
	 * How many whole bills the allowance still covers at today's price (rounded down, before each
	 * bill's network fee). 0 is a value: the customer must top it up (`actionRequired`
	 * `topUpAllowance`) before the next bill. Null without a price.
	 */
	public ?int $remainingPeriods;

	/**
	 * The most one bill may charge, as the subscriber signed it, in the token's units: a bill
	 * above it waits for a new maximum (`actionRequired` `raiseMaximum`). Null when none is recorded.
	 */
	public ?string $maxPerPeriod;

	/**
	 * The same as an on-chain integer.
	 */
	public ?string $maxPerPeriodMinUnits;

	/**
	 * @param array<string,mixed> $data
	 */
	protected function __construct(array $data)
	{
		parent::__construct($data);
		$this->remainingPeriods = Cast::nullableUint($data, 'remainingPeriods');
		$this->maxPerPeriod = Cast::nullableString($data, 'maxPerPeriod');
		$this->maxPerPeriodMinUnits = Cast::nullableString($data, 'maxPerPeriodMinUnits');
	}
}
