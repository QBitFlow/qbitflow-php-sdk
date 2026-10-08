<?php

declare(strict_types=1);

namespace QBitFlow\Exceptions;

/**
 * A 422 `idempotency_key_reused`: the `Idempotency-Key` was already used for a different
 * request. A caller bug; never retried.
 */
class IdempotencyException extends ApiException
{
}
