<?php

declare(strict_types=1);

namespace QBitFlow\Models;

use DateTimeImmutable;
use QBitFlow\Support\Cast;

/**
 * A payment's or bill's refund, as the payment shows it (`refunds->get()` has the rest of the
 * refund with the same id).
 */
final readonly class RefundSummary extends Model
{
	/**
	 * The refund (`refund@…`).
	 */
	public string $uuid;

	/**
	 * `pending`, `approved` or `rejected` ({@see \QBitFlow\Enums\RefundStatus}).
	 */
	public string $status;

	/**
	 * `customer` (a request) or `merchant` ({@see \QBitFlow\Enums\RefundInitiator}).
	 */
	public string $initiatedBy;

	/**
	 * The share of what the customer paid it sends back, a percent string (`"100"`: all of it).
	 */
	public string $refundPercent;

	/**
	 * What it sends back, in the currency's units (a decimal string); null (absent) when its
	 * currency isn't known (a refund requested before refunds named it).
	 */
	public ?string $amount;

	/**
	 * The same in the currency's smallest unit (a string of digits).
	 */
	public string $amountMinUnits;

	/**
	 * The same in USD, at the payment's time (a decimal string); null (absent) when unknown.
	 */
	public ?string $amountUsd;

	/**
	 * When it was requested or prepared.
	 */
	public DateTimeImmutable $createdAt;

	/**
	 * When it was approved (confirmed) or rejected; null while pending.
	 */
	public ?DateTimeImmutable $respondedAt;

	/**
	 * @param array<string,mixed> $data
	 */
	protected function __construct(array $data)
	{
		$this->uuid = Cast::string($data, 'uuid');
		$this->status = Cast::string($data, 'status');
		$this->initiatedBy = Cast::string($data, 'initiatedBy');
		$this->refundPercent = Cast::string($data, 'refundPercent');
		$this->amount = Cast::nullableString($data, 'amount');
		$this->amountMinUnits = Cast::string($data, 'amountMinUnits');
		$this->amountUsd = Cast::nullableString($data, 'amountUsd');
		$this->createdAt = Cast::date($data, 'createdAt');
		$this->respondedAt = Cast::nullableDate($data, 'respondedAt');
	}
}
