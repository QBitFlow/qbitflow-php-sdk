<?php

declare(strict_types=1);

namespace QBitFlow\Models;

use DateTimeImmutable;
use QBitFlow\Support\Cast;

/**
 * The transaction that paid (or refunded, or released): where and when it landed, and what it
 * cost its sender in the chain's native coin.
 */
final readonly class SettlementTransaction extends Model
{
	/**
	 * The block (Ethereum, Base) or slot (Solana) it landed in, a string of digits.
	 */
	public string $blockNumber;

	/**
	 * When its block was produced (UTC); null (absent) only on records made before it was recorded.
	 */
	public ?DateTimeImmutable $blockTime;

	/**
	 * The gas it consumed (Ethereum, Base); null (absent) on Solana.
	 */
	public ?int $gasUsed;

	/**
	 * What the transaction cost its sender, in the chain's native coin (ETH, SOL): its gas and,
	 * on Base, its L1 data fee; its `amountUsd` at `nativePriceUsd`.
	 */
	public TokenAmount $fee;

	/**
	 * The part of `fee` that paid Base's L1 data fee, in the native coin; null (absent) off Base.
	 */
	public ?TokenAmount $l1Fee;

	/**
	 * The USD price of the chain's native coin when the transaction was recorded (a decimal
	 * string); null (absent) when no price was available.
	 */
	public ?string $nativePriceUsd;

	/**
	 * @param array<string,mixed> $data
	 */
	protected function __construct(array $data)
	{
		$this->blockNumber = Cast::string($data, 'blockNumber');
		$this->blockTime = Cast::nullableDate($data, 'blockTime');
		$this->gasUsed = Cast::nullableUint($data, 'gasUsed');
		$this->fee = Cast::object($data, 'fee', TokenAmount::fromArray(...));
		$this->l1Fee = Cast::nullableObject($data, 'l1Fee', TokenAmount::fromArray(...));
		$this->nativePriceUsd = Cast::nullableString($data, 'nativePriceUsd');
	}
}
