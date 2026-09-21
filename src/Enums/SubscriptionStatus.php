<?php

declare(strict_types=1);

namespace QBitFlow\Enums;

/**
 * Lifecycle states of a subscription.
 */
enum SubscriptionStatus: string
{
	/** Active and billing normally. */
	case ACTIVE = 'active';

	/** Cancelled (inactive). */
	case CANCELLED = 'cancelled';

	/** Last payment attempt failed; retried until the grace period ends, then cancelled. */
	case PAST_DUE = 'past_due';

	/** On-chain allowance is running low; the next billing may fail. */
	case LOW_ON_FUNDS = 'low_on_funds';

	/** Max amount reached (e.g. price fluctuation); the subscriber must raise it. */
	case PENDING = 'pending';

	/** Currently within the trial period. */
	case TRIAL = 'trial';

	/** Trial expired; grace period to upgrade before being flagged as cancelled. */
	case TRIAL_EXPIRED = 'trial_expired';
}
