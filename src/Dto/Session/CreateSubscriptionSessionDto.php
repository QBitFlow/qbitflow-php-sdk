<?php

declare(strict_types=1);

namespace QBitFlow\Dto\Session;

use QBitFlow\Exceptions\ValidationException;
use QBitFlow\Support\Duration;

/**
 * Payload for creating a subscription session.
 *
 * Unlike a one-time payment, a subscription must reference an existing product: supply
 * either `productId` or `productReference`.
 *
 * ```php
 * $link = $client->subscriptions->createSession(new CreateSubscriptionSessionDto(
 *     frequency: Duration::months(1),
 *     productId: 1,
 *     trialPeriod: Duration::days(7),
 *     customerUUID: 'customer-uuid',
 * ));
 * ```
 */
final class CreateSubscriptionSessionDto extends CreatePaymentSessionDto
{
	public function __construct(
		/** Billing frequency. Required. */
		public readonly Duration $frequency,
		?string $reference = null,
		?int $productId = null,
		?string $productReference = null,
		?string $productName = null,
		?string $description = null,
		?float $price = null,
		?string $successUrl = null,
		?string $cancelUrl = null,
		?string $customerUUID = null,
		?string $customerReference = null,
		/** Trial period before the first billing. */
		public readonly ?Duration $trialPeriod = null,
		/** Minimum number of billing periods the subscriber must complete. */
		public readonly ?int $minPeriods = null,
	) {
		parent::__construct(
			$reference,
			$productId,
			$productReference,
			$productName,
			$description,
			$price,
			$successUrl,
			$cancelUrl,
			$customerUUID,
			$customerReference,
		);
	}

	/**
	 * @param array<string,mixed> $data
	 */
	public static function fromArray(array $data): static
	{
		$frequency = $data['frequency'] ?? null;

		if ($frequency instanceof Duration) {
			$resolved = $frequency;
		} elseif (is_array($frequency)) {
			$resolved = Duration::fromArray($frequency);
		} else {
			throw new ValidationException('Frequency is required');
		}

		$trial = $data['trialPeriod'] ?? null;

		return new self(
			frequency: $resolved,
			reference: isset($data['reference']) ? (string) $data['reference'] : null,
			productId: isset($data['productId']) ? (int) $data['productId'] : null,
			productReference: isset($data['productReference']) ? (string) $data['productReference'] : null,
			productName: isset($data['productName']) ? (string) $data['productName'] : null,
			description: isset($data['description']) ? (string) $data['description'] : null,
			price: isset($data['price']) ? (float) $data['price'] : null,
			successUrl: isset($data['successUrl']) ? (string) $data['successUrl'] : null,
			cancelUrl: isset($data['cancelUrl']) ? (string) $data['cancelUrl'] : null,
			customerUUID: isset($data['customerUUID']) ? (string) $data['customerUUID'] : null,
			customerReference: isset($data['customerReference']) ? (string) $data['customerReference'] : null,
			trialPeriod: $trial instanceof Duration
				? $trial
				: (is_array($trial) ? Duration::fromArray($trial) : null),
			minPeriods: isset($data['minPeriods']) ? (int) $data['minPeriods'] : null,
		);
	}

	/**
	 * Subscriptions always bill against a stored product, so an inline product is not
	 * accepted here even though the parent payload allows one.
	 *
	 * @throws ValidationException
	 */
	public function validate(): void
	{
		if ($this->productId === null && $this->productReference === null) {
			throw new ValidationException(
				'Either productId or productReference must be provided for a subscription',
			);
		}

		if ($this->productId !== null && $this->productId <= 0) {
			throw new ValidationException('Product ID must be positive');
		}

		if ($this->minPeriods !== null && $this->minPeriods <= 0) {
			throw new ValidationException('Minimum periods must be positive');
		}
	}
}
