<?php

declare(strict_types=1);

namespace QBitFlow\Models;

use QBitFlow\Enums\DurationUnit;
use QBitFlow\Support\Cast;

/**
 * A length of time as the API writes it: a value in the largest exact unit
 * (`new Duration(1, DurationUnit::MONTHS)` = 30 days). Used in requests and responses.
 *
 * `value` 0 means none (a unit is then optional): `new Duration()` removes a trial.
 */
final readonly class Duration
{
	public function __construct(
		/** The number of units, 0 to 4294967295; 0 means no duration. */
		public int $value = 0,
		/** The unit ({@see DurationUnit}), required when `value` > 0: seconds, minutes, hours, days, weeks, months (30 days) or years (365 days). */
		public ?string $unit = null,
	) {
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
	 * @param array<string,mixed> $data
	 */
	public static function fromArray(array $data): self
	{
		return new self(Cast::uint($data, 'value', 4294967295), Cast::nullableString($data, 'unit'));
	}

	/**
	 * The wire form: `{"value": 1, "unit": "months"}` (the unit left out when unset).
	 *
	 * @return array{value: int, unit?: string}
	 */
	public function toArray(): array
	{
		$out = ['value' => $this->value];
		if ($this->unit !== null && $this->unit !== '') {
			$out['unit'] = $this->unit;
		}

		return $out;
	}
}
