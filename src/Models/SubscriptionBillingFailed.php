<?php

declare(strict_types=1);

namespace QBitFlow\Models;

use DateTimeImmutable;
use QBitFlow\Support\Cast;

/**
 * `subscription.billingFailed`'s data: the subscription as a failed bill attempt leaves it, and
 * the failure. Its `managementPageLink` is where the customer fixes it. Sent once per failed
 * attempt (`pastDue`), and once when a bill above the customer's maximum starts waiting for a
 * new one (`maxAmountExceeded`: no attempt spent).
 */
final readonly class SubscriptionBillingFailed extends Subscription
{
	/**
	 * What the customer does: `insufficientBalance` (add funds to the wallet),
	 * `allowanceExhausted` (top up the allowance), `approvalRevoked` (approve the token again),
	 * `maxAmountExceeded` (sign a new maximum) or `other` (the network refused the charge); see
	 * {@see \QBitFlow\Enums\BillingFailureReason}.
	 */
	public string $reason;

	/**
	 * The charge's error code (e.g. `insufficient_funds`, `insufficient_allowance`).
	 */
	public ?string $failureCode;

	/**
	 * The bill that failed: the `sub-hist@…` its `subscription.billed` has once it is paid.
	 */
	public string $billUuid;

	/**
	 * The bill, in USD, a decimal string: its `priceUsd` plus its `fees` lines.
	 */
	public string $amountUsd;

	/**
	 * This bill's failed attempts so far, this one included (`maxAmountExceeded` spends none).
	 */
	public int $attempt;

	/**
	 * The attempts left (retried 1, 3, 5 and 7 days after the first failed attempt). 0: this was
	 * the last; its cancellation follows (`subscription.statusChanged`).
	 */
	public int $remainingAttempts;

	/**
	 * When the bill is tried again at the latest (`maxAmountExceeded`: when it stops waiting for
	 * a new maximum); null after the last attempt.
	 */
	public ?DateTimeImmutable $nextAttemptAt;

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
	}
}
