<?php

declare(strict_types=1);

namespace QBitFlow\Exceptions;

/**
 * An HTTP error response, and the parent of every typed error below (as the Go SDK's
 * `*APIError` is embedded by each). Thrown as is for an HTTP error without a more specific
 * type (e.g. 413 `request_too_large`, a 405).
 */
class ApiException extends QBitFlowException
{
}
