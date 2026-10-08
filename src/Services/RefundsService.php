<?php

declare(strict_types=1);

namespace QBitFlow\Services;

use Generator;
use QBitFlow\Http\Requester;
use QBitFlow\Models\Refund;
use QBitFlow\Page;
use QBitFlow\Params\InitiateRefundParams;
use QBitFlow\Params\RefundListParams;
use QBitFlow\RequestOptions;

/**
 * Lists and initiates refunds (`/transaction/refunds…`).
 */
final class RefundsService extends Service
{
	/**
	 * The refunds awaiting the merchant's answer (`GET /transaction/refunds/all`; not paginated).
	 * From the organization's space the members' refunds are included by default
	 * (`includeMembers` false leaves them out). `limit` and `cursor` are not sent.
	 *
	 * @return list<Refund>
	 */
	public function list(?RefundListParams $params = null, ?RequestOptions $options = null): array
	{
		$params?->validate();

		return $this->requester->call('GET', '/transaction/refunds/all', Requester::list(Refund::fromArray(...)), $params?->toQuery(false) ?? [], options: $options);
	}

	/**
	 * One page of the answered refunds, approved or rejected (`GET /transaction/refunds/all/inactive`;
	 * page size 10 by default, at most 50).
	 *
	 * @return Page<Refund>
	 */
	public function listInactive(?RefundListParams $params = null, ?RequestOptions $options = null): Page
	{
		$params?->validate();

		return $this->requester->call('GET', '/transaction/refunds/all/inactive', Requester::page(Refund::fromArray(...)), $params?->toQuery(true) ?? [], options: $options);
	}

	/**
	 * Every refund `listInactive()` returns, the pages fetched lazily.
	 *
	 * @return Generator<int,Refund>
	 */
	public function iterateInactive(?RefundListParams $params = null, ?RequestOptions $options = null): Generator
	{
		$params ??= new RefundListParams();

		return Requester::walk($params->cursor, fn (?string $cursor): Page => $this->listInactive($params->withCursor($cursor), $options));
	}

	/**
	 * Starts a refund of a payment (`pay@…`) or a bill (`sub-hist@…`) of the space
	 * (`POST /transaction/refunds/initiate`, 201). Sends an Idempotency-Key and is retried on
	 * transient failures. It creates a **pending** refund: no money moves until the merchant
	 * signs the transfer in the dashboard. Errors: 409 `refund_already_exists`
	 * (`details.refundUuid`) or `held_funds_released`.
	 */
	public function initiate(InitiateRefundParams $params, ?RequestOptions $options = null): Refund
	{
		$params->validate();

		return $this->requester->call('POST', '/transaction/refunds/initiate', Requester::one(Refund::fromArray(...)), body: $params->toArray(), idempotent: true, options: $options);
	}
}
