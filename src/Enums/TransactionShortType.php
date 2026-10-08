<?php

declare(strict_types=1);

namespace QBitFlow\Enums;

/**
 * Short transaction category encoded in a transaction UUID prefix
 * (`pay@…`, `sub@…`) and reported as `txType` on session data.
 */
enum TransactionShortType: string
{
	case PAYMENT = 'payment';
	case SUBSCRIPTION = 'subscription';
	case PAY_AS_YOU_GO = 'payAsYouGo';
	case SUBSCRIPTION_HISTORY = 'subscriptionHistory';
	case REFUND = 'refund';
	case TRANSFER = 'transfer';
}
