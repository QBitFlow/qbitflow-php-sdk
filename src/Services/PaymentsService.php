<?php

declare(strict_types=1);

namespace QBitFlow\Services;

use Generator;
use QBitFlow\Http\Requester;
use QBitFlow\Models\Payment;
use QBitFlow\Page;
use QBitFlow\Params\PaymentListParams;
use QBitFlow\Params\ReadParams;
use QBitFlow\RequestOptions;
use QBitFlow\Support\Validator;

/**
 * Reads the one-time payments (`/payments…`). The feed of payments and bills together is
 * `$client->transactions`.
 */
final class PaymentsService extends Service
{
	/**
	 * One page of the space's one-time payments (`GET /payments`; newest first, page size 20 by
	 * default, at most 100). A payment exists once its transaction is confirmed. Filters:
	 * customer, product, creation window (both bounds excluded), `reference`, `refunded`, and
	 * `includeMembers` or `userUuid` (organization key without On-Behalf-Of).
	 *
	 * @return Page<Payment>
	 */
	public function list(?PaymentListParams $params = null, ?RequestOptions $options = null): Page
	{
		$params?->validate();

		return $this->requester->call('GET', '/payments', Requester::page(Payment::fromArray(...)), $params?->toQuery() ?? [], options: $options);
	}

	/**
	 * Every payment `list()` returns, the pages fetched lazily, filters and page size kept.
	 *
	 * @return Generator<int,Payment>
	 */
	public function iterate(?PaymentListParams $params = null, ?RequestOptions $options = null): Generator
	{
		$params ??= new PaymentListParams();

		return Requester::walk($params->cursor, fn (?string $cursor): Page => $this->list($params->withCursor($cursor), $options));
	}

	/**
	 * A one-time payment by its id (`GET /payments/:uuid`; `pay@…` or the bare UUID).
	 * `includeMembers` reads a member's payment from the organization's space. A payment not
	 * confirmed yet (read its checkout session), or another space's, is a
	 * {@see \QBitFlow\Exceptions\NotFoundException}.
	 */
	public function get(string $uuid, ?ReadParams $params = null, ?RequestOptions $options = null): Payment
	{
		Validator::pathTxId('uuid', $uuid);
		$params?->validate();

		return $this->requester->call('GET', Requester::path('/payments/%s', $uuid), Requester::one(Payment::fromArray(...)), $params?->toQuery() ?? [], options: $options);
	}

	/**
	 * The space's one-time payment with the reference given to its checkout session: runs
	 * `list()`'s reference filter (`GET /payments?reference=`) and returns its one payment. No
	 * payment with that reference (none, or not confirmed yet) is a
	 * {@see \QBitFlow\Exceptions\NotFoundException} (status 404, code `not_found`).
	 */
	public function getByReference(string $reference, ?RequestOptions $options = null): Payment
	{
		return $this->requester->getByReference('/payments', 'payment', $reference, Payment::fromArray(...),
			static fn (Payment $payment): ?string => $payment->reference, $options);
	}
}
