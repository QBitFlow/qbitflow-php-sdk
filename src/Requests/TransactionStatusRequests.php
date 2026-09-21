<?php

declare(strict_types=1);

namespace QBitFlow\Requests;

use QBitFlow\Dto\TransactionStatus;
use QBitFlow\Enums\TransactionType;

/**
 * Check where a transaction stands.
 *
 * This endpoint is public. The API also exposes a WebSocket at
 * `/transaction/status/ws` with the same query parameters; this SDK does not wrap it,
 * since a long-lived socket has no place in a typical PHP request. Poll this method, or
 * rely on webhooks, which are the recommended way to learn that a payment settled.
 */
final class TransactionStatusRequests extends Request
{
	private const BASE_ROUTE = '/transaction/status';

	/**
	 * Get the current status of a transaction.
	 *
	 * ```php
	 * $status = $client->transactionStatus->get($uuid, TransactionType::ONE_TIME_PAYMENT);
	 *
	 * if ($status->isCompleted()) {
	 *     echo 'Paid, tx hash: ', $status->txHash;
	 * }
	 * ```
	 */
	public function get(string $transactionUUID, TransactionType $transactionType): TransactionStatus
	{
		$this->requireNonEmpty($transactionUUID, 'Transaction UUID');

		return TransactionStatus::fromArray($this->transport->get(self::BASE_ROUTE, [
			'txUUID' => $transactionUUID,
			'txType' => $transactionType->value,
		]));
	}
}
