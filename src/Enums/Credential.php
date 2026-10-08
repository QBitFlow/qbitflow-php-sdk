<?php

declare(strict_types=1);

namespace QBitFlow\Enums;

/**
 * What authenticated a request (`Me::$credential`).
 *
 * An open enum: the API's value is kept as a plain `string`, and a value this SDK does not
 * know yet is kept as is, never rejected. Compare against these constants.
 */
final class Credential
{
	/** An API key (X-API-Key). */
	public const API_KEY = 'apiKey';

	/** A signed-in person's access token. */
	public const SESSION = 'session';

	/**
	 * Every value this SDK knows.
	 *
	 * @return list<string>
	 */
	public static function values(): array
	{
		return [
			self::API_KEY,
			self::SESSION,
		];
	}

	private function __construct()
	{
	}
}
