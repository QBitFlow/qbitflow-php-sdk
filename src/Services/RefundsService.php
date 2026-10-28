<?php

declare(strict_types=1);

namespace QBitFlow\Services;

use Generator;
use QBitFlow\Http\Requester;
use QBitFlow\Models\Refund;
use QBitFlow\Page;
use QBitFlow\Params\RefundListParams;
use QBitFlow\RequestOptions;
use QBitFlow\Support\Validator;

/**
 * Lists and reads refunds (`/refunds…`). Refunds are read only here: a customer's request is
 * answered, and a merchant's own refund sent, from the dashboard.
 */
final class RefundsService extends Service
{
	/**
	 * One page of the space's refunds (`GET /refunds`; newest first, page size 20 by default, at
	 * most 100). `status` keeps the given statuses (`[RefundStatus::PENDING]`: the ones awaiting
	 * an answer; `[]`: every status); `txUuid` the refund of one transaction. From the
	 * organization's space the members' refunds are included by default (`includeMembers` false
	 * leaves them out); `userUuid` reads one member's; `held` filters on held transactions.
	 * {@see iterate()} walks every page.
	 *
	 * @return Page<Refund>
	 */
	public function list(?RefundListParams $params = null, ?RequestOptions $options = null): Page
	{
		$params?->validate();

		return $this->requester->call('GET', '/refunds', Requester::page(Refund::fromArray(...)), $params?->toQuery() ?? [], options: $options);
	}

	/**
	 * Every refund `list()` returns, the pages fetched lazily, filters and page size kept.
	 *
	 * @return Generator<int,Refund>
	 */
	public function iterate(?RefundListParams $params = null, ?RequestOptions $options = null): Generator
	{
		$params ??= new RefundListParams();

		return Requester::walk($params->cursor, fn (?string $cursor): Page => $this->list($params->withCursor($cursor), $options));
	}

	/**
	 * A refund by its id (`GET /refunds/:uuid`; `refund@…` or the bare UUID). Another space's
	 * refund is a {@see \QBitFlow\Exceptions\NotFoundException}.
	 */
	public function get(string $uuid, ?RequestOptions $options = null): Refund
	{
		Validator::pathTxId('uuid', $uuid);

		return $this->requester->call('GET', Requester::path('/refunds/%s', $uuid), Requester::one(Refund::fromArray(...)), options: $options);
	}
}
