<?php

declare(strict_types=1);

namespace QBitFlow\Models;

use DateTimeImmutable;
use QBitFlow\Support\Cast;

/**
 * What the organization owes one member (`members->listHeldFunds()`).
 */
final readonly class MemberHeldFunds extends Model
{
	/**
	 * The member (a removed member still owed too).
	 */
	public string $userUuid;

	/**
	 * What the organization owes them, in USD, a decimal string (`"0"` or negative when refunds
	 * cancel out the payments).
	 */
	public string $totalUsd;

	/**
	 * Their lines not settled: payments held, refunds of them.
	 */
	public int $count;

	/**
	 * Their oldest line not settled: how long the organization has held funds for them.
	 */
	public DateTimeImmutable $oldestAt;

	/**
	 * @param array<string,mixed> $data
	 */
	protected function __construct(array $data)
	{
		$this->userUuid = Cast::string($data, 'userUuid');
		$this->totalUsd = Cast::string($data, 'totalUsd');
		$this->count = Cast::int($data, 'count');
		$this->oldestAt = Cast::date($data, 'oldestAt');
	}
}
