<?php

declare(strict_types=1);

namespace QBitFlow\Enums;

/**
 * A webhook endpoint's (and an event's) payload version.
 *
 * An open enum: the API's value is kept as a plain `string`, and a value this SDK does not
 * know yet is kept as is, never rejected. Compare against these constants.
 */
final class WebhookPayloadVersion
{
	/** v1's bodies (endpoints migrated from v1; not parsed by this SDK). */
	public const V1 = 'v1';

	/** The event envelope. */
	public const V2 = 'v2';

	/**
	 * Every value this SDK knows.
	 *
	 * @return list<string>
	 */
	public static function values(): array
	{
		return [
			self::V1,
			self::V2,
		];
	}

	private function __construct()
	{
	}
}
