<?php

declare(strict_types=1);

namespace QBitFlow\Models;

use QBitFlow\Support\Cast;

/**
 * `payment.completed`'s data: the payment, confirmed.
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
