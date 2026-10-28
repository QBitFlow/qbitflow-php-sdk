<?php

declare(strict_types=1);

namespace QBitFlow\Services;

use Generator;
use QBitFlow\Http\Requester;
use QBitFlow\Models\Failure;
use QBitFlow\Page;
use QBitFlow\Params\FailureListParams;
use QBitFlow\RequestOptions;

/**
 * Reads the failed payment attempts (`/payment-failures`).
 */
final class FailuresService extends Service
{
	/**
	 * One page of the failed payment attempts (`GET /payment-failures`; newest first, page
	 * size 20 by default, at most 100): failed attempts never moved money. It takes the payment
	 * lists' filters plus `kind`, `category` and `subscriptionUuid`.
	 *
	 * @return Page<Failure>
	 */
	public function list(?FailureListParams $params = null, ?RequestOptions $options = null): Page
	{
		$params?->validate();

		return $this->requester->call('GET', '/payment-failures', Requester::page(Failure::fromArray(...)), $params?->toQuery() ?? [], options: $options);
	}

	/**
	 * Every failure `list()` returns, the pages fetched lazily.
	 *
	 * @return Generator<int,Failure>
	 */
	public function iterate(?FailureListParams $params = null, ?RequestOptions $options = null): Generator
	{
		$params ??= new FailureListParams();

		return Requester::walk($params->cursor, fn (?string $cursor): Page => $this->list($params->withCursor($cursor), $options));
	}
}
