<?php

declare(strict_types=1);

namespace QBitFlow\Params;

use QBitFlow\Support\Validator;

/**
 * Starts a refund (`refunds->initiate()`): a pending refund the merchant signs in the dashboard.
 */
final readonly class InitiateRefundParams
{
	public function __construct(
		/** Required: the payment (`pay@…`) or bill (`sub-hist@…`) to refund. */
		public string $txUuid,
		/** The share of what the customer paid to send back: above 0, at most 100, at most 2 decimals (null = 100). */
		public ?float $refundPercent = null,
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
		if ($this->refundPercent !== null) {
			$v->percent('refundPercent', $this->refundPercent, 100, true);
		}
		$v->text('reason', $this->reason, 0, 500);
		$v->text('merchantMessage', $this->merchantMessage, 0, 500);
		$v->throwIfAny();
	}

	/** @return array<string,mixed> */
	public function toArray(): array
	{
		$out = ['txUuid' => $this->txUuid];
		if ($this->refundPercent !== null) {
			$out['refundPercent'] = $this->refundPercent;
		}
		if ($this->reason !== null && $this->reason !== '') {
			$out['reason'] = $this->reason;
		}
		if ($this->merchantMessage !== null && $this->merchantMessage !== '') {
			$out['merchantMessage'] = $this->merchantMessage;
		}

		return $out;
	}
}
