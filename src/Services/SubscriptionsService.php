<?php

declare(strict_types=1);

namespace QBitFlow\Services;

use Generator;
use QBitFlow\Http\Requester;
use QBitFlow\Models\Bill;
use QBitFlow\Models\BillingState;
use QBitFlow\Models\Subscription;
use QBitFlow\Models\SubscriptionCancellation;
use QBitFlow\Page;
use QBitFlow\Params\BillListParams;
use QBitFlow\Params\CancelSubscriptionParams;
use QBitFlow\Params\ReadParams;
use QBitFlow\Params\SubscriptionListParams;
use QBitFlow\RequestOptions;
use QBitFlow\Support\Validator;

/**
 * Reads subscriptions and their bills, cancels them, and runs a test billing.
 *
 * Grant access while now < `currentPeriodEnd`, whatever the status.
 */
final class SubscriptionsService extends Service
{
	/**
	 * One page of the space's subscriptions (`GET /transaction/subscriptions`; newest first,
	 * cancelled ones included, page size 20 by default, at most 100).
	 *
	 * @return Page<Subscription>
	 */
	public function list(?SubscriptionListParams $params = null, ?RequestOptions $options = null): Page
	{
		$params?->validate();

		return $this->requester->call('GET', '/transaction/subscriptions', Requester::page(Subscription::fromArray(...)), $params?->toQuery() ?? [], options: $options);
	}

	/**
	 * Every subscription `list()` returns, the pages fetched lazily.
	 *
	 * @return Generator<int,Subscription>
	 */
	public function iterate(?SubscriptionListParams $params = null, ?RequestOptions $options = null): Generator
	{
		$params ??= new SubscriptionListParams();

		return Requester::walk($params->cursor, fn (?string $cursor): Page => $this->list($params->withCursor($cursor), $options));
	}

	/**
	 * A subscription by its id (`GET /transaction/subscription/:uuid`; `sub@…` or the bare
	 * UUID), whatever its status (a cancelled one too). Before its checkout completes it is a 404.
	 */
	public function get(string $uuid, ?ReadParams $params = null, ?RequestOptions $options = null): Subscription
	{
		Validator::pathTxId('uuid', $uuid);
		$params?->validate();

		return $this->requester->call('GET', Requester::path('/transaction/subscription/%s', $uuid), Requester::one(Subscription::fromArray(...)), $params?->toQuery() ?? [], options: $options);
	}

	/** A subscription by its checkout's reference (`GET /transaction/subscription/reference/subscription/:reference`). */
	public function getByReference(string $reference, ?RequestOptions $options = null): Subscription
	{
		Validator::pathRequired('reference', $reference);

		return $this->requester->call('GET', Requester::path('/transaction/subscription/reference/subscription/%s', $reference),
			Requester::one(Subscription::fromArray(...)), options: $options);
	}

	/**
	 * One page of a subscription's bills (`GET /transaction/subscription/:uuid/bills`; newest
	 * first, page size 20 by default, at most 100).
	 *
	 * @return Page<Bill>
	 */
	public function listBills(string $uuid, ?BillListParams $params = null, ?RequestOptions $options = null): Page
	{
		Validator::pathTxId('uuid', $uuid);
		$params?->validate();

		return $this->requester->call('GET', Requester::path('/transaction/subscription/%s/bills', $uuid), Requester::page(Bill::fromArray(...)), $params?->toQuery() ?? [], options: $options);
	}

	/**
	 * Every bill of a subscription, the pages fetched lazily.
	 *
	 * @return Generator<int,Bill>
	 */
	public function iterateBills(string $uuid, ?BillListParams $params = null, ?RequestOptions $options = null): Generator
	{
		$params ??= new BillListParams();

		return Requester::walk($params->cursor, fn (?string $cursor): Page => $this->listBills($uuid, $params->withCursor($cursor), $options));
	}

	/** One bill by its id (`GET /transaction/subscription/bill/:uuid`; `sub-hist@…` or the bare UUID). */
	public function getBill(string $billUuid, ?ReadParams $params = null, ?RequestOptions $options = null): Bill
	{
		Validator::pathTxId('billUuid', $billUuid);
		$params?->validate();

		return $this->requester->call('GET', Requester::path('/transaction/subscription/bill/%s', $billUuid), Requester::one(Bill::fromArray(...)), $params?->toQuery() ?? [], options: $options);
	}

	/**
	 * A subscription's 10 most recent bills, newest first, as its customer's page shows them
	 * (`GET /transaction/subscription/history/:subscriptionUuid`, a public route). The fields the
	 * API only returns to the subscription's owner (metadata, customerUuid, customerReference,
	 * userUuid, paidMinUnits, refund…) are empty here: use `listBills()` for the full bills.
	 *
	 * @return list<Bill>
	 */
	public function getPublicHistory(string $subscriptionUuid, ?RequestOptions $options = null): array
	{
		Validator::pathTxId('subscriptionUuid', $subscriptionUuid);

		return $this->requester->call('GET', Requester::path('/transaction/subscription/history/%s', $subscriptionUuid), Requester::list(Bill::fromArray(...)), options: $options);
	}

	/**
	 * Cancels a subscription without its customer signing
	 * (`POST /transaction/subscription/processing/force-cancel/:uuid`). Not retried.
	 *
	 * By default it is cancelled at once (status `cancelled`, reason `merchant`); `immediate`
	 * false stops it now and cancels it at the end of its paid period. `pending` is true when the
	 * API answered 202: the on-chain cancel is still confirming (`subscription.statusChanged`
	 * tells the end).
	 */
	public function cancel(string $uuid, ?CancelSubscriptionParams $params = null, ?RequestOptions $options = null): SubscriptionCancellation
	{
		Validator::pathTxId('uuid', $uuid);
		$params?->validate();

		[$subscription, $status] = $this->requester->callWithStatus('POST', Requester::path('/transaction/subscription/processing/force-cancel/%s', $uuid),
			Requester::one(Subscription::fromArray(...)), $params?->toQuery() ?? [], options: $options);

		return new SubscriptionCancellation($subscription, $status === 202);
	}

	/**
	 * Bills a test subscription now (`POST /transaction/subscription/processing/execute-billing/:uuid`)
	 * and returns the bill's billing state. Test mode only. Not retried.
	 */
	public function executeTestBilling(string $uuid, ?RequestOptions $options = null): BillingState
	{
		Validator::pathTxId('uuid', $uuid);

		return $this->requester->call('POST', Requester::path('/transaction/subscription/processing/execute-billing/%s', $uuid),
			Requester::one(BillingState::fromArray(...)), options: $options);
	}
}
