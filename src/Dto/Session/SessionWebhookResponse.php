<?php

declare(strict_types=1);

namespace QBitFlow\Dto\Session;

use QBitFlow\Dto\TransactionStatus;
use QBitFlow\Enums\TransactionType;
use QBitFlow\Support\Cast;
use QBitFlow\Support\Dto;

/**
 * Payload delivered to your **transaction** webhook URL when a checkout you created is
 * completed by the customer.
 *
 * Two cases trigger it: a one-time payment was paid, or a subscription checkout was
 * completed — in which case the first billing has already happened.
 *
 * ```php
 * $event = SessionWebhookResponse::fromArray(json_decode($rawBody, true));
 * ```
 */
final class SessionWebhookResponse extends Dto
{
	public function __construct(
		/** Session UUID. */
		public readonly string $uuid,
		/**
		 * Top-level transaction type: `payment` or `createSubscription`. A `TransactionType`
		 * member, or the raw string for a type this SDK does not know.
		 */
		public readonly TransactionType|string $txType,
		/** The checkout data, as a payment or subscription session. */
		public readonly SessionCheckout $session,
		/** Current transaction status; null when the delivery carries none. */
		public readonly ?TransactionStatus $status = null,
		/** Link to the QBitFlow management page for this transaction; `''` when not provided. */
		public readonly string $managementPageLink = '',
	) {
	}

	/**
	 * @param array<array-key,mixed> $data
	 *
	 * @throws \QBitFlow\Exceptions\ServerException When the payload does not have the webhook shape.
	 */
	public static function fromArray(array $data): self
	{
		return Cast::one($data, static fn (array $body): self => new self(
			Cast::string($body, 'uuid'),
			Cast::enum($body, 'txType', TransactionType::class),
			Cast::object($body, 'session', SessionCheckout::discriminate(...)),
			Cast::nullableObject($body, 'status', TransactionStatus::fromArray(...)),
			Cast::string($body, 'managementPageLink'),
		));
	}

	/** Whether this event announces a newly created subscription. */
	public function isSubscription(): bool
	{
		return $this->txType === TransactionType::CREATE_SUBSCRIPTION;
	}
}
