<?php

declare(strict_types=1);

namespace QBitFlow\Dto;

use DateTimeImmutable;
use QBitFlow\Support\Cast;
use QBitFlow\Support\Dto;

/**
 * Funds your organization owes a user who has claimed their account.
 */
final class ClaimFunds extends Dto
{
	public function __construct(
		/** ID of the user owed funds. */
		public readonly int $userId,
		/** Total amount owed, in USD. */
		public readonly float $totalAmountOwed,
		/** Whether the transfer has been funded. */
		public readonly bool $funded,
		/** Whether this is a test-mode entry. */
		public readonly bool $test,
		/** When the entry was created. */
		public readonly DateTimeImmutable $createdAt,
	) {
	}

	/**
	 * @param array<string,mixed> $data
	 */
	public static function fromArray(array $data): self
	{
		return new self(
			Cast::int($data, 'userId'),
			Cast::float($data, 'totalAmountOwed'),
			Cast::bool($data, 'funded'),
			Cast::bool($data, 'test'),
			Cast::date($data, 'createdAt'),
		);
	}
}
