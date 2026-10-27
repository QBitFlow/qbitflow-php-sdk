<?php

declare(strict_types=1);

namespace QBitFlow\Exceptions;

/**
 * A 403: the key is authenticated but not allowed to do this.
 *
 * - `forbidden`: the key's role doesn't allow the call (a member's key on an organization-only
 *   route, or any call needing `admin` while acting On-Behalf-Of a member), or a bad
 *   `On-Behalf-Of` value. It carries no details.
 * - `policy_disabled`: a member's key doing what the organization's member policy doesn't allow
 *   members. `details['policy']` names the policy (e.g. `members.checkoutRedirects`), and
 *   `details['field']` the request field it governs, when there is one (`successUrl` or
 *   `cancelUrl` on a checkout).
 *
 * Branch on {@see ApiException::getErrorCode()}, never on the message.
 */
class PermissionDeniedException extends ApiException
{
}
