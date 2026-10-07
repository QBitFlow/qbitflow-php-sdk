<?php

declare(strict_types=1);

namespace QBitFlow\Support;

use QBitFlow\Enums\DurationUnit;
use QBitFlow\Exceptions\ValidationException;
use Stringable;

/**
 * A period of time, used for subscription billing frequencies and trial periods.
 *
 * ```php
 * $monthly = Duration::months(1);
 * $trial   = Duration::days(7);
 *
 * // Equivalent, spelled out:
 * $monthly = new Duration(1, DurationUnit::MONTHS);
 * ```
 */
final class Duration extends Dto implements Stringable
{
	public readonly int $value;

	public readonly DurationUnit $unit;

	/**
	 * @param int                 $value Number of units, 0 to 4294967295 (a Go `uint32`). A
	 *                                   billing frequency must be at least 1; a trial period
	 *                                   may be 0. Both are checked when the session is created.
	 * @param DurationUnit|string $unit  Unit of time.
	 *
	 * @throws ValidationException If the value is out of range or the unit is unknown.
	 */
	public function __construct(int $value, DurationUnit|string $unit)
	{
		if ($value < 0 || $value > Validate::UINT32_MAX) {
			throw new ValidationException(sprintf(
				'Duration value must be between 0 and %d',
				Validate::UINT32_MAX,
			));
		}

		if (is_string($unit)) {
			$resolved = DurationUnit::tryFrom($unit);

			if ($resolved === null) {
				throw new ValidationException(sprintf(
					'Invalid duration unit "%s". Expected one of: %s',
					$unit,
					implode(', ', array_column(DurationUnit::cases(), 'value')),
				));
			}

			$unit = $resolved;
		}

		$this->value = $value;
		$this->unit = $unit;
	}

	public static function seconds(int $value): self
	{
		return new self($value, DurationUnit::SECONDS);
	}

	public static function minutes(int $value): self
	{
		return new self($value, DurationUnit::MINUTES);
	}

	public static function hours(int $value): self
	{
		return new self($value, DurationUnit::HOURS);
	}

	public static function days(int $value): self
	{
		return new self($value, DurationUnit::DAYS);
	}

	public static function weeks(int $value): self
	{
		return new self($value, DurationUnit::WEEKS);
	}

	public static function months(int $value): self
	{
		return new self($value, DurationUnit::MONTHS);
	}

	public static function years(int $value): self
	{
		return new self($value, DurationUnit::YEARS);
	}

	/**
	 * Build a Duration from a plain array such as `['value' => 1, 'unit' => 'months']`.
	 *
	 * @param array<array-key,mixed> $data
	 *
	 * @throws ValidationException If `value` is not an integer in range, or `unit` is missing or unknown.
	 */
	public static function fromArray(array $data): self
	{
		$value = $data['value'] ?? null;
		$unit = $data['unit'] ?? null;

		if (! is_int($value)) {
			throw new ValidationException('Duration value must be an integer');
		}

		if (! is_string($unit) && ! $unit instanceof DurationUnit) {
			throw new ValidationException('Duration unit is required');
		}

		return new self($value, $unit);
	}

	/**
	 * Render the duration for humans, e.g. `1 month` or `3 days`.
	 */
	public function __toString(): string
	{
		$unit = $this->value === 1
			? rtrim($this->unit->value, 's')
			: $this->unit->value;

		return $this->value . ' ' . $unit;
	}
}
