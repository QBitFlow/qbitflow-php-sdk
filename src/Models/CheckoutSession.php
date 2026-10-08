<?php

declare(strict_types=1);

namespace QBitFlow\Models;

use DateTimeImmutable;
use QBitFlow\Support\Cast;

/**
 * A created checkout session: send the customer to `link`.
 */
final readonly class CheckoutSession extends Model
{
	/**
	 * The checkout page for the customer.
	 */
	public string $link;

	/**
	 * The session's id (`pay@…`, `sub@…`), also the id of the payment or subscription it creates.
	 */
	public string $uuid;

	/**
	 * When the checkout can no longer be paid (`checkout.expired` is sent then).
	 */
	public ?DateTimeImmutable $expiresAt;

	/**
	 * @param array<string,mixed> $data
	 */
	protected function __construct(array $data)
	{
		$this->link = Cast::string($data, 'link');
		$this->uuid = Cast::string($data, 'uuid');
		$this->expiresAt = Cast::nullableDate($data, 'expiresAt');
	}
}
