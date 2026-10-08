<?php

declare(strict_types=1);

namespace QBitFlow\Models;

use DateTimeImmutable;
use QBitFlow\Support\Cast;

/**
 * One held-funds line.
 */
final readonly class LedgerEntry extends Model
{
	/**
	 * The line's transaction: `pay@…`, `sub-hist@…` or `refund@…`.
	 */
	public string $txUuid;

	/**
	 * `payment`, `subscriptionHistory` or `refund` ({@see \QBitFlow\Enums\LedgerEntryType}).
	 */
	public string $type;

	/**
	 * What the line is, in words.
	 */
	public string $description;

	/**
	 * On a refund line, the payment or bill it refunds.
	 */
	public ?string $refundedTxUuid;

	/**
	 * What the customer paid (a payment or bill), or what a refund sent back, in USD.
	 */
	public float $amount;

	/**
	 * A payment's or bill's fees and split; null on refund lines.
	 */
	public ?PaymentMetadata $metadata;

	/**
	 * The currency paid (and refunded).
	 */
	public int $currencyId;

	/**
	 * What the line counts for in a release, in min units (a decimal string, negative on refunds).
	 */
	public string $owedMinUnits;

	/**
	 * The same in USD.
	 */
	public float $owedUsd;

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
		$this->amount = Cast::float($data, 'amount');
		$this->metadata = Cast::nullableObject($data, 'metadata', PaymentMetadata::fromArray(...));
		$this->currencyId = Cast::uint($data, 'currencyId');
		$this->owedMinUnits = Cast::string($data, 'owedMinUnits');
		$this->owedUsd = Cast::float($data, 'owedUsd');
		$this->createdAt = Cast::date($data, 'createdAt');
	}
}
