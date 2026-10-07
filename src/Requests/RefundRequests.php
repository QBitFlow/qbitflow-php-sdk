<?php

declare(strict_types=1);

namespace QBitFlow\Requests;

use QBitFlow\Dto\RefundEntry;
use QBitFlow\Support\CursorData;

/**
 * Query the refunds raised against your transactions.
 */
final class RefundRequests extends Request
{
	private const BASE_ROUTE = '/transaction/refunds';

	/**
	 * Get the refund attached to a transaction. This endpoint is public.
	 */
	public function getByTransaction(string $transactionUUID): RefundEntry
	{
		$this->requireNonEmpty($transactionUUID, 'Transaction UUID');

		return $this->transport->get(
			self::BASE_ROUTE . '/by-transaction/' . $this->encode($transactionUUID),
			map: self::one(RefundEntry::fromArray(...)),
		);
	}

	/**
	 * Get every active refund for your organization — the ones still awaiting a decision.
	 *
	 * @return list<RefundEntry>
	 */
	public function getAll(): array
	{
		return $this->transport->get(self::BASE_ROUTE . '/all', map: self::list(RefundEntry::fromArray(...)));
	}

	/**
	 * List resolved refunds, one page at a time.
	 *
	 * @return CursorData<RefundEntry>
	 */
	public function getAllInactive(?int $limit = null, ?string $cursor = null): CursorData
	{
		return $this->transport->get(
			self::BASE_ROUTE . '/all/inactive',
			CursorData::queryParams($limit, $cursor),
			map: self::page(RefundEntry::fromArray(...)),
		);
	}
}
