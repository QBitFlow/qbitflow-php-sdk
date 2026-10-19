<?php

declare(strict_types=1);

namespace QBitFlow\Params;

use QBitFlow\Support\Validator;

/**
 * Starts a refund (`refunds->create()`): a pending refund the merchant signs in the dashboard.
 */
final readonly class CreateRefundParams
{
	public function __construct(
		/** Required: the space's payment (`pay@…`) or bill (`sub-hist@…`) to refund, or its bare UUID. */
		public string $txUuid,
		/**
		 * The share of what the customer paid to send back, a percent string (`"50"`): above 0, at
		 * most 100, at most 2 decimals (null or `''` = `"100"`: everything the customer paid,
		 * network fee included).
		 */
		public ?string $refundPercent = null,
		/** Why the merchant refunds (optional, at most 500 characters). */
		public ?string $reason = null,
		/** A note to the customer (optional, at most 500 characters). */
		public ?string $merchantMessage = null,
	) {
	}

	/** @throws \QBitFlow\Exceptions\ValidationException */
	public function validate(): void
	{
		$v = new Validator();
		if ($v->required('txUuid', $this->txUuid)) {
			$v->txId('txUuid', $this->txUuid);
		}
		if ($this->refundPercent !== null && $this->refundPercent !== '') {
			$v->percent('refundPercent', $this->refundPercent, 100, true);
		}
		$v->text('reason', $this->reason, 0, 500);
		$v->text('merchantMessage', $this->merchantMessage, 0, 500);
		$v->throwIfAny();
	}

	/** @return array<string,mixed> */
	public function toArray(): array
	{
		return array_filter([
			'txUuid' => $this->txUuid,
			'refundPercent' => $this->refundPercent,
			'reason' => $this->reason,
			'merchantMessage' => $this->merchantMessage,
		], static fn (?string $value): bool => $value !== null && $value !== '');
	}
}
