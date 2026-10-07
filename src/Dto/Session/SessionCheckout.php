<?php

declare(strict_types=1);

namespace QBitFlow\Dto\Session;

use QBitFlow\Enums\TransactionType;
use QBitFlow\Support\Cast;
use QBitFlow\Support\Dto;

/**
 * Fields common to every checkout session, whatever its type.
 *
 * Optional session fields the API omits when empty come back as their zero value (`''`,
 * `0`, `0.0`); only `customerUUID` is nullable.
 *
 * Sessions come in two concrete shapes — {@see OneTimePaymentSession} and
 * {@see SubscriptionSession}. Use {@see SessionCheckout::discriminate()} to build the
 * right one from a raw payload.
 */
abstract class SessionCheckout extends Dto
{
	/**
	 * @param list<int> $availableCurrencies
	 */
	public function __construct(
		/** Session UUID (`pay@` / `sub@`-prefixed). Identifies the payment on-chain. */
		public readonly string $uuid,
		/**
		 * Transaction type for this session, set by the server. A one-time payment reports
		 * `payment`; a subscription session reports `createSubscription` — not the short
		 * `subscription` form. A `TransactionType` member, or the raw string for a type this
		 * SDK does not know.
		 */
		public readonly TransactionType|string $txType,
		/** Product name. */
		public readonly string $productName,
		/** Product description. */
		public readonly string $description,
		/** Price in USD. */
		public readonly float $price,
		/** Name of the merchant organization. */
		public readonly string $organizationName,
		/** Organization ID. */
		public readonly int $organizationId,
		/** QBitFlow platform fee in basis points. */
		public readonly int $feeBps,
		/** Whether this is a test-mode session. */
		public readonly bool $test,
		/**
		 * IDs of the currencies accepted for this payment. Resolve them with
		 * {@see \QBitFlow\Requests\CurrencyRequests::getAllAvailable()}.
		 */
		public readonly array $availableCurrencies = [],
		/** Your own reference for the transaction; `''` when none was set. */
		public readonly string $reference = '',
		/** Product ID; `0` when the session was not created from a stored product ID. */
		public readonly int $productId = 0,
		/** Your own product reference; `''` when the product was not selected by reference. */
		public readonly string $productReference = '',
		/** Where to send the customer after a successful payment; `''` when not set. */
		public readonly string $successUrl = '',
		/** Where to send the customer after a cancelled or failed payment; `''` when not set. */
		public readonly string $cancelUrl = '',
		/** Additional organization fee in basis points; `0` when none. */
		public readonly int $organizationFeeBps = 0,
		/** ID of the user who created the payment link; `0` for an organization-level session. */
		public readonly int $userId = 0,
		/** Name of the user who created the payment link; `''` for an organization-level session. */
		public readonly string $userName = '',
		/** Pre-filled customer UUID; null when the customer is collected during checkout. */
		public readonly ?string $customerUUID = null,
		/** Your own customer reference, when the customer was pre-filled by reference; `''` otherwise. */
		public readonly string $customerReference = '',
	) {
	}

	/**
	 * Build the concrete session type matching a raw API payload.
	 *
	 * The server sets `txType` (`payment` or `createSubscription`), which decides when
	 * present. A payload without a recognised `txType` is told apart by `frequency`:
	 * subscription data carries one, one-time payment data does not.
	 *
	 * @param array<array-key,mixed> $data
	 *
	 * @throws \QBitFlow\Exceptions\ServerException When the payload does not have the session shape.
	 */
	public static function discriminate(array $data): self
	{
		$txType = $data['txType'] ?? null;

		if ($txType === TransactionType::CREATE_SUBSCRIPTION->value) {
			return SubscriptionSession::fromArray($data);
		}

		if ($txType === TransactionType::ONE_TIME_PAYMENT->value) {
			return OneTimePaymentSession::fromArray($data);
		}

		if (($data['frequency'] ?? null) !== null) {
			return SubscriptionSession::fromArray($data);
		}

		return OneTimePaymentSession::fromArray($data);
	}

	/** Whether this is a one-time payment session. */
	public function isPayment(): bool
	{
		return $this instanceof OneTimePaymentSession;
	}

	/**
	 * Constructor arguments shared by every session type, keyed by parameter name.
	 *
	 * @param array<array-key,mixed> $data
	 *
	 * @return array<string,mixed>
	 *
	 * @internal
	 */
	protected static function baseArguments(array $data): array
	{
		return [
			'uuid' => Cast::string($data, 'uuid'),
			'txType' => Cast::enum($data, 'txType', TransactionType::class),
			'productName' => Cast::string($data, 'productName'),
			'description' => Cast::string($data, 'description'),
			'price' => Cast::float($data, 'price'),
			'organizationName' => Cast::string($data, 'organizationName'),
			'organizationId' => Cast::int($data, 'organizationId'),
			'feeBps' => Cast::int($data, 'feeBps'),
			'test' => Cast::bool($data, 'test'),
			'availableCurrencies' => Cast::intList($data, 'availableCurrencies'),
			'reference' => Cast::string($data, 'reference'),
			'productId' => Cast::int($data, 'productId'),
			'productReference' => Cast::string($data, 'productReference'),
			'successUrl' => Cast::string($data, 'successUrl'),
			'cancelUrl' => Cast::string($data, 'cancelUrl'),
			'organizationFeeBps' => Cast::int($data, 'organizationFeeBps'),
			'userId' => Cast::int($data, 'userId'),
			'userName' => Cast::string($data, 'userName'),
			'customerUUID' => Cast::nullableString($data, 'customerUUID'),
			'customerReference' => Cast::string($data, 'customerReference'),
		];
	}
}
