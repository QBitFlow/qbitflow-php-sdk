<?php

declare(strict_types=1);

namespace QBitFlow\Exceptions;

use Throwable;

/**
 * Marker interface implemented by every exception the QBitFlow SDK throws.
 *
 * ```php
 * try {
 *     $client->payments->get($id);
 * } catch (\QBitFlow\Exceptions\ExceptionInterface $e) {
 *     // any SDK failure
 * }
 * ```
 */
interface ExceptionInterface extends Throwable
{
}
