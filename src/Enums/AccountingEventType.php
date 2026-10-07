<?php

declare(strict_types=1);

namespace QBitFlow\Enums;

/**
 * Row types found in an accounting export.
 */
enum AccountingEventType: string
{
	case PAYMENT = 'payment';
	case SUBSCRIPTION_HISTORY = 'subscriptionHistory';
	case REFUND = 'refund';
	case ORGANIZATION_FEE = 'organizationFee';
	case REFERRAL_FEE = 'referralFee';

	/**
	 * Alternative spelling of the subscription-billing row type.
	 *
	 * The API reference documents the row type both as `TransactionShortTypeSubHistory`
	 * (`subscriptionHistory`) and, in prose, as `subHistory`. Both hydrate to
	 * {@see AccountingEventType::SUBSCRIPTION_HISTORY}.
	 */
	public const SUBSCRIPTION_HISTORY_ALIAS = 'subHistory';

	/**
	 * Resolve a hydrated value, folding the documented alias onto its member.
	 *
	 * @internal
	 */
	public static function fromWire(self|string $value): self|string
	{
		return $value === self::SUBSCRIPTION_HISTORY_ALIAS ? self::SUBSCRIPTION_HISTORY : $value;
	}
}
