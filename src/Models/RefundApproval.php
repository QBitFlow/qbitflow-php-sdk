<?php

declare(strict_types=1);

namespace QBitFlow\Models;

use QBitFlow\Support\Cast;

/**
 * A refund's approval in progress.
 */
final readonly class RefundApproval extends Model
{
	/**
	 * `waitingConfirmation` (sent, being confirmed) or `created` (its last attempt failed).
	 */
	public string $status;

	/**
	 * The transfer being confirmed, once known.
	 */
	public ?string $txHash;

	/**
	 * Why the last attempt failed.
	 */
	public ?Attempt $lastAttempt;

	/**
	 * @param array<string,mixed> $data
	 */
	protected function __construct(array $data)
	{
		$this->status = Cast::string($data, 'status');
		$this->txHash = Cast::nullableString($data, 'txHash');
		$this->lastAttempt = Cast::nullableObject($data, 'lastAttempt', Attempt::fromArray(...));
	}
}
