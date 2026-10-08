<?php

declare(strict_types=1);

namespace QBitFlow\Models;

use DateTimeImmutable;
use QBitFlow\Support\Cast;

/**
 * A transaction's refund, as its payment or bill shows it.
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
	 * `customer` (a request) or `merchant`.
	 */
	public string $initiatedBy;

	/**
	 * The share of what the customer paid it sends back, in percent.
	 */
	public float $refundPercent;

	/**
	 * What it sends back, in the currency's min units (a decimal string).
	 */
	public string $amountMinUnits;

	/**
	 * The same in USD, at the payment's time.
	 */
	public float $amountUsd;

	/**
	 * When it was requested or started.
	 */
	public DateTimeImmutable $createdAt;

	/**
	 * When it was approved (confirmed) or denied; null while pending.
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
		$this->refundPercent = Cast::float($data, 'refundPercent');
		$this->amountMinUnits = Cast::string($data, 'amountMinUnits');
		$this->amountUsd = Cast::float($data, 'amountUsd');
		$this->createdAt = Cast::date($data, 'createdAt');
		$this->respondedAt = Cast::nullableDate($data, 'respondedAt');
	}
}
