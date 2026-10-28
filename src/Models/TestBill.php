<?php

declare(strict_types=1);

namespace QBitFlow\Models;

use DateTimeImmutable;
use QBitFlow\Support\Cast;

/**
 * Where a test bill stands once its attempt settled (`subscriptions->testBill()`): done (its
 * `outcome`), waiting for its next attempt after a failure (`failureCode`, `nextAttemptAt`), or
 * still running after 2 minutes. Its events are a live bill's (`subscription.billed`,
 * `subscription.billingFailed`, `subscription.statusChanged`).
 */
final readonly class TestBill extends Model
{
	/**
	 * The bill (`sub-hist@…`): the id its `subscription.billed` and `subscriptions->getBill()`
	 * carry once it is paid.
	 */
	public string $billUuid;

	/**
	 * `running` (being charged), `waiting` (for its next attempt, its due date or a trial's grace
	 * period), `pending` (over the customer's maximum: waiting for a new one) or `done`
	 * ({@see \QBitFlow\Enums\BillingStage}).
	 */
	public string $stage;

	/**
	 * How it ended, once done: `paid`, `notDue`, `skipped`, `cancelled` or `paused`
	 * ({@see \QBitFlow\Enums\BillingOutcome}); null before.
	 */
	public ?string $outcome;

	/**
	 * Its failed attempts so far.
	 */
	public int $attempts;

	/**
	 * When it is tried again at the latest, while waiting or pending; null otherwise.
	 */
	public ?DateTimeImmutable $nextAttemptAt;

	/**
	 * The charge's transaction, once paid.
	 */
	public ?string $txHash;

	/**
	 * Its last failure's code (e.g. `insufficient_allowance`).
	 */
	public ?string $failureCode;

	/**
	 * @param array<string,mixed> $data
	 */
	protected function __construct(array $data)
	{
		$this->billUuid = Cast::string($data, 'billUuid');
		$this->stage = Cast::string($data, 'stage');
		$this->outcome = Cast::nullableString($data, 'outcome');
		$this->attempts = Cast::uint($data, 'attempts', 65535);
		$this->nextAttemptAt = Cast::nullableDate($data, 'nextAttemptAt');
		$this->txHash = Cast::nullableString($data, 'txHash');
		$this->failureCode = Cast::nullableString($data, 'failureCode');
	}
}
