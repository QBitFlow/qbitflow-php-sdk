<?php

declare(strict_types=1);

namespace QBitFlow\Models;

use QBitFlow\Support\Cast;

/**
 * What a wallet holds of a currency, read on its chain now. `amount`, `amountMinUnits` and
 * `amountUsd` (its value at today's price; null when QBitFlow has no price it can trust right
 * now) come from {@see TokenAmount}.
 *
 * ```json
 * {"currency": {…}, "amount": "12.5", "amountMinUnits": "12500000", "amountUsd": "12.5"}
 * ```
 */
final readonly class Balance extends TokenAmount
{
	/**
	 * The currency held.
	 */
	public Currency $currency;

	/**
	 * @param array<string,mixed> $data
	 */
	protected function __construct(array $data)
	{
		parent::__construct($data);
		$this->currency = Cast::object($data, 'currency', Currency::fromArray(...));
	}
}
