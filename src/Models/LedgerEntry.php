<?php

declare(strict_types=1);

namespace QBitFlow\Models;

use DateTimeImmutable;
use QBitFlow\Support\Cast;

/**
 * A line of a member's held funds: a payment or a bill made to them while the organization held
 * their funds (it adds what the organization owes them), or a refund of one of them (it takes
 * off what the refund sent back to the customer).
 */
final readonly class LedgerEntry extends Model
{
	/**
	 * The line's transaction: the payment (`pay@…`), the bill (`sub-hist@…`) or the refund (`refund@…`).
	 */
	public string $txUuid;

	/**
	 * `payment`, `bill` or `refund` ({@see \QBitFlow\Enums\LedgerEntryType}).
	 */
	public string $type;

	/**
	 * What the line is, in words (`"Payment pay@…"`).
	 */
	public string $description;

	/**
	 * On a refund line, the payment or bill it refunds; null on the others.
	 */
	public ?string $refundedTxUuid;

	/**
	 * What the customer paid (a payment or bill), or what a refund sent back (positive:
	 * `owedUsd` is its negative), in USD, a decimal string.
	 */
	public string $amountUsd;

	/**
	 * A payment's or bill's fees and split (the member was owed `settlement->split->merchant`);
	 * null on a refund line.
	 */
	public ?Settlement $settlement;

	/**
	 * The currency paid (and refunded).
	 */
	public Currency $currency;

	/**
	 * What the line counts for in a release, in the currency's units, exact: a payment's or
	 * bill's merchant share, or minus what a refund sent back (negative). In
	 * `heldFunds.released`: what it counted for in that release.
	 */
	public string $owed;

	/**
	 * The same in the currency's smallest unit (negative for a refund).
	 */
	public string $owedMinUnits;

	/**
	 * The same in USD, at the payment's time (negative for a refund).
	 */
	public string $owedUsd;

	/**
	 * When the line was recorded.
	 */
	public DateTimeImmutable $createdAt;

	/**
	 * @param array<string,mixed> $data
	 */
	protected function __construct(array $data)
	{
		$this->txUuid = Cast::string($data, 'txUuid');
		$this->type = Cast::string($data, 'type');
		$this->description = Cast::string($data, 'description');
		$this->refundedTxUuid = Cast::nullableString($data, 'refundedTxUuid');
		$this->amountUsd = Cast::string($data, 'amountUsd');
		$this->settlement = Cast::nullableObject($data, 'settlement', Settlement::fromArray(...));
		$this->currency = Cast::object($data, 'currency', Currency::fromArray(...));
		$this->owed = Cast::string($data, 'owed');
		$this->owedMinUnits = Cast::string($data, 'owedMinUnits');
		$this->owedUsd = Cast::string($data, 'owedUsd');
		$this->createdAt = Cast::date($data, 'createdAt');
	}
}
