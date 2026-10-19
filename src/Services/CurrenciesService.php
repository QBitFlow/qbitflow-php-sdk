<?php

declare(strict_types=1);

namespace QBitFlow\Services;

use QBitFlow\Http\Requester;
use QBitFlow\Models\Currency;
use QBitFlow\Params\CurrencyListParams;
use QBitFlow\RequestOptions;
use QBitFlow\Support\Validator;

/**
 * Reads the currency catalog (`/currencies…`, public routes; the key is sent anyway). Rate
 * limited to 60 requests a minute per IP: cache the list.
 */
final class CurrenciesService extends Service
{
	/**
	 * The currency catalog (`GET /currencies`; a bounded list): the live currencies, or the test
	 * networks' with `test` (whatever the key's mode); `main` keeps only the chains' native coins
	 * (ETH, BASE, SOL).
	 *
	 * @return list<Currency>
	 */
	public function list(?CurrencyListParams $params = null, ?RequestOptions $options = null): array
	{
		$params?->validate();

		return $this->requester->items('/currencies', Currency::fromArray(...), $params?->toQuery() ?? [], $options);
	}

	/**
	 * A currency by its id (`GET /currencies/:id`), e.g. a token's `mainCurrencyId`. Ids are
	 * unique across test and live.
	 */
	public function get(int $id, ?RequestOptions $options = null): Currency
	{
		if ($id <= 0) {
			throw Validator::fieldError('id', 'must be a currency id (above 0)');
		}

		return $this->requester->call('GET', '/currencies/' . $id, Requester::one(Currency::fromArray(...)), options: $options);
	}
}
