<?php

declare(strict_types=1);

namespace QBitFlow\Services;

use QBitFlow\Http\Requester;
use QBitFlow\Models\Currency;
use QBitFlow\Params\CurrencyListParams;
use QBitFlow\RequestOptions;
use QBitFlow\Support\Validator;

/**
 * Reads the currency catalog (`/utils…`, public routes; the key is sent anyway). Rate limited to
 * 60 requests a minute per IP: cache the lists.
 */
final class CurrenciesService extends Service
{
	/**
	 * Every currency checkouts can take (`GET /utils/all-available-currencies`); `test` lists the
	 * testnet currencies, whatever the key's mode.
	 *
	 * @return list<Currency>
	 */
	public function listAvailable(?CurrencyListParams $params = null, ?RequestOptions $options = null): array
	{
		$params?->validate();

		return $this->requester->call('GET', '/utils/all-available-currencies', Requester::list(Currency::fromArray(...)), $params?->toQuery() ?? [], options: $options);
	}

	/**
	 * The chains' main currencies (`GET /utils/all-main-currencies`).
	 *
	 * @return list<Currency>
	 */
	public function listMain(?CurrencyListParams $params = null, ?RequestOptions $options = null): array
	{
		$params?->validate();

		return $this->requester->call('GET', '/utils/all-main-currencies', Requester::list(Currency::fromArray(...)), $params?->toQuery() ?? [], options: $options);
	}

	/** A currency by its id (`GET /utils/currency/id/:id`): resolves the `currencyId` fields. */
	public function get(int $id, ?RequestOptions $options = null): Currency
	{
		if ($id <= 0) {
			throw Validator::fieldError('id', 'must be a currency id (above 0)');
		}

		return $this->requester->call('GET', '/utils/currency/id/' . $id, Requester::one(Currency::fromArray(...)), options: $options);
	}
}
