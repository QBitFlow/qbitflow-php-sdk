<?php

declare(strict_types=1);

namespace QBitFlow\Services;

use Generator;
use QBitFlow\Http\Requester;
use QBitFlow\Models\CombinedPayment;
use QBitFlow\Models\Payment;
use QBitFlow\Page;
use QBitFlow\Params\CombinedPaymentListParams;
use QBitFlow\Params\PaymentListParams;
use QBitFlow\Params\ReadParams;
use QBitFlow\RequestOptions;
use QBitFlow\Support\Validator;

/**
 * Reads the one-time payments and the combined feed of payments and bills.
 */
final class PaymentsService extends Service
{
	/**
	 * One page of the space's one-time payments (`GET /transaction/payments`; newest first, page
	 * size 10 by default, at most 50). A payment exists once its transaction is confirmed.
	 *
	 * @return Page<Payment>
	 */
	public function list(?PaymentListParams $params = null, ?RequestOptions $options = null): Page
	{
		$params?->validate();

		return $this->requester->call('GET', '/transaction/payments', Requester::page(Payment::fromArray(...)), $params?->toQuery() ?? [], options: $options);
	}

	/**
	 * Every payment `list()` returns, the pages fetched lazily.
	 *
	 * @return Generator<int,Payment>
	 */
	public function iterate(?PaymentListParams $params = null, ?RequestOptions $options = null): Generator
	{
		$params ??= new PaymentListParams();

		return Requester::walk($params->cursor, fn (?string $cursor): Page => $this->list($params->withCursor($cursor), $options));
	}

	/**
	 * One page of the combined feed of one-time payments and subscription bills
	 * (`GET /transaction/payments/combined`; newest first, page size 10 by default, at most 50).
	 *
	 * @return Page<CombinedPayment>
	 */
	public function listCombined(?CombinedPaymentListParams $params = null, ?RequestOptions $options = null): Page
	{
		$params?->validate();

		return $this->requester->call('GET', '/transaction/payments/combined', Requester::page(CombinedPayment::fromArray(...)), $params?->toQuery() ?? [], options: $options);
	}

	/**
	 * Every row `listCombined()` returns, the pages fetched lazily.
	 *
	 * @return Generator<int,CombinedPayment>
	 */
	public function iterateCombined(?CombinedPaymentListParams $params = null, ?RequestOptions $options = null): Generator
	{
		$params ??= new CombinedPaymentListParams();

		return Requester::walk($params->cursor, fn (?string $cursor): Page => $this->listCombined($params->withCursor($cursor), $options));
	}

	/**
	 * A one-time payment by its id (`GET /transaction/payment/:uuid`; `pay@…` or the bare UUID).
	 * `includeMembers` reads a member's payment from the organization's space.
	 */
	public function get(string $uuid, ?ReadParams $params = null, ?RequestOptions $options = null): Payment
	{
		Validator::pathTxId('uuid', $uuid);
		$params?->validate();

		return $this->requester->call('GET', Requester::path('/transaction/payment/%s', $uuid), Requester::one(Payment::fromArray(...)), $params?->toQuery() ?? [], options: $options);
	}

	/** A one-time payment by its checkout's reference (`GET /transaction/payment/reference/:reference`). */
	public function getByReference(string $reference, ?RequestOptions $options = null): Payment
	{
		Validator::pathRequired('reference', $reference);

		return $this->requester->call('GET', Requester::path('/transaction/payment/reference/%s', $reference), Requester::one(Payment::fromArray(...)), options: $options);
	}
}
