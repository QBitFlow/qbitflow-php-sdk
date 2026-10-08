<?php

declare(strict_types=1);

namespace QBitFlow\Enums;

/**
 * A checkout session's (or a refund approval's) status.
 *
 * An open enum: the API's value is kept as a plain `string`, and a value this SDK does not
 * know yet is kept as is, never rejected. Compare against these constants.
 */
final class CheckoutSessionStatusValue
{
	/** Waiting for the customer: nothing sent yet, or the last attempt failed (lastAttempt). */
	public const CREATED = 'created';

	/** A transaction was sent: waiting for the network and its record. */
	public const WAITING_CONFIRMATION = 'waitingConfirmation';

	/** Confirmed and recorded. Final. */
	public const COMPLETED = 'completed';

	/** Expired unpaid. Final. */
	public const EXPIRED = 'expired';

	/**
	 * Every value this SDK knows.
	 *
	 * @return list<string>
	 */
	public static function values(): array
	{
		return [
			self::CREATED,
			self::WAITING_CONFIRMATION,
			self::COMPLETED,
			self::EXPIRED,
		];
	}

	private function __construct()
	{
	}
}
