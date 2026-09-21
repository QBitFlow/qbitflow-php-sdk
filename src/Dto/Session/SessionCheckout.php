<?php

declare(strict_types=1);

namespace QBitFlow\Dto\Session;

use QBitFlow\Enums\TransactionType;
use QBitFlow\Support\Cast;
use QBitFlow\Support\Dto;

/**
 * Fields common to every checkout session, whatever its type.
 *
 * Properties marked "authenticated only" are omitted when the session is fetched from
 * the public checkout page.
 *
 * Sessions come in three concrete shapes — {@see OneTimePaymentSession},
 * {@see SubscriptionSession} and {@see PaygSubscriptionSession}. Use
 * {@see SessionCheckout::discriminate()} to build the right one from a raw payload.
 */
abstract class SessionCheckout extends Dto
{
	public function __construct(
		/** Session UUID. Identifies the payment on-chain. */
		public readonly string $uuid,
		/** Product name. */
		public readonly string $productName,
		/** Product description. */
		public readonly string $description,
		/** Price in USD. */
		public readonly float $price,
		/** Organization name, set by the server. */
		public readonly string $organizationName,
		/** Whether this is a test-mode session. */
		public readonly bool $test,
		/**
		 * IDs of the currencies accepted for this payment. Resolve them with
		 * {@see \QBitFlow\Requests\CurrencyRequests::getAllAvailable()}.
		 *
		 * @var list<int>
		 */
		public readonly array $availableCurrencies = [],
		/** Your own reference for the transaction, set when the session was created. */
		public readonly ?string $reference = null,
		/** Product ID, when the session was created from a stored product. */
		public readonly ?int $productId = null,
		/** Your own product reference, when the product was selected by reference. */
		public readonly ?string $productReference = null,
		/** Where to send the customer after a successful payment. */
		public readonly ?string $successUrl = null,
		/** Where to send the customer after a cancelled or failed payment. */
		public readonly ?string $cancelUrl = null,
		/**
		 * Transaction type for this session, set by the server. A one-time payment reports
		 * `payment`; a subscription session reports `createSubscription` - not the short
		 * `subscription` form.
		 */
		public readonly ?TransactionType $txType = null,
		/** Organization ID. Authenticated only. */
		public readonly ?int $organizationId = null,
		/** QBitFlow platform fee in basis points. Authenticated only. */
		public readonly ?int $feeBps = null,
		/** Additional organization fee in basis points. Authenticated only. */
		public readonly ?int $organizationFeeBps = null,
		/** ID of the user who created the payment link. Authenticated only. */
		public readonly ?int $userId = null,
		/** Name of the user who created the payment link. Authenticated only. */
		public readonly ?string $userName = null,
		/** Pre-filled customer UUID. Authenticated only. */
		public readonly ?string $customerUUID = null,
		/** Your own customer reference, when the customer was pre-filled by reference. */
		public readonly ?string $customerReference = null,
	) {
	}

	/**
	 * Build the concrete session type matching a raw API payload.
	 *
	 * The variants are told apart by `frequency`: a structured object means a
	 * pay-as-you-go session, a number means a recurring subscription, and its absence
	 * means a one-time payment.
	 *
	 * @param array<string,mixed> $data
	 */
	public static function discriminate(array $data): self
	{
		$frequency = $data['frequency'] ?? null;

		if (is_array($frequency)) {
			return PaygSubscriptionSession::fromArray($data);
		}

		if ($frequency !== null) {
			return SubscriptionSession::fromArray($data);
		}

		return OneTimePaymentSession::fromArray($data);
	}

	/**
	 * Arguments shared by every session type, in constructor order.
	 *
	 * @param array<string,mixed> $data
	 *
	 * @return array<int,mixed>
	 *
	 * @internal
	 */
	protected static function baseArguments(array $data): array
	{
		return [
			Cast::string($data, 'uuid'),
			Cast::string($data, 'productName'),
			Cast::string($data, 'description'),
			Cast::float($data, 'price'),
			Cast::string($data, 'organizationName'),
			Cast::bool($data, 'test'),
			Cast::intList($data['availableCurrencies'] ?? []),
			Cast::nullableString($data, 'reference'),
			Cast::nullableInt($data, 'productId'),
			Cast::nullableString($data, 'productReference'),
			Cast::nullableString($data, 'successUrl'),
			Cast::nullableString($data, 'cancelUrl'),
			Cast::nullableEnum($data, 'txType', TransactionType::class),
			Cast::nullableInt($data, 'organizationId'),
			Cast::nullableInt($data, 'feeBps'),
			Cast::nullableInt($data, 'organizationFeeBps'),
			Cast::nullableInt($data, 'userId'),
			Cast::nullableString($data, 'userName'),
			Cast::nullableString($data, 'customerUUID'),
			Cast::nullableString($data, 'customerReference'),
		];
	}
}
