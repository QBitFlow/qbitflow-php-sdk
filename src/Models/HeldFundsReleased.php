<?php

declare(strict_types=1);

namespace QBitFlow\Models;

use DateTimeImmutable;
use QBitFlow\Support\Cast;

/**
 * `heldFunds.released`'s data: the transfer paying a member the funds the organization held for them, and the lines it settled.
 */
final readonly class HeldFundsReleased extends Model
{
	/**
	 * The transfer's id (`transfer@…`).
	 */
	public string $uuid;

	/**
	 * When it was recorded, once confirmed on-chain.
	 */
	public DateTimeImmutable $createdAt;

	/**
	 * The customer's wallet.
	 */
	public string $from;

	/**
	 * The wallet that received it.
	 */
	public string $to;

	/**
	 * What the customer paid, in USD.
	 */
	public float $amount;

	/**
	 * The amount in the token's min units (a decimal string).
	 */
	public string $amountMinUnits;

	/**
	 * The currency paid in (`$client->currencies->get()`).
	 */
	public int $currencyId;

	/**
	 * That currency.
	 */
	public ?Currency $currency;

	/**
	 * The transaction's hash.
	 */
	public string $txHash;

	/**
	 * The chain it was paid on (`ETH`, `BASE`, `SOL`; the testnet's in test mode), see {@see \QBitFlow\Enums\Chain}.
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
	 * The member whose space it is in; null for the organization's own.
	 */
	public ?string $userUuid;

	/**
	 * True once the recipient received it.
	 */
	public bool $received;

	/**
	 * `heldFundsRelease` ({@see \QBitFlow\Enums\TransferType}).
	 */
	public string $type;

	/**
	 * The transaction's network fees and block.
	 */
	public TxMetadata $txMetadata;

	/**
	 * The held-funds lines it settled.
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
		$this->amount = Cast::float($data, 'amount');
		$this->amountMinUnits = Cast::string($data, 'amountMinUnits');
		$this->currencyId = Cast::uint($data, 'currencyId');
		$this->currency = Cast::nullableObject($data, 'currency', Currency::fromArray(...));
		$this->txHash = Cast::string($data, 'txHash');
		$this->chain = Cast::nullableString($data, 'chain');
		$this->explorerUrl = Cast::nullableString($data, 'explorerUrl');
		$this->test = Cast::bool($data, 'test');
		$this->userUuid = Cast::nullableString($data, 'userUuid');
		$this->received = Cast::bool($data, 'received');
		$this->type = Cast::string($data, 'type');
		$this->txMetadata = Cast::object($data, 'txMetadata', TxMetadata::fromArray(...));
		$this->ledgers = Cast::listOf($data, 'ledgers', LedgerEntry::fromArray(...));
	}
}
