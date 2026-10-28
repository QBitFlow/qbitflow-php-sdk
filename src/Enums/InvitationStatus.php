<?php

declare(strict_types=1);

namespace QBitFlow\Enums;

/**
 * An invitation's status (computed when read).
 *
 * An open enum: the API's value is kept as a plain `string`, and a value this SDK does not
 * know yet is kept as is, never rejected. Compare against these constants.
 */
final class InvitationStatus
{
	/** Sent, waiting for the person. */
	public const PENDING = 'pending';

	/** The person joined. */
	public const ACCEPTED = 'accepted';

	/** Revoked by the organization (or replaced by a newer one). */
	public const REVOKED = 'revoked';

	/** Not accepted in time. */
	public const EXPIRED = 'expired';

	/**
	 * Every value this SDK knows.
	 *
	 * @return list<string>
	 */
	public static function values(): array
	{
		return [
			self::PENDING,
			self::ACCEPTED,
			self::REVOKED,
			self::EXPIRED,
		];
	}

	private function __construct()
	{
	}
}
