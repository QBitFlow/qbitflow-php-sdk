<?php

declare(strict_types=1);

namespace QBitFlow\Models;

use QBitFlow\Support\Cast;

/**
 * `payment.completed`'s data: the payment, confirmed, as `payments->get()` returns it to its
 * owner (its refund state excepted: API reads only), and its customer page. Fulfil the order:
 * `uuid` is the checkout session's `pay@…`, `reference` and `metadata` yours.
 */
final readonly class PaymentCompleted extends Payment
{
	/**
	 * The payment's page for its customer.
	 */
	public ?string $managementPageLink;

	/**
	 * @param array<string,mixed> $data
	 */
	protected function __construct(array $data)
	{
		parent::__construct($data);
		$this->managementPageLink = Cast::nullableString($data, 'managementPageLink');
	}
}
