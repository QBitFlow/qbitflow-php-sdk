<?php

declare(strict_types=1);

namespace QBitFlow\Models;

use QBitFlow\Support\Cast;

/**
 * An amount a one-time payment's checkout added to its product's price, shown to the customer
 * and paid with it (on {@see Payment::$fees} and {@see PaymentSessionData::$fees}).
 */
final readonly class FeeLine extends Model
{
	/**
	 * `custom` (the merchant's line) or `processingFee` (QBitFlow's fee, which the merchant has
	 * the customer pay), see {@see \QBitFlow\Enums\FeeLineType}; an unknown value is kept.
	 */
	public string $type;

	/**
	 * The line's name, as the checkout shows it ("Processing fee" for the processing fee).
	 */
	public string $label;

	/**
	 * More about the line, as the merchant wrote it; null without one.
	 */
	public ?string $description;

	/**
	 * The line's amount in USD, a decimal string with at most 2 decimals (`"4.99"`).
	 */
	public string $amountUsd;

	/**
	 * @param array<string,mixed> $data
	 */
	protected function __construct(array $data)
	{
		$this->type = Cast::string($data, 'type');
		$this->label = Cast::string($data, 'label');
		$this->description = Cast::nullableString($data, 'description');
		$this->amountUsd = Cast::string($data, 'amountUsd');
	}
}
