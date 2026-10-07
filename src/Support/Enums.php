<?php

declare(strict_types=1);

namespace QBitFlow\Support;

use BackedEnum;

/**
 * Helpers for the `<Enum>|string` union the response objects use.
 *
 * A response field backed by an enum hydrates to the enum member when the SDK knows the
 * value and to the raw string when it does not (a value the API added after this SDK was
 * released). The union keeps such a response readable instead of failing it wholesale;
 * these helpers make the union painless to work with.
 *
 * ```php
 * echo Enums::value($subscription->subscriptionStatus);   // 'active', or the unknown raw value
 *
 * if (Enums::is($subscription->subscriptionStatus, SubscriptionStatus::ACTIVE)) { ... }
 * ```
 */
final class Enums
{
	/** The wire value of an enum member or raw string. */
	public static function value(BackedEnum|string $value): string
	{
		return $value instanceof BackedEnum ? (string) $value->value : $value;
	}

	/** Whether a hydrated value is a specific enum member (a raw string never is). */
	public static function is(BackedEnum|string|null $value, BackedEnum $member): bool
	{
		return $value === $member;
	}

	/** Whether the API sent a value this SDK does not know. */
	public static function isUnknown(BackedEnum|string|null $value): bool
	{
		return is_string($value);
	}
}
