<?php

declare(strict_types=1);

namespace QBitFlow\Exceptions;

/**
 * A 409: `unique_violation` (`details.field`), `tx_already_sent`, `merchant_not_ready`
 * (`details.reason`), `refund_already_exists` (`details.refundUuid`), `held_funds_pending`,
 * `already_joined`, `payment_not_due`, `idempotency_key_in_use`, `conflict`, …
 */
class ConflictException extends ApiException
{
}
