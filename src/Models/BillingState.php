<?php

declare(strict_types=1);

namespace QBitFlow\Models;

use DateTimeImmutable;
use QBitFlow\Support\Cast;

/**
 * A test billing run's state (`subscriptions->executeTestBilling()`).
 */
final readonly class BillingState extends Model
{
	/**
	 * The bill's id, bare (its history entry is `sub-hist@<billUuid>`).
	 */
	public string $billUuid;

	/**
	 * `running`, `waiting`, `pending` or `done` ({@see \QBitFlow\Enums\BillingStage}).
	 */
	public string $stage;

	/**
	 * How it ended, once done ({@see \QBitFlow\Enums\BillingOutcome}).
	 */
	public ?string $outcome;

	/**
	 * The failed attempts so far.
	 */
	public int $attempts;

	/**
	 * When it tries again at the latest, while waiting or pending.
	 */
	public ?DateTimeImmutable $nextAttempt;

	/**
	 * The charge's transaction, once paid.
	 */
	public ?string $txHash;

	/**
	 * The last failure's code (e.g. `insufficient_allowance`).
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
		$this->nextAttempt = Cast::nullableDate($data, 'nextAttempt');
		$this->txHash = Cast::nullableString($data, 'txHash');
		$this->failureCode = Cast::nullableString($data, 'failureCode');
	}
}
