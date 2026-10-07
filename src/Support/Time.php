<?php

declare(strict_types=1);

namespace QBitFlow\Support;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;

/**
 * Go's zero time, which the API sends for a timestamp that was never set.
 *
 * The API is written in Go, and a Go `time.Time` is never null: an unset one serializes as
 * `0001-01-01T00:00:00Z`. The SDK keeps such timestamps non-nullable and hands you that
 * date as-is — test for it here rather than null-checking.
 *
 * ```php
 * if (Time::isZero($subscription->nextBillingDate)) {
 *     // nothing scheduled
 * }
 * ```
 */
final class Time
{
	/** Go's zero `time.Time`, as the API serializes it. */
	public const GO_ZERO = '0001-01-01T00:00:00Z';

	/** Go's zero time, in UTC. */
	public static function zero(): DateTimeImmutable
	{
		return new DateTimeImmutable(self::GO_ZERO, new DateTimeZone('UTC'));
	}

	/** Whether a timestamp is Go's zero time, i.e. "never set". */
	public static function isZero(DateTimeInterface $value): bool
	{
		return $value->getTimestamp() === self::zero()->getTimestamp()
			&& (int) $value->format('u') === 0;
	}
}
