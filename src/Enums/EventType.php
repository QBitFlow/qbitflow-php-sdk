<?php

declare(strict_types=1);

namespace QBitFlow\Enums;

/**
 * A webhook event's type.
 *
 * An open enum: the API's value is kept as a plain `string`, and a value this SDK does not
 * know yet is kept as is, never rejected. Compare against these constants.
 */
final class EventType
{
	public const PAYMENT_COMPLETED = 'payment.completed';

	public const SUBSCRIPTION_CREATED = 'subscription.created';

	public const SUBSCRIPTION_BILLED = 'subscription.billed';

	public const SUBSCRIPTION_STATUS_CHANGED = 'subscription.statusChanged';

	public const SUBSCRIPTION_ACTION_REQUIRED_CHANGED = 'subscription.actionRequiredChanged';

	public const SUBSCRIPTION_BILLING_FAILED = 'subscription.billingFailed';

	public const SUBSCRIPTION_UPCOMING_BILL = 'subscription.upcomingBill';

	public const REFUND_REQUESTED = 'refund.requested';

	public const REFUND_COMPLETED = 'refund.completed';

	public const REFUND_DENIED = 'refund.denied';

	public const MEMBER_JOINED = 'member.joined';

	public const MEMBER_REMOVED = 'member.removed';

	public const HELD_FUNDS_RELEASED = 'heldFunds.released';

	public const CHECKOUT_EXPIRED = 'checkout.expired';

	public const WEBHOOK_TEST = 'webhook.test';

	/**
	 * Every value this SDK knows.
	 *
	 * @return list<string>
	 */
	public static function values(): array
	{
		return [
			self::PAYMENT_COMPLETED,
			self::SUBSCRIPTION_CREATED,
			self::SUBSCRIPTION_BILLED,
			self::SUBSCRIPTION_STATUS_CHANGED,
			self::SUBSCRIPTION_ACTION_REQUIRED_CHANGED,
			self::SUBSCRIPTION_BILLING_FAILED,
			self::SUBSCRIPTION_UPCOMING_BILL,
			self::REFUND_REQUESTED,
			self::REFUND_COMPLETED,
			self::REFUND_DENIED,
			self::MEMBER_JOINED,
			self::MEMBER_REMOVED,
			self::HELD_FUNDS_RELEASED,
			self::CHECKOUT_EXPIRED,
			self::WEBHOOK_TEST,
		];
	}

	private function __construct()
	{
	}
}
