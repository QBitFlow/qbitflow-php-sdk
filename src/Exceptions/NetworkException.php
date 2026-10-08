<?php

declare(strict_types=1);

namespace QBitFlow\Exceptions;

/**
 * No response was received: DNS, connection, TLS, timeout. `getPrevious()` holds the PSR-18
 * client's exception.
 */
class NetworkException extends ApiException
{
}
