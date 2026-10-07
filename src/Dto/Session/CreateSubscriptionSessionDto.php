<?php

declare(strict_types=1);

namespace QBitFlow\Dto\Session;

use QBitFlow\Exceptions\ValidationException;
use QBitFlow\Support\Duration;
use QBitFlow\Support\Validate;

/**
 * Payload for creating a subscription session.
 *
 * The product is identified exactly as for a one-time payment: by `productId`, by
 * `productReference`, or inline with `productName` + `description` + `price` (a "ghost"
 * product created behind the scenes). `frequency` is required.
 *
 * ```php
 * $link = $client->subscriptions->createSession(new CreateSubscriptionSessionDto(
 *     frequency: Duration::months(1),
 *     productId: 1,
 *     trialPeriod: Duration::days(7),
 * ));
 * ```
 */
final class CreateSubscriptionSessionDto extends CreatePaymentSessionDto
{
	public function __construct(
		/** Billing frequency. Required; its value must be at least 1. */
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
		/** Trial period before the first billing. A value of 0 means no trial. */
		public readonly ?Duration $trialPeriod = null,
		/**
		 * Minimum number of billing periods the subscriber must complete, 0 to 4294967295.
		 * `0` means no minimum and is left off the request.
		 */
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
	 * @param array<array-key,mixed> $data
	 *
	 * @throws ValidationException When `frequency` is missing or a value has the wrong type.
	 */
	public static function fromArray(array $data): static
	{
		return new self(
			self::durationArg($data, 'frequency') ?? throw new ValidationException('Frequency is required'),
			...self::sessionArguments($data),
			trialPeriod: self::durationArg($data, 'trialPeriod'),
			minPeriods: self::intArg($data, 'minPeriods'),
		);
	}

	/**
	 * Subscriptions accept the same product forms as a one-time payment: a stored product
	 * (`productId` / `productReference`) **or** an inline ghost product
	 * (`productName` + `description` + `price`), validated by the parent, plus the
	 * subscription-only rules on `frequency` and `minPeriods`.
	 *
	 * @throws ValidationException
	 */
	public function validate(): void
	{
		parent::validate();

		if ($this->frequency->value < 1) {
			throw new ValidationException('Frequency value must be at least 1');
		}

		if ($this->minPeriods !== null) {
			Validate::intRange('minPeriods', $this->minPeriods, 0, Validate::UINT32_MAX);
		}
	}

	public function toArray(): array
	{
		$out = parent::toArray();

		// `minPeriods` is `omitempty` on the API: 0 means "no minimum".
		if (($out['minPeriods'] ?? null) === 0) {
			unset($out['minPeriods']);
		}

		return $out;
	}

	/**
	 * @param array<array-key,mixed> $data
	 *
	 * @throws ValidationException
	 */
	private static function durationArg(array $data, string $key): ?Duration
	{
		$value = $data[$key] ?? null;

		return match (true) {
			$value === null, $value instanceof Duration => $value,
			is_array($value) => Duration::fromArray($value),
			default => throw new ValidationException("{$key} must be a Duration"),
		};
	}
}
