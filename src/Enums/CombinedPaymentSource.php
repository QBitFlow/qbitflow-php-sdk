<?php

declare(strict_types=1);

namespace QBitFlow\Enums;

/**
 * Origin of an entry returned by the combined payments endpoint.
 */
enum CombinedPaymentSource: string
{
	/** A one-time payment. */
	case PAYMENT = 'payment';

	/** A subscription billing-cycle record. */
	case SUBSCRIPTION_HISTORY = 'subscription_history';
}
