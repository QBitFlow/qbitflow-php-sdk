<?php

declare(strict_types=1);

namespace QBitFlow\Models;

use DateTimeImmutable;
use QBitFlow\Support\Cast;

/**
 * `heldFunds.released`'s data: the transfer paying a member the funds the organization held for
 * them (in the member's space: the envelope's `userUuid`), and the lines it settled.
 */
final readonly class HeldFundsReleased extends Model
{
	/**
	 * The transfer's id (`transfer@…`).
	 */
	public string $uuid;

	/**
	 * When it was recorded.
	 */
	public DateTimeImmutable $createdAt;

	/**
	 * The organization's wallet.
	 */
	public string $from;

	/**
	 * The member's wallet.
	 */
	public string $to;

	/**
	 * What the member received, in the currency's units, exact: the sum of the lines' `owed`.
	 */
	public string $amount;

	/**
	 * The same in the currency's smallest unit.
	 */
	public string $amountMinUnits;

	/**
	 * The same in USD, at the release's price.
	 */
	public string $amountUsd;

	/**
	 * The currency paid in.
	 */
	public Currency $currency;

	/**
	 * The transaction's hash.
	 */
	public string $txHash;

	/**
	 * The chain.
	 */
	public ?string $chain;

	/**
	 * The transaction on the chain's block explorer.
	 */
	public ?string $explorerUrl;

	/**
	 * True in test mode.
	 */
	public bool $test;

	/**
	 * The member paid.
	 */
	public ?string $userUuid;

	/**
	 * True once the recipient received it.
	 */
	public bool $received;

	/**
	 * The transfer's transaction: its block and what it cost its sender.
	 */
	public TransferSettlement $settlement;

	/**
	 * The lines it settled, oldest first: the payments it paid (positive) and the refunds of
	 * them it took off (negative, type `refund`), each with what it counted for (`owed`,
	 * `owedMinUnits`, `owedUsd`).
	 *
	 * @var list<LedgerEntry>
	 */
	public array $ledgers;

	/**
	 * @param array<string,mixed> $data
	 */
	protected function __construct(array $data)
	{
		$this->uuid = Cast::string($data, 'uuid');
		$this->createdAt = Cast::date($data, 'createdAt');
		$this->from = Cast::string($data, 'from');
		$this->to = Cast::string($data, 'to');
		$this->amount = Cast::string($data, 'amount');
		$this->amountMinUnits = Cast::string($data, 'amountMinUnits');
		$this->amountUsd = Cast::string($data, 'amountUsd');
		$this->currency = Cast::object($data, 'currency', Currency::fromArray(...));
		$this->txHash = Cast::string($data, 'txHash');
		$this->chain = Cast::nullableString($data, 'chain');
		$this->explorerUrl = Cast::nullableString($data, 'explorerUrl');
		$this->test = Cast::bool($data, 'test');
		$this->userUuid = Cast::nullableString($data, 'userUuid');
		$this->received = Cast::bool($data, 'received');
		$this->settlement = Cast::object($data, 'settlement', TransferSettlement::fromArray(...));
		$this->ledgers = Cast::listOf($data, 'ledgers', LedgerEntry::fromArray(...));
	}
}
