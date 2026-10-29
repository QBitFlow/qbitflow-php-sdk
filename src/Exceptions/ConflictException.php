<?php

declare(strict_types=1);

namespace QBitFlow\Exceptions;

/**
 * A 409: `unique_violation` (`details.field`), `tx_already_sent`, `merchant_not_ready`
 * (`details.reason`), `held_funds_pending`, `member_has_no_wallet`, `already_joined`, `payment_not_due`,
 * `idempotency_key_in_use`, `conflict`, …
 */
class ConflictException extends ApiException
{
}
