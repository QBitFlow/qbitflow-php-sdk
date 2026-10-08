<?php

declare(strict_types=1);

namespace QBitFlow\Dto\Session;

/**
 * Checkout session for a one-time payment.
 */
final class OneTimePaymentSession extends SessionCheckout
{
	/**
	 * @param array<string,mixed> $data
	 */
	public static function fromArray(array $data): self
	{
		return new self(...self::baseArguments($data));
	}
}
