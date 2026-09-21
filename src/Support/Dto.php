<?php

declare(strict_types=1);

namespace QBitFlow\Support;

use BackedEnum;
use DateTimeInterface;
use JsonSerializable;
use ReflectionObject;
use ReflectionProperty;

/**
 * Base class for every data object in the SDK.
 *
 * Provides a single reflection-based serializer so that request DTOs and response
 * objects alike convert to the camelCase array shape the API speaks. `null` values are
 * omitted, which is what the API's partial-update endpoints expect.
 */
abstract class Dto implements JsonSerializable
{
	/**
	 * Convert the object to the array representation used on the wire.
	 *
	 * Property names are sent verbatim — they already match the API's camelCase keys.
	 * Nested DTOs, enums and dates are converted recursively; `null` values are dropped.
	 *
	 * @return array<string,mixed>
	 */
	public function toArray(): array
	{
		$out = [];

		foreach ((new ReflectionObject($this))->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
			if ($property->isStatic() || ! $property->isInitialized($this)) {
				continue;
			}

			$value = self::normalize($property->getValue($this));

			if ($value !== null) {
				$out[$property->getName()] = $value;
			}
		}

		return $out;
	}

	public function jsonSerialize(): mixed
	{
		return $this->toArray();
	}

	/**
	 * Recursively convert a value into a JSON-encodable scalar, list or map.
	 */
	private static function normalize(mixed $value): mixed
	{
		if ($value instanceof self) {
			return $value->toArray();
		}

		if ($value instanceof BackedEnum) {
			return $value->value;
		}

		if ($value instanceof DateTimeInterface) {
			return $value->format(DATE_ATOM);
		}

		if (is_array($value)) {
			return array_map(static fn (mixed $item): mixed => self::normalize($item), $value);
		}

		return $value;
	}
}
