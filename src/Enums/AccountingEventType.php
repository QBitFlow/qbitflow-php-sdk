<?php

declare(strict_types=1);

namespace QBitFlow\Enums;

/**
 * Row types found in an accounting export.
 */
enum AccountingEventType: string
{
	case PAYMENT = 'payment';
	case SUBSCRIPTION_HISTORY = 'subscriptionHistory';
	case REFUND = 'refund';
	case ORGANIZATION_FEE = 'organizationFee';
	case REFERRAL_FEE = 'referralFee';
}
