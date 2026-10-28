<?php

declare(strict_types=1);

namespace QBitFlow\Models;

use DateTimeImmutable;
use QBitFlow\Support\Cast;

/**
 * A refund of a payment (`pay@…`) or a subscription bill (`sub-hist@…`): a customer's request
 * the merchant approves or rejects, or a refund the merchant sent from the dashboard (recorded
 * once its transfer confirmed: `approved`, nothing before). Refunds are read only here. It is
 * also the data of the `refund.requested`, `refund.approved` and `refund.rejected` events.
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
	 * Why the refund: the customer's reason, or the merchant's when it started the refund; null
	 * (absent) when it gave none.
	 */
	public ?string $reason;

	/**
	 * `pending` (a customer's request not answered yet), `approved` (its transfer confirmed) or
	 * `rejected` ({@see \QBitFlow\Enums\RefundStatus}).
	 */
	public string $status;

	/**
	 * When it was requested (or prepared by the merchant).
	 */
	public DateTimeImmutable $createdAt;

	/**
	 * The merchant's message to the customer (why it rejected the request, or a note on a refund
	 * it started); null (absent) when none.
	 */
	public ?string $merchantMessage;

	/**
	 * When the refund was answered (rejected, or its transfer confirmed); null while pending.
	 */
	public ?DateTimeImmutable $respondedAt;

	/**
	 * True in test mode.
	 */
	public bool $test;

	/**
	 * The member whose space the refunded transaction is in; null for the organization's own.
	 */
	public ?string $userUuid;

	/**
	 * The refund transfer's transaction, once approved; null before.
	 */
	public ?string $txHash;

	/**
	 * `customer` (requested by the customer, the merchant answers) or `merchant` (sent by the
	 * merchant without a request, from the dashboard: recorded once confirmed, `approved`); see
	 * {@see \QBitFlow\Enums\RefundInitiator}.
	 */
	public string $initiatedBy;

	/**
	 * True when the refunded payment was paid to the organization's wallet, which holds the
	 * seller's funds: the organization decides the refund and sends it from its wallet. Once
	 * sent, it is a negative line of the seller's held funds.
	 */
	public bool $held;

	/**
	 * What the customer paid for the refunded transaction (its amount, fees included, and the
	 * network fee they paid on top), in the currency's units: the base of `refundPercent`.
	 */
	public string $paid;

	/**
	 * The same in the currency's smallest unit, a string of digits.
	 */
	public string $paidMinUnits;

	/**
	 * The same in USD at the refunded transaction's time; null (absent) when unknown (a request
	 * made before it was recorded).
	 */
	public ?string $paidUsd;

	/**
	 * The share of `paid` the refund sends back, a percent string (`"100"`: all of it). A
	 * customer requests `"100"`; the merchant sets it when it approves, or when it starts the refund.
	 */
	public string $refundPercent;

	/**
	 * What the refund sends back: `refundPercent` of `paid`, rounded down to a whole min unit, in
	 * the currency's units.
	 */
	public string $amount;

	/**
	 * The same in the currency's smallest unit, a string of digits.
	 */
	public string $amountMinUnits;

	/**
	 * The same in USD at the refunded transaction's time; null (absent) when unknown.
	 */
	public ?string $amountUsd;

	/**
	 * The currency paid, and sent back.
	 */
	public Currency $currency;

	/**
	 * The refund transfer's transaction (its block, what it cost), once approved; null before.
	 */
	public ?TransferSettlement $settlement;

	/**
	 * The currency's chain.
	 */
	public ?string $chain;

	/**
	 * The refund transfer on the chain's explorer, once sent.
	 */
	public ?string $explorerUrl;

	/**
	 * Where the refund transfer stands while the request is pending and it was sent (approved,
	 * or answered by the merchant's own refund): being confirmed, or its last attempt failed
	 * (API reads only, never in webhooks).
	 */
	public ?RefundApproval $approval;

	/**
	 * The refunded transaction's customer (API reads only).
	 */
	public ?string $customerUuid;

	/**
	 * What the refunded transaction paid for (API reads only).
	 */
	public ?string $productName;

	/**
	 * Names the refunded transaction's customer, even once deleted (API reads only).
	 */
	public ?CustomerSummary $customer;

	/**
	 * @param array<string,mixed> $data
	 */
	protected function __construct(array $data)
	{
		$this->uuid = Cast::string($data, 'uuid');
		$this->txUuid = Cast::string($data, 'txUuid');
		$this->reason = Cast::nullableString($data, 'reason');
		$this->status = Cast::string($data, 'status');
		$this->createdAt = Cast::date($data, 'createdAt');
		$this->merchantMessage = Cast::nullableString($data, 'merchantMessage');
		$this->respondedAt = Cast::nullableDate($data, 'respondedAt');
		$this->test = Cast::bool($data, 'test');
		$this->userUuid = Cast::nullableString($data, 'userUuid');
		$this->txHash = Cast::nullableString($data, 'txHash');
		$this->initiatedBy = Cast::string($data, 'initiatedBy');
		$this->held = Cast::bool($data, 'held');
		$this->paid = Cast::string($data, 'paid');
		$this->paidMinUnits = Cast::string($data, 'paidMinUnits');
		$this->paidUsd = Cast::nullableString($data, 'paidUsd');
		$this->refundPercent = Cast::string($data, 'refundPercent');
		$this->amount = Cast::string($data, 'amount');
		$this->amountMinUnits = Cast::string($data, 'amountMinUnits');
		$this->amountUsd = Cast::nullableString($data, 'amountUsd');
		$this->currency = Cast::object($data, 'currency', Currency::fromArray(...));
		$this->settlement = Cast::nullableObject($data, 'settlement', TransferSettlement::fromArray(...));
		$this->chain = Cast::nullableString($data, 'chain');
		$this->explorerUrl = Cast::nullableString($data, 'explorerUrl');
		$this->approval = Cast::nullableObject($data, 'approval', RefundApproval::fromArray(...));
		$this->customerUuid = Cast::nullableString($data, 'customerUuid');
		$this->productName = Cast::nullableString($data, 'productName');
		$this->customer = Cast::nullableObject($data, 'customer', CustomerSummary::fromArray(...));
	}
}
