<?php

declare(strict_types=1);

namespace QBitFlow\Services;

use Generator;
use QBitFlow\Http\Requester;
use QBitFlow\Models\Transaction;
use QBitFlow\Page;
use QBitFlow\Params\TransactionListParams;
use QBitFlow\RequestOptions;

/**
 * Reads the transactions feed (`/transactions`): the one-time payments and the subscriptions'
 * bills together.
 */
final class TransactionsService extends Service
{
	/**
	 * One page of the transactions feed (`GET /transactions`; newest first, page size 20 by
	 * default, at most 100): the one-time payments and the subscriptions' bills together, each
	 * row with its `type` (`payment` or `bill`). It takes the payment lists' filters plus `type`,
	 * `subscriptionUuid` (one subscription's bills) and `refunded`. Open a row's detail with
	 * `payments->get()` or `subscriptions->getBill()`.
	 *
	 * @return Page<Transaction>
	 */
	public function list(?TransactionListParams $params = null, ?RequestOptions $options = null): Page
	{
		$params?->validate();

		return $this->requester->call('GET', '/transactions', Requester::page(Transaction::fromArray(...)), $params?->toQuery() ?? [], options: $options);
	}

	/**
	 * Every row `list()` returns, the pages fetched lazily, filters and page size kept.
	 *
	 * @return Generator<int,Transaction>
	 */
	public function iterate(?TransactionListParams $params = null, ?RequestOptions $options = null): Generator
	{
		$params ??= new TransactionListParams();

		return Requester::walk($params->cursor, fn (?string $cursor): Page => $this->list($params->withCursor($cursor), $options));
	}
}
