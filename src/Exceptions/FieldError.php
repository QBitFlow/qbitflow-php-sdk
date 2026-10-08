<?php

declare(strict_types=1);

namespace QBitFlow\Exceptions;

/**
 * One failing input of a validation error.
 *
 * `field` is the input's wire name, dotted when nested (`frequency.unit`), indexed in a list
 * (`events[1]`). For the SDK's own checks the message starts with the field's name
 * (`"price must be a number above 0"`).
 */
final readonly class FieldError
{
	public function __construct(
		/** The input's wire name, dotted when nested (e.g. `frequency.unit`). */
		public string $field,
		/** What is wrong with it. */
		public string $message,
	) {
	}

	/** `field: message`. */
	public function __toString(): string
	{
		return $this->field . ': ' . $this->message;
	}
}
