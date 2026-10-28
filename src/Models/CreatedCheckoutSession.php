<?php

declare(strict_types=1);

namespace QBitFlow\Models;

use DateTimeImmutable;
use QBitFlow\Support\Cast;

/**
 * A checkout session just opened (`checkoutSessions->createPayment()`, `createSubscription()`):
 * the page to send the customer to, its id, and how long it can be paid.
 */
final readonly class CreatedCheckoutSession extends Model
{
	/**
	 * The checkout page for the customer: `<the merchant's checkout base URL>/checkout?uuid=<uuid>`.
	 */
	public string $link;

	/**
	 * The session's id (`pay@…`, `sub@…`), also the id of the payment or subscription it creates.
	 */
	public string $uuid;

	/**
	 * When the checkout can no longer be paid (`checkout.expired` is sent then, if it wasn't paid).
	 */
	public DateTimeImmutable $expiresAt;

	/**
	 * @param array<string,mixed> $data
	 */
	protected function __construct(array $data)
	{
		$this->link = Cast::string($data, 'link');
		$this->uuid = Cast::string($data, 'uuid');
		$this->expiresAt = Cast::date($data, 'expiresAt');
	}
}
