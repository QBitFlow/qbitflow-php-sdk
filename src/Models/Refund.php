<?php

declare(strict_types=1);

namespace QBitFlow\Models;

use DateTimeImmutable;
use QBitFlow\Support\Cast;

/**
 * A refund of a payment or a bill.
 */
final readonly class Refund extends Model
{
	/**
	 * The refund's id (`refund@…`).
	 */
	public string $uuid;

	/**
	 * The refunded transaction: a payment (`pay@…`) or a bill (`sub-hist@…`).
	 */
	public string $txUuid;

	/**
	 * The customer's reason, or the merchant's when it started the refund.
	 */
	public string $reason;

	/**
	 * `pending`, `approved` or `rejected` ({@see \QBitFlow\Enums\RefundStatus}).
	 */
	public string $status;

	/**
	 * When it was requested or started.
	 */
	public DateTimeImmutable $createdAt;

	/**
	 * The merchant's message to the customer (`''` until set).
	 */
	public string $merchantMessage;

	/**
	 * When the merchant answered; null while pending.
	 */
	public ?DateTimeImmutable $respondedAt;

	/**
	 * True in test mode.
	 */
	public bool $test;

	/**
	 * The member whose space it is in; null for the organization's own.
	 */
	public ?string $userUuid;

	/**
	 * The refund transfer's hash (`''` until sent).
	 */
	public string $txHash;

	/**
	 * `customer` (a request the merchant answers) or `merchant`.
	 */
	public string $initiatedBy;

	/**
	 * True when the refunded transaction's funds are held by the organization.
	 */
	public bool $held;

	/**
	 * What the customer paid for the refunded transaction, in min units.
	 */
	public string $paidMinUnits;

	/**
	 * The same in USD.
	 */
	public float $paidUsd;

	/**
	 * The share of what the customer paid it sends back, in percent.
	 */
	public float $refundPercent;

	/**
	 * What it sends back, in min units (a decimal string).
	 */
	public string $amountMinUnits;

	/**
	 * The same in USD, at the refunded transaction's time.
	 */
	public float $amountUsd;

	/**
	 * The currency paid, and sent back.
	 */
	public int $currencyId;

	/**
	 * The refund transfer's network fees and block, once approved; null before.
	 */
	public ?TxMetadata $metadata;

	/**
	 * The currency's chain.
	 */
	public ?string $chain;

	/**
	 * The refund transfer on the chain's explorer, once sent.
	 */
	public ?string $explorerUrl;

	/**
	 * The approval being confirmed, or its failed attempt (API reads only).
	 */
	public ?RefundApproval $approval;

	/**
	 * What the refunded transaction paid for (API reads only).
	 */
	public ?string $productName;

	/**
	 * Names the refunded transaction's customer (API reads only).
	 */
	public ?CustomerSummary $customer;

	/**
	 * @param array<string,mixed> $data
	 */
	protected function __construct(array $data)
	{
		$this->uuid = Cast::string($data, 'uuid');
		$this->txUuid = Cast::string($data, 'txUuid');
		$this->reason = Cast::string($data, 'reason');
		$this->status = Cast::string($data, 'status');
		$this->createdAt = Cast::date($data, 'createdAt');
		$this->merchantMessage = Cast::string($data, 'merchantMessage');
		$this->respondedAt = Cast::nullableDate($data, 'respondedAt');
		$this->test = Cast::bool($data, 'test');
		$this->userUuid = Cast::nullableString($data, 'userUuid');
		$this->txHash = Cast::string($data, 'txHash');
		$this->initiatedBy = Cast::string($data, 'initiatedBy');
		$this->held = Cast::bool($data, 'held');
		$this->paidMinUnits = Cast::string($data, 'paidMinUnits');
		$this->paidUsd = Cast::float($data, 'paidUsd');
		$this->refundPercent = Cast::float($data, 'refundPercent');
		$this->amountMinUnits = Cast::string($data, 'amountMinUnits');
		$this->amountUsd = Cast::float($data, 'amountUsd');
		$this->currencyId = Cast::uint($data, 'currencyId');
		$this->metadata = Cast::nullableObject($data, 'metadata', TxMetadata::fromArray(...));
		$this->chain = Cast::nullableString($data, 'chain');
		$this->explorerUrl = Cast::nullableString($data, 'explorerUrl');
		$this->approval = Cast::nullableObject($data, 'approval', RefundApproval::fromArray(...));
		$this->productName = Cast::nullableString($data, 'productName');
		$this->customer = Cast::nullableObject($data, 'customer', CustomerSummary::fromArray(...));
	}
}
