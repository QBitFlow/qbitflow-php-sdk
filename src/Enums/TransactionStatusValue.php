<?php

declare(strict_types=1);

namespace QBitFlow\Enums;

/**
 * Lifecycle states of a transaction.
 */
enum TransactionStatusValue: string
{
	/** Created but not yet processed. */
	case CREATED = 'created';

	/** Broadcast, waiting for blockchain confirmation. */
	case WAITING_CONFIRMATION = 'waitingConfirmation';

	/** Pending processing. */
	case PENDING = 'pending';

	/** Successfully completed. */
	case COMPLETED = 'completed';

	/** Failed. */
	case FAILED = 'failed';

	/** Cancelled. */
	case CANCELLED = 'cancelled';

	/** Expired before completion. */
	case EXPIRED = 'expired';
}
