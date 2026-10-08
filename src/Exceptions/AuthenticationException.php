<?php

declare(strict_types=1);

namespace QBitFlow\Exceptions;

/**
 * A 401: the API key is missing, unknown, expired or revoked (a removed member's keys too).
 */
class AuthenticationException extends ApiException
{
}
