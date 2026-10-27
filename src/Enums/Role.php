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
	/** An organization key: every method, the organization's members and invitations included. */
	public const ADMIN = 'admin';

	/**
	 * A member's key, or an organization key acting On-Behalf-Of a member: the member's own space
	 * only.
	 */
	public const USER = 'user';

	/**
	 * Every value this SDK knows.
	 *
	 * @return list<string>
	 */
	public static function values(): array
	{
		return [
			self::ADMIN,
			self::USER,
		];
	}

	private function __construct()
	{
	}
}
