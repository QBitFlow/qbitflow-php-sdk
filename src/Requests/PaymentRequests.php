<?php

declare(strict_types=1);

namespace QBitFlow\Requests;

use QBitFlow\Dto\CombinedPayment;
use QBitFlow\Dto\Customer;
use QBitFlow\Dto\Payment;
use QBitFlow\Dto\Session\CreatePaymentSessionDto;
use QBitFlow\Dto\Session\LinkResponse;
use QBitFlow\Dto\Session\SessionCheckout;
use QBitFlow\Exceptions\ValidationException;
use QBitFlow\Http\Transport;
use QBitFlow\Support\CursorData;

/**
 * One-time cryptocurrency payments.
 */
final class PaymentRequests extends Request
{
	private const BASE_ROUTE = '/transaction';

	private readonly SessionRequests $sessions;

	public function __construct(Transport $transport)
	{
		parent::__construct($transport);

		$this->sessions = new SessionRequests($transport);
	}

	/**
	 * Create a one-time payment session and get back the link to send your customer.
	 *
	 * Identify the product by `productId`, by `productReference`, or inline with
	 * `productName` + `description` + `price`.
	 *
	 * ```php
	 * $payment = $client->oneTimePayments->createSession(new CreatePaymentSessionDto(
	 *     productId: 1,
	 *     customerUUID: 'customer-uuid',
	 *     successUrl: 'https://example.com/success',
	 * ));
	 *
	 * return redirect($payment->link);
	 * ```
	 *
	 * Webhook URLs are configured in the dashboard, not per session.
	 *
	 * @param CreatePaymentSessionDto|array<string,mixed> $session
	 *
	 * @throws ValidationException If neither a product nor inline product details are given.
	 */
	public function createSession(CreatePaymentSessionDto|array $session): LinkResponse
	{
		$dto = is_array($session) ? CreatePaymentSessionDto::fromArray($session) : $session;
		$dto->validate();

		return $this->sessions->createForPayment($dto);
	}

	/**
	 * Get a payment session by UUID.
	 *
	 * @param bool|null $closeToExpireError Whether the API should error when the session is
	 *                                      close to expiry. Defaults to true server-side.
	 */
	public function getSession(string $sessionUUID, ?bool $closeToExpireError = null): SessionCheckout
	{
		return $this->sessions->get($sessionUUID, $closeToExpireError);
	}

	/**
	 * Get a settled payment by UUID.
	 */
	public function get(string $paymentUUID): Payment
	{
		$this->requireNonEmpty($paymentUUID, 'Payment UUID');

		return Payment::fromArray(
			$this->transport->get(self::BASE_ROUTE . '/payment/' . $this->encode($paymentUUID)),
		);
	}

	/**
	 * Get a settled payment by the reference you assigned when creating the session.
	 *
	 * Resolves a payment from your own order or invoice ID, so you never have to store
	 * QBitFlow's UUID.
	 */
	public function getByReference(string $reference): Payment
	{
		$this->requireNonEmpty($reference, 'Payment reference');

		return Payment::fromArray($this->transport->get(
			self::BASE_ROUTE . '/payment/reference/' . $this->encode($reference),
		));
	}

	/**
	 * List one-time payments, one page at a time.
	 *
	 * @return CursorData<Payment>
	 */
	public function getAll(?int $limit = null, ?string $cursor = null): CursorData
	{
		return CursorData::fromArray(
			$this->transport->get(
				self::BASE_ROUTE . '/payments',
				CursorData::queryParams($limit, $cursor),
			),
			Payment::fromArray(...),
		);
	}

	/**
	 * List one-time payments and subscription billings together, one page at a time.
	 *
	 * ```php
	 * $page = $client->oneTimePayments->getAllCombined(limit: 20);
	 *
	 * foreach ($page as $entry) {
	 *     echo $entry->source->value, ' ', $entry->amount, PHP_EOL;
	 * }
	 * ```
	 *
	 * @return CursorData<CombinedPayment>
	 */
	public function getAllCombined(?int $limit = null, ?string $cursor = null): CursorData
	{
		return CursorData::fromArray(
			$this->transport->get(
				self::BASE_ROUTE . '/payments/combined',
				CursorData::queryParams($limit, $cursor),
			),
			CombinedPayment::fromArray(...),
		);
	}

	/**
	 * Get the customer behind a transaction.
	 */
	public function getCustomerForTransaction(string $transactionUUID): Customer
	{
		$this->requireNonEmpty($transactionUUID, 'Transaction UUID');

		return Customer::fromArray($this->transport->get(
			self::BASE_ROUTE . '/customer/' . $this->encode($transactionUUID),
		));
	}
}
