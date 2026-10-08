<?php

declare(strict_types=1);

namespace QBitFlow\Models;

use DateTimeImmutable;
use QBitFlow\Support\Cast;

/**
 * A past-due subscription's retries.
 */
final readonly class DunningStatus extends Model
{
	/**
	 * The bill's failed attempts so far.
	 */
	public int $failedAttempts;

	/**
	 * The attempts left: after the last one fails, it is cancelled.
	 */
	public int $remainingAttempts;

	/**
	 * The bill's first failed attempt.
	 */
	public ?DateTimeImmutable $failingSince;

	/**
	 * When the bill is tried again at the latest.
	 */
	public ?DateTimeImmutable $nextAttemptAt;

	/**
	 * When it started waiting for the customer to raise their maximum.
	 */
	public ?DateTimeImmutable $awaitingMaximumSince;

	/**
	 * @param array<string,mixed> $data
	 */
	protected function __construct(array $data)
	{
		$this->failedAttempts = Cast::uint($data, 'failedAttempts', 65535);
		$this->remainingAttempts = Cast::uint($data, 'remainingAttempts', 65535);
		$this->failingSince = Cast::nullableDate($data, 'failingSince');
		$this->nextAttemptAt = Cast::nullableDate($data, 'nextAttemptAt');
		$this->awaitingMaximumSince = Cast::nullableDate($data, 'awaitingMaximumSince');
	}
}
