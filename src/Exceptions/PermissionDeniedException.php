<?php

declare(strict_types=1);

namespace QBitFlow\Exceptions;

/**
 * A 403: `forbidden`, `policy_disabled` (`details.policy`), `plan_required`.
 */
class PermissionDeniedException extends ApiException
{
}
