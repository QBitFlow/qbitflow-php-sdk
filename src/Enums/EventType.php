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
	/** A one-time payment was confirmed: fulfil the order. */
	public const PAYMENT_COMPLETED = 'payment.completed';

	/** A subscription was created (active, or in its trial): grant access. */
	public const SUBSCRIPTION_CREATED = 'subscription.created';

	/** A subscription's bill was paid (`data->bill`, `data->subscription`). */
	public const SUBSCRIPTION_BILLED = 'subscription.billed';

	/** A subscription's status changed. */
	public const SUBSCRIPTION_STATUS_CHANGED = 'subscription.statusChanged';

	/** What a subscription's customer must do changed. */
	public const SUBSCRIPTION_ACTION_REQUIRED_CHANGED = 'subscription.actionRequiredChanged';

	/** A bill attempt failed. */
	public const SUBSCRIPTION_BILLING_FAILED = 'subscription.billingFailed';

	/** A bill (or a trial's end) is near. */
	public const SUBSCRIPTION_UPCOMING_BILL = 'subscription.upcomingBill';

	/** A customer requested a refund. */
	public const REFUND_REQUESTED = 'refund.requested';

	/** A refund was approved, its transfer confirmed (was `refund.completed`). */
	public const REFUND_APPROVED = 'refund.approved';

	/** A refund request was rejected (was `refund.denied`). */
	public const REFUND_REJECTED = 'refund.rejected';

	/** Someone accepted an invitation and joined as a member. */
	public const MEMBER_JOINED = 'member.joined';

	/** A member was removed. */
	public const MEMBER_REMOVED = 'member.removed';

	/** The funds the organization held for a member were paid out to them. */
	public const HELD_FUNDS_RELEASED = 'heldFunds.released';

	/** A checkout session expired unpaid. */
	public const CHECKOUT_EXPIRED = 'checkout.expired';

	/** Sent only by an endpoint's test (the dashboard); no endpoint subscribes to it. */
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
			self::REFUND_APPROVED,
			self::REFUND_REJECTED,
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
