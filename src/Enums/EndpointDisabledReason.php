<?php

declare(strict_types=1);

namespace QBitFlow\Enums;

/**
 * Why a webhook endpoint is disabled.
 *
 * An open enum: the API's value is kept as a plain `string`, and a value this SDK does not
 * know yet is kept as is, never rejected. Compare against these constants.
 */
final class EndpointDisabledReason
{
	/** Every delivery failing for too long. */
	public const FAILING = 'failing';

	/** Disabled by its owner. */
	public const OWNER = 'owner';

	/** Its member removed, or its organization closed. */
	public const CLOSED = 'closed';

	/**
	 * Every value this SDK knows.
	 *
	 * @return list<string>
	 */
	public static function values(): array
	{
		return [
			self::FAILING,
			self::OWNER,
			self::CLOSED,
		];
	}

	private function __construct()
	{
	}
}
