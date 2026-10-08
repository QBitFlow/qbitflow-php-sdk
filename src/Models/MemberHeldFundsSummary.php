<?php

declare(strict_types=1);

namespace QBitFlow\Models;

use DateTimeImmutable;
use QBitFlow\Support\Cast;

/**
 * One member's held funds, in the organization's overview.
 */
final readonly class MemberHeldFundsSummary extends Model
{
	/**
	 * The member (a removed member still owed too).
	 */
	public string $userUuid;

	/**
	 * What the organization owes them, in USD.
	 */
	public float $totalAmount;

	/**
	 * Their lines not settled.
	 */
	public int $count;

	/**
	 * Their oldest line not settled.
	 */
	public DateTimeImmutable $oldestAt;

	/**
	 * @param array<string,mixed> $data
	 */
	protected function __construct(array $data)
	{
		$this->userUuid = Cast::string($data, 'userUuid');
		$this->totalAmount = Cast::float($data, 'totalAmount');
		$this->count = Cast::int($data, 'count');
		$this->oldestAt = Cast::date($data, 'oldestAt');
	}
}
