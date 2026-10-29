<?php

declare(strict_types=1);

namespace QBitFlow\Services;

use Generator;
use QBitFlow\Http\Requester;
use QBitFlow\Models\Bill;
use QBitFlow\Models\Subscription;
use QBitFlow\Models\SubscriptionCancellation;
use QBitFlow\Models\TestBill;
use QBitFlow\Page;
use QBitFlow\Params\BillListParams;
use QBitFlow\Params\CancelSubscriptionParams;
use QBitFlow\Params\ReadParams;
use QBitFlow\Params\SubscriptionListParams;
use QBitFlow\Params\UpdateSubscriptionParams;
use QBitFlow\RequestOptions;
use QBitFlow\Support\Validator;

/**
 * Reads subscriptions and their bills, updates their metadata, cancels them, and runs a test
 * bill (`/subscriptions…`, `/bills…`).
 *
 * Grant access while now < `accessUntil`, whatever the status ({@see Subscription::hasAccess()}).
 */
final class SubscriptionsService extends Service
{
	/**
	 * One page of the space's subscriptions (`GET /subscriptions`; newest first, cancelled ones
	 * included, page size 20 by default, at most 100). A checkout not completed yet is no
	 * subscription. Filters: `status`, `reference`, customer, product, creation window, and
	 * `includeMembers` or `userUuid` (organization key).
	 *
	 * @return Page<Subscription>
	 */
	public function list(?SubscriptionListParams $params = null, ?RequestOptions $options = null): Page
	{
		$params?->validate();

		return $this->requester->call('GET', '/subscriptions', Requester::page(Subscription::fromArray(...)), $params?->toQuery() ?? [], options: $options);
	}

	/**
	 * Every subscription `list()` returns, the pages fetched lazily, filters and page size kept.
	 *
	 * @return Generator<int,Subscription>
	 */
	public function iterate(?SubscriptionListParams $params = null, ?RequestOptions $options = null): Generator
	{
		$params ??= new SubscriptionListParams();

		return Requester::walk($params->cursor, fn (?string $cursor): Page => $this->list($params->withCursor($cursor), $options));
	}

	/**
	 * A subscription by its id (`GET /subscriptions/:uuid`; `sub@…` or the bare UUID), whatever
	 * its status (a cancelled one too). `includeMembers` reads a member's from the organization's
	 * space. Before its checkout completes it is a {@see \QBitFlow\Exceptions\NotFoundException}:
	 * read the checkout session instead.
	 */
	public function get(string $uuid, ?ReadParams $params = null, ?RequestOptions $options = null): Subscription
	{
		Validator::pathTxId('uuid', $uuid);
		$params?->validate();

		return $this->requester->call('GET', Requester::path('/subscriptions/%s', $uuid), Requester::one(Subscription::fromArray(...)), $params?->toQuery() ?? [], options: $options);
	}

	/**
	 * The space's subscription with the reference given to its checkout session, whatever its
	 * status: runs `list()`'s reference filter (`GET /subscriptions?reference=`) and returns its
	 * one subscription. No subscription with that reference is a
	 * {@see \QBitFlow\Exceptions\NotFoundException} (status 404, code `not_found`).
	 */
	public function getByReference(string $reference, ?RequestOptions $options = null): Subscription
	{
		return $this->requester->getByReference('/subscriptions', 'subscription', $reference, Subscription::fromArray(...),
			static fn (Subscription $subscription): ?string => $subscription->reference, $options);
	}

	/**
	 * Changes a subscription's metadata (`PATCH /subscriptions/:uuid`), merged into its own (a key
	 * set to `''` deletes it; at most 50 keys once merged), cancelled ones too, and returns the
	 * subscription. Its price, terms and status don't change here. A member's key needs the
	 * organization's `members.subscriptions` policy (403 `policy_disabled`). Not retried.
	 */
	public function update(string $uuid, UpdateSubscriptionParams $params, ?RequestOptions $options = null): Subscription
	{
		Validator::pathTxId('uuid', $uuid);
		$params->validate();

		return $this->requester->call('PATCH', Requester::path('/subscriptions/%s', $uuid), Requester::one(Subscription::fromArray(...)), body: $params->toArray(), options: $options);
	}

	/**
	 * One page of a subscription's bills (`GET /subscriptions/:uuid/bills`; newest first, page
	 * size 20 by default, at most 100).
	 *
	 * @return Page<Bill>
	 */
	public function listBills(string $uuid, ?BillListParams $params = null, ?RequestOptions $options = null): Page
	{
		Validator::pathTxId('uuid', $uuid);
		$params?->validate();

		return $this->requester->call('GET', Requester::path('/subscriptions/%s/bills', $uuid), Requester::page(Bill::fromArray(...)), $params?->toQuery() ?? [], options: $options);
	}

	/**
	 * Every bill of a subscription, the pages fetched lazily, the page size kept.
	 *
	 * @return Generator<int,Bill>
	 */
	public function iterateBills(string $uuid, ?BillListParams $params = null, ?RequestOptions $options = null): Generator
	{
		$params ??= new BillListParams();

		return Requester::walk($params->cursor, fn (?string $cursor): Page => $this->listBills($uuid, $params->withCursor($cursor), $options));
	}

	/**
	 * One bill by its id (`GET /bills/:uuid`; `sub-hist@…` or the bare UUID). `includeMembers`
	 * reads a member's from the organization's space.
	 */
	public function getBill(string $billUuid, ?ReadParams $params = null, ?RequestOptions $options = null): Bill
	{
		Validator::pathTxId('billUuid', $billUuid);
		$params?->validate();

		return $this->requester->call('GET', Requester::path('/bills/%s', $billUuid), Requester::one(Bill::fromArray(...)), $params?->toQuery() ?? [], options: $options);
	}

	/**
	 * Cancels a subscription without its customer signing (`POST /subscriptions/:uuid/cancel`).
	 * Not retried.
	 *
	 * By default (no params, or `immediate` null or true) it is cancelled at once (status
	 * `cancelled`, `cancellationReason` `merchant`). `immediate` false stops it now (status
	 * `stopped`, never billed again) and cancels it when its paid period ends. The answer is the
	 * subscription as the cancellation left it; `pending` is true when the API answered 202: the
	 * on-chain cancel is still confirming, the status is not updated yet, and
	 * `subscription.statusChanged` tells the end (the cancellation goes on whatever happens).
	 *
	 * Errors: 404 for a cancelled or another space's subscription; 409
	 * `subscription_already_stopped_or_inactive` (`immediate` false on a stopped one); 400 on
	 * `immediate` for a trial with `immediate` false; 409 `tx_already_sent` while a cancellation runs.
	 */
	public function cancel(string $uuid, ?CancelSubscriptionParams $params = null, ?RequestOptions $options = null): SubscriptionCancellation
	{
		Validator::pathTxId('uuid', $uuid);
		$params?->validate();

		[$subscription, $status] = $this->requester->callWithStatus('POST', Requester::path('/subscriptions/%s/cancel', $uuid),
			Requester::one(Subscription::fromArray(...)), $params?->toQuery() ?? [], options: $options);

		return new SubscriptionCancellation($subscription, $status === 202);
	}

	/**
	 * Bills a test subscription now (`POST /subscriptions/:uuid/test-bill`) and returns where the
	 * test bill stands once its attempt settled (done, waiting after a failure, or still running
	 * after 2 minutes). Its events are a live bill's. Test mode only: a live subscription is a
	 * {@see \QBitFlow\Exceptions\BadRequestException} (400 `test_mode_only`); a bill not due yet
	 * is a 409 `payment_not_due`. Not retried.
	 */
	public function testBill(string $uuid, ?RequestOptions $options = null): TestBill
	{
		Validator::pathTxId('uuid', $uuid);

		return $this->requester->call('POST', Requester::path('/subscriptions/%s/test-bill', $uuid),
			Requester::one(TestBill::fromArray(...)), options: $options);
	}
}
