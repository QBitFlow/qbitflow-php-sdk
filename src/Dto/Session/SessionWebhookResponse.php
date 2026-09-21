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
 */
final class SessionWebhookResponse extends Dto
{
	public function __construct(
		/** Session UUID. */
		public readonly string $uuid,
		/** Current transaction status. */
		public readonly TransactionStatus $status,
		/** The checkout data, as a payment, subscription or PAYG session. */
		public readonly SessionCheckout $session,
		/** Top-level transaction type: `payment` or `createSubscription`. */
		public readonly TransactionType $txType,
		/** Link to the QBitFlow management page for this transaction. */
		public readonly string $managementPageLink,
	) {
	}

	/**
	 * @param array<string,mixed> $data
	 */
	public static function fromArray(array $data): self
	{
		$status = $data['status'] ?? [];
		$session = $data['session'] ?? [];

		return new self(
			Cast::string($data, 'uuid'),
			TransactionStatus::fromArray(is_array($status) ? $status : []),
			SessionCheckout::discriminate(is_array($session) ? $session : []),
			Cast::enum($data, 'txType', TransactionType::class, TransactionType::ONE_TIME_PAYMENT),
			Cast::string($data, 'managementPageLink'),
		);
	}

	/** Whether this event announces a newly created subscription. */
	public function isSubscription(): bool
	{
		return $this->txType === TransactionType::CREATE_SUBSCRIPTION;
	}
}
