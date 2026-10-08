<?php

declare(strict_types=1);

namespace QBitFlow\Models;

use DateTimeImmutable;
use QBitFlow\Support\Cast;

/**
 * `subscription.billingFailed`'s data: a bill attempt failed.
 */
final readonly class SubscriptionBillingFailed extends Subscription
{
	/**
	 * `insufficientBalance`, `allowanceExhausted`, `approvalRevoked`, `maxAmountExceeded` or `other` ({@see \QBitFlow\Enums\BillingFailureReason}).
	 */
	public string $reason;

	/**
	 * The charge's error code (e.g. `insufficient_funds`).
	 */
	public ?string $failureCode;

	/**
	 * The bill that failed (`sub-hist@…`: its id once paid).
	 */
	public string $billUuid;

	/**
	 * The bill, in USD (a decimal string, unlike SubscriptionUpcomingBill's).
	 */
	public string $amountUsd;

	/**
	 * This bill's failed attempts so far, this one included.
	 */
	public int $attempt;

	/**
	 * The attempts left.
	 */
	public int $remainingAttempts;

	/**
	 * When the bill is tried again at the latest.
	 */
	public ?DateTimeImmutable $nextAttemptAt;

	/**
	 * The subscription's management page, where its customer fixes it.
	 */
	public ?string $managementPageLink;

	/**
	 * @param array<string,mixed> $data
	 */
	protected function __construct(array $data)
	{
		parent::__construct($data);
		$this->reason = Cast::string($data, 'reason');
		$this->failureCode = Cast::nullableString($data, 'failureCode');
		$this->billUuid = Cast::string($data, 'billUuid');
		$this->amountUsd = Cast::string($data, 'amountUsd');
		$this->attempt = Cast::uint($data, 'attempt', 65535);
		$this->remainingAttempts = Cast::uint($data, 'remainingAttempts', 65535);
		$this->nextAttemptAt = Cast::nullableDate($data, 'nextAttemptAt');
		$this->managementPageLink = Cast::nullableString($data, 'managementPageLink');
	}
}
