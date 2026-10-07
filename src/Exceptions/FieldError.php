<?php

declare(strict_types=1);

namespace QBitFlow\Exceptions;

/**
 * A single field-level validation failure.
 *
 * The API reports validation failures as a list, one entry per offending field:
 *
 * ```json
 * {"errors":[{"field":"ProductName","message":"ProductName is too short"},
 *            {"field":"Price","message":"Price is too short"}]}
 * ```
 */
final class FieldError
{
	public function __construct(
		/** Name of the offending field, as the API names it. Empty when the API reported a bare message. */
		public readonly string $field,
		/** The API's explanation for that field. */
		public readonly string $message,
	) {
	}

	/**
	 * Build the list of field failures from the `errors` member of an error body.
	 *
	 * Every entry is kept — surfacing only the first hides the rest of what the caller
	 * has to fix.
	 *
	 * @param  mixed $errors The raw `errors` member of a decoded error body.
	 * @return list<self>
	 */
	public static function listFrom(mixed $errors): array
	{
		if (!is_array($errors)) {
			return [];
		}

		$extracted = [];

		foreach ($errors as $entry) {
			if (is_array($entry)) {
				$field = isset($entry['field']) && is_scalar($entry['field'])
					? (string) $entry['field']
					: '';
				$message = isset($entry['message']) && is_scalar($entry['message'])
					? (string) $entry['message']
					: '';

				if ($field !== '' || $message !== '') {
					$extracted[] = new self($field, $message);
				}

				continue;
			}

			if (is_scalar($entry) && (string) $entry !== '') {
				$extracted[] = new self('', (string) $entry);
			}
		}

		return $extracted;
	}

	/** Render as `field: message`, or just the message when there is no field. */
	public function __toString(): string
	{
		return $this->field !== '' ? sprintf('%s: %s', $this->field, $this->message) : $this->message;
	}
}
