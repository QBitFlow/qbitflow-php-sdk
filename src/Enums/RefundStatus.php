<?php

declare(strict_types=1);

namespace QBitFlow\Enums;

/**
 * States of a refund entry.
 */
enum RefundStatus: string
{
	case PENDING = 'pending';
	case APPROVED = 'approved';
	case REFUSED = 'refused';
	case FAILED = 'failed';
}
