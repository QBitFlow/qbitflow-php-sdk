<?php

declare(strict_types=1);

namespace QBitFlow\Requests;

use QBitFlow\Dto\TransactionStatus;
use QBitFlow\Enums\TransactionType;

/**
 * Check where a transaction stands.
 *
 * To learn that a payment settled, use webhooks (recommended) or poll
 * {@see TransactionStatusRequests::get()}.
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
	 *
	 * @param TransactionType|string $transactionType A `TransactionType` member, or the raw
	 *                                                wire value for a type this SDK does not know.
	 */
	public function get(string $transactionUUID, TransactionType|string $transactionType): TransactionStatus
	{
		return $this->transport->get(
			self::BASE_ROUTE,
			$this->query($transactionUUID, $transactionType),
			map: self::one(TransactionStatus::fromArray(...)),
		);
	}

	/**
	 * @return array{txUUID: string, txType: string}
	 *
	 * @throws \QBitFlow\Exceptions\ValidationException
	 */
	private function query(string $transactionUUID, TransactionType|string $transactionType): array
	{
		$this->requireNonEmpty($transactionUUID, 'Transaction UUID');

		$type = $transactionType instanceof TransactionType ? $transactionType->value : $transactionType;
		$this->requireNonEmpty($type, 'Transaction type');

		return ['txUUID' => $transactionUUID, 'txType' => $type];
	}
}
