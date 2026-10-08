<?php

declare(strict_types=1);

namespace QBitFlow\Exceptions;

/**
 * Any other 400 (`bad_request`, `foreign_key_violation` with `details.field`, …).
 */
class BadRequestException extends ApiException
{
}
