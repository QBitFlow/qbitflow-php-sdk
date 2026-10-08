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
 * Reads the failed payment attempts (`/transaction/failures`).
 */
final class FailuresService extends Service
{
	/**
	 * One page of the failed payment attempts (`GET /transaction/failures`; newest first, page
	 * size 10 by default, at most 50): failed attempts never moved money.
	 *
	 * @return Page<Failure>
	 */
	public function list(?FailureListParams $params = null, ?RequestOptions $options = null): Page
	{
		$params?->validate();

		return $this->requester->call('GET', '/transaction/failures', Requester::page(Failure::fromArray(...)), $params?->toQuery() ?? [], options: $options);
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
