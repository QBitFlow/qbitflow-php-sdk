<?php

declare(strict_types=1);

namespace QBitFlow\Enums;

/**
 * What a delivery to your subscription webhook URL is reporting.
 */
enum SubscriptionWebhookType: string
{
	/** A status change that may need your attention — cancelled, low on funds, and so on. */
	case STATUS_TRANSITION = 'status_transition';

	/** An active subscription renewed for a new period and was billed successfully. */
	case BILLING = 'billing';
}
