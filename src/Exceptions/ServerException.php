<?php

declare(strict_types=1);

namespace QBitFlow\Exceptions;

/**
 * A 5xx (503 `network_unavailable` and 504 `timeout` included), an unexpected 3xx (redirects
 * are never followed), or a 2xx whose body is not the expected JSON (empty, not JSON, or a
 * value of the wrong JSON type).
 */
class ServerException extends ApiException
{
}
