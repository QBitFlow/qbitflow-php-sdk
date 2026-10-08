<?php

declare(strict_types=1);

namespace QBitFlow\Events;

use QBitFlow\Models\PaymentSessionData;
use QBitFlow\Models\SubscriptionSessionData;
use QBitFlow\Support\Cast;

/**
 * `checkout.expired`: a checkout session expired unpaid: release what the order holds.
 *
 * `data` is a {@see SubscriptionSessionData} for a subscription session (`txType`
 * `createSubscription`), else a {@see PaymentSessionData} (an unknown `txType` too; `rawData`
 * keeps the raw object).
 */
final readonly class CheckoutExpiredEvent extends Event
{
	/** The expired session: a payment's, or a subscription's. */
	public PaymentSessionData|SubscriptionSessionData $data;

	/**
	 * @param array<string,mixed> $data
	 */
	protected function __construct(array $data)
	{
		parent::__construct($data);
		$this->data = self::isSubscriptionSession($data['data'] ?? null)
			? Cast::object($data, 'data', SubscriptionSessionData::fromArray(...))
			: Cast::object($data, 'data', PaymentSessionData::fromArray(...));
	}
}
