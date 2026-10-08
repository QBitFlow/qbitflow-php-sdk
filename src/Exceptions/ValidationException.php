<?php

declare(strict_types=1);

namespace QBitFlow\Exceptions;

/**
 * A 400 `validation_failed`, or an input the SDK refused **before sending anything**
 * (`status` 0, `apiCode` empty). `fieldErrors` names each failing input by its wire name.
 */
class ValidationException extends ApiException
{
}
