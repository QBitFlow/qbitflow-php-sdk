<?php

declare(strict_types=1);

namespace QBitFlow\Enums;

/**
 * What a credential (or an invitation) may do in a space. An API key is `admin` (organization key) or `user` (a member's key, or On-Behalf-Of).
 *
 * An open enum: the API's value is kept as a plain `string`, and a value this SDK does not
 * know yet is kept as is, never rejected. Compare against these constants.
 */
final class Role
{
	public const OWNER = 'owner';

	public const ADMIN = 'admin';

	public const USER = 'user';

	public const HANDLE = 'handle';

	/**
	 * Every value this SDK knows.
	 *
	 * @return list<string>
	 */
	public static function values(): array
	{
		return [
			self::OWNER,
			self::ADMIN,
			self::USER,
			self::HANDLE,
		];
	}

	private function __construct()
	{
	}
}
