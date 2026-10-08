<?php

declare(strict_types=1);

namespace QBitFlow\Requests;

use QBitFlow\Dto\Currency;
use QBitFlow\Support\Cast;

/**
 * Look up the cryptocurrencies QBitFlow supports.
 *
 * These endpoints are public. Use them to resolve the currency IDs found in
 * `availableCurrencies` on a session, and in `currencyId` on payments and subscriptions.
 */
final class CurrencyRequests extends Request
{
	private const BASE_ROUTE = '/utils';

	/**
	 * Get every supported currency — native currencies and tokens alike.
	 *
	 * ```php
	 * $currencies = $client->currencies->getAllAvailable();
	 *
	 * $byId = array_column(
	 *     array_map(fn ($c) => ['id' => $c->id, 'currency' => $c], $currencies),
	 *     'currency',
	 *     'id',
	 * );
	 * ```
	 *
	 * @param bool $test Pass true to list test-network currencies.
	 *
	 * @return list<Currency>
	 */
	public function getAllAvailable(bool $test = false): array
	{
		return Cast::listOf(
			$this->transport->get(self::BASE_ROUTE . '/all-available-currencies', ['test' => $test]),
			Currency::fromArray(...),
		);
	}

	/**
	 * Get only the main (native) currencies, one per chain, excluding tokens.
	 *
	 * @param bool $test Pass true to list test-network currencies.
	 *
	 * @return list<Currency>
	 */
	public function getAllMain(bool $test = false): array
	{
		return Cast::listOf(
			$this->transport->get(self::BASE_ROUTE . '/all-main-currencies', ['test' => $test]),
			Currency::fromArray(...),
		);
	}
}
