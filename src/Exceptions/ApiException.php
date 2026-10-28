<?php

declare(strict_types=1);

namespace QBitFlow\Exceptions;

/**
 * An HTTP error response, and the parent of every typed HTTP error (catch it to handle any
 * error the API answered). Thrown as is for an HTTP error without a more specific type (e.g. 413
 * `request_too_large`, 405 `method_not_allowed`).
 */
class ApiException extends QBitFlowException
{
}
