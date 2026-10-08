<?php

declare(strict_types=1);

namespace QBitFlow\Models;

/**
 * Base class of the response models.
 *
 * A model is a read-only value: its properties are named exactly as the API's JSON keys
 * (camelCase), and it is built by {@see Model::fromArray()} from a decoded JSON object,
 * following the SDK's decoding policy (see {@see \QBitFlow\Support\Cast}): an absent or
 * `null` value gives the field's zero value (or `null` for a nullable field), and a value of
 * the wrong JSON type is a {@see \QBitFlow\Exceptions\ServerException}.
 */
abstract readonly class Model
{
	/**
	 * Builds the model from a decoded JSON object: its properties as an array (nested objects
	 * may be `stdClass` instances or associative arrays).
	 *
	 * @param array<string,mixed> $data
	 *
	 * @throws \QBitFlow\Exceptions\ServerException When a value has the wrong JSON type.
	 */
	final public static function fromArray(array $data): static
	{
		return new static($data);
	}

	/**
	 * @param array<string,mixed> $data
	 */
	abstract protected function __construct(array $data);
}
