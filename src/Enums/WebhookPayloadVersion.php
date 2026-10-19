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
	/** The event envelope `{id, type, version, createdAt, test, userUuid, data}`: the only version. */
	public const V2 = 'v2';

	/**
	 * Every value this SDK knows.
	 *
	 * @return list<string>
	 */
	public static function values(): array
	{
		return [
			self::V2,
		];
	}

	private function __construct()
	{
	}
}
