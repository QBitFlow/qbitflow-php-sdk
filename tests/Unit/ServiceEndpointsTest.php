<?php

declare(strict_types=1);

namespace QBitFlow\Tests\Unit;

use Closure;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use QBitFlow\Enums\BillingOutcome;
use QBitFlow\Enums\BillingStage;
use QBitFlow\Enums\CheckoutSessionStatusValue;
use QBitFlow\Enums\CombinedPaymentSource;
use QBitFlow\Enums\EventType;
use QBitFlow\Enums\FailureCategory;
use QBitFlow\Enums\FailureKind;
use QBitFlow\Enums\InvitationStatus;
use QBitFlow\Enums\RefundStatus;
use QBitFlow\Enums\SubscriptionStatus;
use QBitFlow\Enums\WebhookPayloadVersion;
use QBitFlow\Events\WebhookTestEvent;
use QBitFlow\Exceptions\AuthenticationException;
use QBitFlow\Exceptions\BadRequestException;
use QBitFlow\Exceptions\ConflictException;
use QBitFlow\Exceptions\NotFoundException;
use QBitFlow\Exceptions\ServerException;
use QBitFlow\Exceptions\ValidationException;
use QBitFlow\Exceptions\WebhookSignatureException;
use QBitFlow\Models;
use QBitFlow\Page;
use QBitFlow\Params;
use QBitFlow\QBitFlow;
use QBitFlow\RequestOptions;
use QBitFlow\Tests\Support\Fixtures;
use QBitFlow\Tests\Support\MockHttpClient;
use QBitFlow\Tests\Support\TestCase;

/**
 * Every service method: the request it sends (verb, escaped path, query, body, headers), the
 * decoding of its answer, its retry policy, and the checks run before anything is sent.
 */
final class ServiceEndpointsTest extends TestCase
{
	private const UUID_A = '019eca82-5680-7b00-8000-0000000000c1';

	private const UUID_B = '019eca82-5680-7b00-8000-0000000000c2';

	private const PAY_ID = 'pay@019eca82-5680-7b00-8000-0000000000d1';

	private const SUB_ID = 'sub@019eca82-5680-7b00-8000-0000000000d2';

	private const BILL_ID = 'sub-hist@019eca82-5680-7b00-8000-0000000000d3';

	private const EVENT_ID = 'evt_bd1a913d-188e-5ac5-a2d6-5fd25cd67301';

	private const ODD_REF = 'a/b c@d';

	private const ODD_REF_ESCAPED = 'a%2Fb%20c@d';

	private const AFTER_Q = '2026-09-01T12%3A00%3A00%2B02%3A00';

	private const BEFORE_Q = '2026-10-01T00%3A00%3A00.5Z';

	private static function after(): DateTimeImmutable
	{
		return new DateTimeImmutable('2026-09-01T12:00:00+02:00');
	}

	private static function before(): DateTimeImmutable
	{
		return new DateTimeImmutable('2026-10-01T00:00:00.5Z');
	}

	/**
	 * name → [call, method, path, query, body, idempotent, reply, status, check, accept, contentType]
	 *
	 * @return array<string,array{0: Closure(QBitFlow, ?RequestOptions): mixed, 1: string, 2: string, 3: string, 4: ?string, 5: bool, 6: string, 7: int, 8: ?Closure, 9: string}>
	 */
	public static function routes(): array
	{
		$m = Fixtures::model(...);
		$page = Fixtures::page(...);
		$list = Fixtures::list(...);
		$r = static fn (Closure $call, string $method, string $path, string $reply, string $query = '', ?string $body = null, bool $idempotent = false,
			int $status = 200, ?Closure $check = null, string $accept = 'application/json'): array => [$call, $method, $path, $query, $body, $idempotent, $reply, $status, $check, $accept];

		return [
			'products.list' => $r(static fn (QBitFlow $c, ?RequestOptions $o) => $c->products->list(new Params\ProductListParams(includeHidden: true, subscription: false), $o),
				'GET', '/product', $list($m('Product')), 'includeHidden=true&subscription=false',
				check: static function (self $t, array $v): void {
					$t->assertCount(1, $v);
					$t->assertSame('p-1', $v[0]->uuid);
					$t->assertNotNull($v[0]->subscription);
				}),
			'products.list no params' => $r(static fn (QBitFlow $c, ?RequestOptions $o) => $c->products->list(null, $o), 'GET', '/product', '[]'),
			'products.create' => $r(static fn (QBitFlow $c, ?RequestOptions $o) => $c->products->create(new Params\CreateProductParams(name: 'Pro', price: 9.99, reference: 'pro',
				subscription: new Params\SubscriptionTermsParams(Models\Duration::months(1), new Models\Duration(), 3)), $o),
				'POST', '/product', $m('Product'), body: '{"name":"Pro","price":9.99,"reference":"pro","subscription":{"frequency":{"value":1,"unit":"months"},"trialPeriod":{"value":0},"minPeriods":3}}',
				idempotent: true, status: 201,
				check: static function (self $t, Models\Product $v): void {
					$t->assertSame('p-1', $v->uuid);
					$t->assertSame(9.99, $v->price);
				}),
			'products.get' => $r(static fn (QBitFlow $c, ?RequestOptions $o) => $c->products->get(self::UUID_A, $o), 'GET', '/product/uuid/' . self::UUID_A, $m('Product')),
			'products.getByReference' => $r(static fn (QBitFlow $c, ?RequestOptions $o) => $c->products->getByReference(self::ODD_REF, $o), 'GET', '/product/reference/' . self::ODD_REF_ESCAPED, $m('Product')),
			'products.update' => $r(static fn (QBitFlow $c, ?RequestOptions $o) => $c->products->update(self::UUID_A, new Params\UpdateProductParams(description: '', price: 12.0, isActive: false), $o),
				'PUT', '/product/' . self::UUID_A, $m('Product'), body: '{"description":"","price":12,"isActive":false}'),
			'products.delete' => $r(static fn (QBitFlow $c, ?RequestOptions $o) => $c->products->delete(self::UUID_A, $o), 'DELETE', '/product/' . self::UUID_A, '{"message":"Product deleted successfully"}'),

			'customers.create' => $r(static fn (QBitFlow $c, ?RequestOptions $o) => $c->customers->create(new Params\CreateCustomerParams(name: 'Ada', email: 'ada@example.com',
				lastName: 'Lovelace', phoneNumber: '+33 6 12 34 56 78', reference: 'crm-1'), $o),
				'POST', '/customer', $m('Customer'), body: '{"name":"Ada","lastName":"Lovelace","email":"ada@example.com","phoneNumber":"+33 6 12 34 56 78","reference":"crm-1"}',
				idempotent: true, status: 201,
				check: static function (self $t, Models\Customer $v): void {
					$t->assertSame('c-1', $v->uuid);
					$t->assertTrue($v->verified);
					$t->assertSame('m-1', $v->userUuid);
				}),
			'customers.update' => $r(static fn (QBitFlow $c, ?RequestOptions $o) => $c->customers->update(self::UUID_A, new Params\UpdateCustomerParams(email: 'new@example.com', phoneNumber: ''), $o),
				'PUT', '/customer/' . self::UUID_A, $m('Customer'), body: '{"email":"new@example.com","phoneNumber":""}'),
			'customers.list' => $r(static fn (QBitFlow $c, ?RequestOptions $o) => $c->customers->list(new Params\CustomerListParams(limit: 5, cursor: self::UUID_A, email: 'ada+1@example.com', verified: true), $o),
				'GET', '/customer/all', $page($m('Customer')), 'cursor=' . self::UUID_A . '&email=ada%2B1%40example.com&limit=5&verified=true',
				check: static function (self $t, Page $v): void {
					$t->assertCount(1, $v->items);
					$t->assertTrue($v->hasMore());
					$t->assertSame(self::UUID_B, $v->nextCursor);
				}),
			'customers.get' => $r(static fn (QBitFlow $c, ?RequestOptions $o) => $c->customers->get(self::UUID_A, $o), 'GET', '/customer/uuid/' . self::UUID_A, $m('Customer')),
			'customers.getByEmail' => $r(static fn (QBitFlow $c, ?RequestOptions $o) => $c->customers->getByEmail('ada+1@example.com', $o), 'GET', '/customer/email/ada+1@example.com', $m('Customer')),
			'customers.getByReference' => $r(static fn (QBitFlow $c, ?RequestOptions $o) => $c->customers->getByReference(self::ODD_REF, $o), 'GET', '/customer/reference/' . self::ODD_REF_ESCAPED, $m('Customer')),
			'customers.delete' => $r(static fn (QBitFlow $c, ?RequestOptions $o) => $c->customers->delete(self::UUID_A, $o), 'DELETE', '/customer/uuid/' . self::UUID_A, '{"message":"Customer deleted successfully"}'),

			'checkoutSessions.createPayment' => $r(static fn (QBitFlow $c, ?RequestOptions $o) => $c->checkoutSessions->createPayment(new Params\CreatePaymentSessionParams(
				productName: 'T-shirt', description: 'Blue', price: 4.5, reference: 'order-1', successUrl: 'https://shop.example/ok?id={{UUID}}',
				cancelUrl: 'https://shop.example/ko', customerReference: 'crm-1', expiresInMinutes: 30), $o),
				'POST', '/transaction/session-checkout/new/payment', $m('CheckoutSession'),
				body: '{"reference":"order-1","productName":"T-shirt","description":"Blue","price":4.5,"successUrl":"https://shop.example/ok?id={{UUID}}","cancelUrl":"https://shop.example/ko","customerReference":"crm-1","expiresInMinutes":30}',
				idempotent: true, status: 201,
				check: static function (self $t, Models\CheckoutSession $v): void {
					$t->assertSame('pay@1', $v->uuid);
					$t->assertNotSame('', $v->link);
					$t->assertNotNull($v->expiresAt);
				}),
			'checkoutSessions.createSubscription' => $r(static fn (QBitFlow $c, ?RequestOptions $o) => $c->checkoutSessions->createSubscription(new Params\CreateSubscriptionSessionParams(
				productUuid: self::UUID_A, customerUuid: self::UUID_B, frequency: Models\Duration::weeks(1), trialPeriod: Models\Duration::days(14), minPeriods: 0), $o),
				'POST', '/transaction/session-checkout/new/subscription', $m('CheckoutSession'),
				body: '{"productUuid":"' . self::UUID_A . '","customerUuid":"' . self::UUID_B . '","frequency":{"value":1,"unit":"weeks"},"trialPeriod":{"value":14,"unit":"days"},"minPeriods":0}',
				idempotent: true, status: 201),
			'checkoutSessions.getStatus' => $r(static fn (QBitFlow $c, ?RequestOptions $o) => $c->checkoutSessions->getStatus(self::PAY_ID, $o),
				'GET', '/transaction/session-checkout/' . self::PAY_ID . '/status', $m('CheckoutSessionStatus'),
				check: static function (self $t, Models\CheckoutSessionStatus $v): void {
					$t->assertSame(CheckoutSessionStatusValue::CREATED, $v->status);
					$t->assertNotNull($v->lastAttempt);
				}),
			'checkoutSessions.expire' => $r(static fn (QBitFlow $c, ?RequestOptions $o) => $c->checkoutSessions->expire(self::SUB_ID, $o),
				'POST', '/transaction/session-checkout/' . self::SUB_ID . '/expire', '{"uuid":"' . self::SUB_ID . '","status":"expired"}',
				check: static fn (self $t, Models\CheckoutSessionStatus $v) => $t->assertSame(CheckoutSessionStatusValue::EXPIRED, $v->status)),

			'payments.list' => $r(static fn (QBitFlow $c, ?RequestOptions $o) => $c->payments->list(new Params\PaymentListParams(limit: 50, cursor: self::UUID_A,
				customerUuid: self::UUID_B, productUuid: self::UUID_A, createdAfter: self::after(), createdBefore: self::before(), userUuid: self::MEMBER_UUID, refunded: false), $o),
				'GET', '/transaction/payments', $page($m('Payment')),
				'createdAfter=' . self::AFTER_Q . '&createdBefore=' . self::BEFORE_Q . '&cursor=' . self::UUID_A . '&customerUuid=' . self::UUID_B
					. '&limit=50&productUuid=' . self::UUID_A . '&refunded=false&userUuid=' . self::MEMBER_UUID,
				check: static function (self $t, Page $v): void {
					$t->assertSame('pay@1', $v->items[0]->uuid);
					$t->assertNotNull($v->items[0]->currency);
				}),
			'payments.listCombined' => $r(static fn (QBitFlow $c, ?RequestOptions $o) => $c->payments->listCombined(new Params\CombinedPaymentListParams(includeMembers: true,
				source: CombinedPaymentSource::SUBSCRIPTION_HISTORY, subscriptionUuid: self::SUB_ID, refunded: true), $o),
				'GET', '/transaction/payments/combined', $page($m('CombinedPayment')),
				'includeMembers=true&refunded=true&source=subscriptionHistory&subscriptionUuid=sub%40019eca82-5680-7b00-8000-0000000000d2',
				check: static fn (self $t, Page $v) => $t->assertSame(CombinedPaymentSource::SUBSCRIPTION_HISTORY, $v->items[0]->source)),
			'payments.get' => $r(static fn (QBitFlow $c, ?RequestOptions $o) => $c->payments->get(self::PAY_ID, new Params\ReadParams(includeMembers: true), $o),
				'GET', '/transaction/payment/' . self::PAY_ID, $m('Payment'), 'includeMembers=true',
				check: static function (self $t, Models\Payment $v): void {
					$t->assertSame('order-1', $v->reference);
					$t->assertNotNull($v->metadata->organizationFee);
				}),
			'payments.get bare uuid' => $r(static fn (QBitFlow $c, ?RequestOptions $o) => $c->payments->get(substr(self::PAY_ID, 4), null, $o),
				'GET', '/transaction/payment/' . substr(self::PAY_ID, 4), $m('Payment')),
			'payments.getByReference' => $r(static fn (QBitFlow $c, ?RequestOptions $o) => $c->payments->getByReference(self::ODD_REF, $o), 'GET', '/transaction/payment/reference/' . self::ODD_REF_ESCAPED, $m('Payment')),
			'failures.list' => $r(static fn (QBitFlow $c, ?RequestOptions $o) => $c->failures->list(new Params\FailureListParams(limit: 3, kind: FailureKind::BILL,
				category: FailureCategory::ALLOWANCE_EXHAUSTED, subscriptionUuid: self::SUB_ID), $o),
				'GET', '/transaction/failures', $page($m('Failure')),
				'category=allowanceExhausted&kind=bill&limit=3&subscriptionUuid=sub%40019eca82-5680-7b00-8000-0000000000d2',
				check: static fn (self $t, Page $v) => $t->assertSame(FailureKind::BILL, $v->items[0]->kind)),

			'subscriptions.list' => $r(static fn (QBitFlow $c, ?RequestOptions $o) => $c->subscriptions->list(new Params\SubscriptionListParams(customerUuid: self::UUID_B,
				createdAfter: self::after(), status: SubscriptionStatus::PAST_DUE, reference: 'crm:42'), $o),
				'GET', '/transaction/subscriptions', $page($m('Subscription')),
				'createdAfter=' . self::AFTER_Q . '&customerUuid=' . self::UUID_B . '&reference=crm%3A42&status=pastDue',
				check: static function (self $t, Page $v): void {
					$t->assertSame(SubscriptionStatus::STOPPED, $v->items[0]->status);
					$t->assertNotNull($v->items[0]->dunning);
				}),
			'subscriptions.get' => $r(static fn (QBitFlow $c, ?RequestOptions $o) => $c->subscriptions->get(self::SUB_ID, null, $o), 'GET', '/transaction/subscription/' . self::SUB_ID, $m('Subscription')),
			'subscriptions.getByReference' => $r(static fn (QBitFlow $c, ?RequestOptions $o) => $c->subscriptions->getByReference(self::ODD_REF, $o),
				'GET', '/transaction/subscription/reference/subscription/' . self::ODD_REF_ESCAPED, $m('Subscription')),
			'subscriptions.listBills' => $r(static fn (QBitFlow $c, ?RequestOptions $o) => $c->subscriptions->listBills(self::SUB_ID, new Params\BillListParams(limit: 100, cursor: self::UUID_A), $o),
				'GET', '/transaction/subscription/' . self::SUB_ID . '/bills', $page($m('Bill')), 'cursor=' . self::UUID_A . '&limit=100',
				check: static function (self $t, Page $v): void {
					$t->assertSame('sub-hist@1', $v->items[0]->uuid);
					$t->assertSame('sub@1', $v->items[0]->subscriptionUuid);
				}),
			'subscriptions.getBill' => $r(static fn (QBitFlow $c, ?RequestOptions $o) => $c->subscriptions->getBill(self::BILL_ID, new Params\ReadParams(includeMembers: true), $o),
				'GET', '/transaction/subscription/bill/' . self::BILL_ID, $m('Bill'), 'includeMembers=true'),
			'subscriptions.getPublicHistory' => $r(static fn (QBitFlow $c, ?RequestOptions $o) => $c->subscriptions->getPublicHistory(self::SUB_ID, $o),
				'GET', '/transaction/subscription/history/' . self::SUB_ID, '[{"uuid":"sub-hist@1","createdAt":"2026-10-01T12:00:00+02:00","amount":9.99,"periodStart":"2026-10-01T12:00:00Z"}]',
				check: static function (self $t, array $v): void {
					$t->assertCount(1, $v);
					$t->assertSame(9.99, $v[0]->amount);
					$t->assertNull($v[0]->customerUuid);
					$t->assertSame(0.0, $v[0]->metadata->feePercent);
				}),
			'subscriptions.cancel 202' => $r(static fn (QBitFlow $c, ?RequestOptions $o) => $c->subscriptions->cancel(self::SUB_ID, new Params\CancelSubscriptionParams(immediate: false), $o),
				'POST', '/transaction/subscription/processing/force-cancel/' . self::SUB_ID, $m('Subscription'), 'immediate=false', status: 202,
				check: static function (self $t, Models\SubscriptionCancellation $v): void {
					$t->assertTrue($v->pending);
					$t->assertSame('sub@1', $v->subscription->uuid);
				}),
			'subscriptions.cancel 200' => $r(static fn (QBitFlow $c, ?RequestOptions $o) => $c->subscriptions->cancel(self::SUB_ID, null, $o),
				'POST', '/transaction/subscription/processing/force-cancel/' . self::SUB_ID, '{"uuid":"sub@1","status":"cancelled","cancellationReason":"merchant"}',
				check: static function (self $t, Models\SubscriptionCancellation $v): void {
					$t->assertFalse($v->pending);
					$t->assertSame(SubscriptionStatus::CANCELLED, $v->subscription->status);
				}),
			'subscriptions.executeTestBilling' => $r(static fn (QBitFlow $c, ?RequestOptions $o) => $c->subscriptions->executeTestBilling(self::SUB_ID, $o),
				'POST', '/transaction/subscription/processing/execute-billing/' . self::SUB_ID, $m('BillingState'),
				check: static function (self $t, Models\BillingState $v): void {
					$t->assertSame(BillingStage::DONE, $v->stage);
					$t->assertSame(BillingOutcome::PAID, $v->outcome);
				}),

			'refunds.list' => $r(static fn (QBitFlow $c, ?RequestOptions $o) => $c->refunds->list(new Params\RefundListParams(limit: 5, cursor: self::UUID_A, includeMembers: false, held: true), $o),
				'GET', '/transaction/refunds/all', $list($m('Refund')), 'held=true&includeMembers=false',
				check: static function (self $t, array $v): void {
					$t->assertNotNull($v[0]->approval);
					$t->assertSame(RefundStatus::PENDING, $v[0]->status);
				}),
			'refunds.listInactive' => $r(static fn (QBitFlow $c, ?RequestOptions $o) => $c->refunds->listInactive(new Params\RefundListParams(limit: 5, cursor: self::UUID_A, userUuid: self::MEMBER_UUID), $o),
				'GET', '/transaction/refunds/all/inactive', $page($m('Refund')), 'cursor=' . self::UUID_A . '&limit=5&userUuid=' . self::MEMBER_UUID),
			'refunds.initiate' => $r(static fn (QBitFlow $c, ?RequestOptions $o) => $c->refunds->initiate(new Params\InitiateRefundParams(txUuid: self::BILL_ID, refundPercent: 50.5,
				reason: 'Damaged', merchantMessage: 'Sorry'), $o),
				'POST', '/transaction/refunds/initiate', $m('Refund'), body: '{"txUuid":"' . self::BILL_ID . '","refundPercent":50.5,"reason":"Damaged","merchantMessage":"Sorry"}',
				idempotent: true, status: 201,
				check: static fn (self $t, Models\Refund $v) => $t->assertSame('refund@1', $v->uuid)),

			'members.list' => $r(static fn (QBitFlow $c, ?RequestOptions $o) => $c->members->list(new Params\MemberListParams(limit: 20, cursor: self::MEMBER_UUID), $o),
				'GET', '/members', $page($m('Member')), 'cursor=' . self::MEMBER_UUID . '&limit=20',
				check: static fn (self $t, Page $v) => $t->assertSame([2, 5, 8], $v->items[0]->acceptedCurrencyIds)),
			'members.get' => $r(static fn (QBitFlow $c, ?RequestOptions $o) => $c->members->get(self::MEMBER_UUID, $o), 'GET', '/members/' . self::MEMBER_UUID, $m('Member')),
			'members.update' => $r(static fn (QBitFlow $c, ?RequestOptions $o) => $c->members->update(self::MEMBER_UUID, new Params\UpdateMemberParams(organizationFeePercent: 0), $o),
				'PUT', '/members/' . self::MEMBER_UUID, $m('Member'), body: '{"organizationFeePercent":0}'),
			'members.remove' => $r(static fn (QBitFlow $c, ?RequestOptions $o) => $c->members->remove(self::MEMBER_UUID, $o), 'DELETE', '/members/' . self::MEMBER_UUID, '{"message":"Member removed"}'),
			'members.trust' => $r(static fn (QBitFlow $c, ?RequestOptions $o) => $c->members->trust(self::MEMBER_UUID, $o),
				'POST', '/members/' . self::MEMBER_UUID . '/trust', '{"userUuid":"' . self::MEMBER_UUID . '","trustedAt":"2026-10-07T10:00:00+02:00"}',
				check: static fn (self $t, Models\Member $v) => $t->assertNotNull($v->trustedAt)),
			'members.listHeldFunds' => $r(static fn (QBitFlow $c, ?RequestOptions $o) => $c->members->listHeldFunds($o), 'GET', '/members/held-funds', $list($m('MemberHeldFundsSummary')),
				check: static fn (self $t, array $v) => $t->assertSame(2, $v[0]->count)),
			'members.getHeldFunds' => $r(static fn (QBitFlow $c, ?RequestOptions $o) => $c->members->getHeldFunds(self::MEMBER_UUID, $o), 'GET', '/members/' . self::MEMBER_UUID . '/held-funds', $m('HeldFunds'),
				check: static fn (self $t, Models\HeldFunds $v) => $t->assertSame(-10.0042, $v->totalAmount)),
			'members.getOwnHeldFunds' => $r(static fn (QBitFlow $c, ?RequestOptions $o) => $c->members->getOwnHeldFunds($o), 'GET', '/user/held-funds', '{"ledgers":[],"totalAmount":0}'),

			'invitations.create' => $r(static fn (QBitFlow $c, ?RequestOptions $o) => $c->invitations->create(new Params\CreateInvitationParams(email: 'seller@example.com', trustLayer: true,
				organizationFeePercent: 2.5, redirectUrl: 'https://shop.example/welcome'), $o),
				'POST', '/invitations', $m('InvitationCreated'),
				body: '{"email":"seller@example.com","role":"user","trustLayer":true,"organizationFeePercent":2.5,"redirectUrl":"https://shop.example/welcome"}', idempotent: true, status: 201,
				check: static fn (self $t, Models\InvitationCreated $v) => $t->assertSame(InvitationStatus::PENDING, $v->invitation->status)),
			'invitations.create minimal' => $r(static fn (QBitFlow $c, ?RequestOptions $o) => $c->invitations->create(new Params\CreateInvitationParams(email: 'seller@example.com'), $o),
				'POST', '/invitations', $m('InvitationCreated'), body: '{"email":"seller@example.com","role":"user","trustLayer":false}', idempotent: true, status: 201),
			'invitations.list' => $r(static fn (QBitFlow $c, ?RequestOptions $o) => $c->invitations->list(new Params\InvitationListParams(status: InvitationStatus::PENDING), $o),
				'GET', '/invitations', $page($m('Invitation')), 'status=pending'),
			'invitations.revoke' => $r(static fn (QBitFlow $c, ?RequestOptions $o) => $c->invitations->revoke(self::UUID_A, $o), 'DELETE', '/invitations/' . self::UUID_A, $m('Invitation'),
				check: static fn (self $t, Models\Invitation $v) => $t->assertSame('i-1', $v->uuid)),

			'wallets.list' => $r(static fn (QBitFlow $c, ?RequestOptions $o) => $c->wallets->list(new Params\WalletListParams(withBalances: true), $o),
				'GET', '/wallet/user', $list($m('Wallet')), 'withBalances=true',
				check: static fn (self $t, array $v) => $t->assertNotNull($v[0]->tokenWallets[0]->balance)),
			'wallets.listForMember' => $r(static fn (QBitFlow $c, ?RequestOptions $o) => $c->wallets->listForMember(self::MEMBER_UUID, $o), 'GET', '/wallet/user/' . self::MEMBER_UUID, $list($m('Wallet'))),
			'wallets.listSupportedCurrencies' => $r(static fn (QBitFlow $c, ?RequestOptions $o) => $c->wallets->listSupportedCurrencies(new Params\SupportedCurrenciesParams(userUuid: self::MEMBER_UUID), $o),
				'GET', '/wallet/supported-currencies', $list($m('Currency')), 'userUuid=' . self::MEMBER_UUID,
				check: static fn (self $t, array $v) => $t->assertNotNull($v[0]->mainCurrency)),

			'accounting.exportJson' => $r(static fn (QBitFlow $c, ?RequestOptions $o) => $c->accounting->exportJson('2026-09-01', '2026-09-30', $o),
				'GET', '/accounting/export', $list($m('AccountingEvent')), 'format=json&from=2026-09-01&to=2026-09-30',
				check: static fn (self $t, array $v) => $t->assertSame('refund', $v[0]->type)),
			'accounting.exportCsv' => $r(static fn (QBitFlow $c, ?RequestOptions $o) => $c->accounting->exportCsv('2026-09-01', '2026-09-30', $o),
				'GET', '/accounting/export', "paymentUuid,type\npay@1,payment\n", 'format=csv&from=2026-09-01&to=2026-09-30', accept: 'text/csv, application/json',
				check: static fn (self $t, string $v) => $t->assertSame("paymentUuid,type\npay@1,payment\n", $v)),

			'webhooks.verifyRemote' => $r(static fn (QBitFlow $c, ?RequestOptions $o) => $c->webhooks->verifyRemote(self::UUID_A, '{"id":"evt_1"}', 't=1790856000,v1=abc', $o),
				'POST', '/webhooks/verify', '{"message":"Signature valid"}',
				body: '{"endpointUuid":"' . self::UUID_A . '","body":"{\"id\":\"evt_1\"}","signature":"t=1790856000,v1=abc"}'),
			'webhooks.endpoints.list' => $r(static fn (QBitFlow $c, ?RequestOptions $o) => $c->webhooks->endpoints->list($o), 'GET', '/webhooks/endpoints', $list($m('WebhookEndpointCreated')),
				check: static fn (self $t, array $v) => $t->assertSame('https://x.io/hook', $v[0]->url)),
			'webhooks.endpoints.create' => $r(static fn (QBitFlow $c, ?RequestOptions $o) => $c->webhooks->endpoints->create(new Params\CreateWebhookEndpointParams(url: 'https://shop.example/hooks',
				events: [EventType::PAYMENT_COMPLETED, EventType::MEMBER_JOINED], includeMembers: false, description: 'Shop'), $o),
				'POST', '/webhooks/endpoints', $m('WebhookEndpointCreated'),
				body: '{"url":"https://shop.example/hooks","events":["payment.completed","member.joined"],"includeMembers":false,"description":"Shop"}', idempotent: true, status: 201,
				check: static fn (self $t, Models\WebhookEndpointCreated $v) => $t->assertSame('whsec_x', $v->secret)),
			'webhooks.endpoints.get' => $r(static fn (QBitFlow $c, ?RequestOptions $o) => $c->webhooks->endpoints->get(self::UUID_A, $o), 'GET', '/webhooks/endpoints/' . self::UUID_A, $m('WebhookEndpointCreated')),
			'webhooks.endpoints.update' => $r(static fn (QBitFlow $c, ?RequestOptions $o) => $c->webhooks->endpoints->update(self::UUID_A, new Params\UpdateWebhookEndpointParams(events: [],
				description: '', payloadVersion: WebhookPayloadVersion::V2, enabled: true), $o),
				'PUT', '/webhooks/endpoints/' . self::UUID_A, $m('WebhookEndpointCreated'), body: '{"events":[],"description":"","payloadVersion":"v2","enabled":true}'),
			'webhooks.endpoints.delete' => $r(static fn (QBitFlow $c, ?RequestOptions $o) => $c->webhooks->endpoints->delete(self::UUID_A, $o), 'DELETE', '/webhooks/endpoints/' . self::UUID_A, '{"message":"Endpoint deleted"}'),
			'webhooks.events.list' => $r(static fn (QBitFlow $c, ?RequestOptions $o) => $c->webhooks->events->list(new Params\EventListParams(limit: 2, cursor: self::EVENT_ID,
				type: EventType::WEBHOOK_TEST, includeMembers: true), $o),
				'GET', '/webhooks/events', Fixtures::page(Fixtures::webhookTestEvent(), null), 'cursor=' . self::EVENT_ID . '&includeMembers=true&limit=2&type=webhook.test',
				check: static function (self $t, Page $v): void {
					$t->assertFalse($v->hasMore());
					$t->assertInstanceOf(WebhookTestEvent::class, $v->items[0]);
					$t->assertNotSame('', $v->items[0]->data->endpointUuid);
				}),
			'webhooks.events.get' => $r(static fn (QBitFlow $c, ?RequestOptions $o) => $c->webhooks->events->get(self::EVENT_ID, $o),
				'GET', '/webhooks/events/' . self::EVENT_ID, substr(Fixtures::webhookTestEvent(), 0, -1) . ',"deliveries":[{"endpointUuid":"' . self::UUID_A . '","url":"https://x.io","delivered":true,
					"attempts":[{"uuid":"' . self::UUID_B . '","eventId":"' . self::EVENT_ID . '","eventType":"webhook.test","endpointUuid":"' . self::UUID_A . '","attempt":1,
					"delivered":true,"statusCode":200,"durationMs":12,"attemptedAt":"2026-10-01T12:00:01+02:00"}]}]}',
				check: static function (self $t, Models\EventDetail $v): void {
					$t->assertSame(self::EVENT_ID, $v->id);
					$t->assertSame(EventType::WEBHOOK_TEST, $v->type);
					$t->assertInstanceOf(WebhookTestEvent::class, $v->event);
					$t->assertSame('This is a test event', $v->data->message);
					$t->assertTrue($v->deliveries[0]->delivered);
					$t->assertSame(200, $v->deliveries[0]->attempts[0]->statusCode);
				}),

			'currencies.listAvailable' => $r(static fn (QBitFlow $c, ?RequestOptions $o) => $c->currencies->listAvailable(new Params\CurrencyListParams(test: true), $o),
				'GET', '/utils/all-available-currencies', $list($m('Currency')), 'test=true'),
			'currencies.listMain' => $r(static fn (QBitFlow $c, ?RequestOptions $o) => $c->currencies->listMain(null, $o), 'GET', '/utils/all-main-currencies', $list($m('Currency'))),
			'currencies.get' => $r(static fn (QBitFlow $c, ?RequestOptions $o) => $c->currencies->get(8, $o), 'GET', '/utils/currency/id/8', $m('Currency'),
				check: static function (self $t, Models\Currency $v): void {
					$t->assertSame(8, $v->id);
					$t->assertSame('USDC', $v->symbol);
				}),
		];
	}

	#[Test]
	#[DataProvider('routes')]
	public function it_sends_the_request_and_decodes_the_answer(Closure $call, string $method, string $path, string $query, ?string $body, bool $idempotent, string $reply, int $status, ?Closure $check, string $accept): void
	{
		$client = $this->client(MockHttpClient::static($status, $reply, $accept === 'application/json' ? [] : ['Content-Type' => 'text/csv']));

		$result = $call($client, null);
		if ($check !== null) {
			$check($this, $result);
		}
		// Request options: On-Behalf-Of, X-Request-Id and a caller's Idempotency-Key.
		$call($client, new RequestOptions(onBehalfOf: self::MEMBER_UUID, idempotencyKey: 'order-42', requestId: 'req-42'));

		$this->assertSame(2, $this->http->count());
		foreach ($this->http->requests as $i => $request) {
			$this->assertSame($method, $request->getMethod(), "request $i");
			$this->assertSame($path, self::pathOf($request), "request $i");
			$this->assertSame($query, $request->getUri()->getQuery(), "request $i");
			$this->assertSame($accept, $request->getHeaderLine('Accept'));
			$this->assertSame(self::API_KEY, $request->getHeaderLine('X-API-Key'));
			if ($body === null) {
				$this->assertSame('', (string) $request->getBody());
				$this->assertFalse($request->hasHeader('Content-Type'));
			} else {
				$this->assertSame('application/json', $request->getHeaderLine('Content-Type'));
				$this->assertEquals(json_decode($body, true), json_decode((string) $request->getBody(), true), (string) $request->getBody());
			}
		}

		[$plain, $opted] = $this->http->requests;
		$this->assertFalse($plain->hasHeader('On-Behalf-Of'));
		$this->assertSame(self::MEMBER_UUID, $opted->getHeaderLine('On-Behalf-Of'));
		$this->assertSame('req-42', $opted->getHeaderLine('X-Request-Id'));
		if ($idempotent) {
			$this->assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $plain->getHeaderLine('Idempotency-Key'));
			$this->assertSame('order-42', $opted->getHeaderLine('Idempotency-Key'));
		} else {
			$this->assertFalse($plain->hasHeader('Idempotency-Key'));
			$this->assertFalse($opted->hasHeader('Idempotency-Key'));
		}
	}

	#[Test]
	#[DataProvider('routes')]
	public function it_retries_reads_and_creates_only(Closure $call, string $method, string $path, string $query, ?string $body, bool $idempotent, string $reply, int $status): void
	{
		$client = $this->client(new MockHttpClient(static fn (RequestInterface $r, int $n): ResponseInterface => $n === 0
			? MockHttpClient::response(503, '{"error":"unavailable","code":"network_unavailable"}')
			: MockHttpClient::response($status, $reply)));

		if ($method !== 'GET' && ! $idempotent) {
			try {
				$call($client, null);
				$this->fail('want a ServerException');
			} catch (ServerException $e) {
				$this->assertSame(503, $e->status);
				$this->assertSame(1, $this->http->count(), 'never retried');
			}

			return;
		}

		$call($client, null);
		$this->assertSame(2, $this->http->count());
		if ($idempotent) {
			[$first, $second] = $this->http->requests;
			$this->assertNotSame('', $first->getHeaderLine('Idempotency-Key'));
			$this->assertSame($first->getHeaderLine('Idempotency-Key'), $second->getHeaderLine('Idempotency-Key'));
			$this->assertSame((string) $first->getBody(), (string) $second->getBody());
		}
	}

	#[Test]
	public function exactly_seven_methods_are_idempotent(): void
	{
		$idempotent = array_filter(self::routes(), static fn (array $route, string $name): bool => $route[5] && ! str_ends_with($name, 'minimal'), ARRAY_FILTER_USE_BOTH);
		$this->assertCount(7, $idempotent);
	}

	#[Test]
	public function errors_reach_the_caller_typed(): void
	{
		$client = $this->client(MockHttpClient::static(409, '{"error":"Unknown checkout","code":"tx_already_sent","requestId":"r-1"}'));
		try {
			$client->checkoutSessions->expire(self::PAY_ID);
			$this->fail('want a ConflictException');
		} catch (ConflictException $e) {
			$this->assertSame('tx_already_sent', $e->apiCode);
			$this->assertSame('r-1', $e->requestId);
		}

		$client = $this->client(MockHttpClient::static(400, '{"error":"window too long","code":"bad_request"}'));
		$this->expectExceptionThrown(BadRequestException::class, static fn () => $client->accounting->exportCsv('2026-01-01', '2026-09-30'));

		$client = $this->client(MockHttpClient::static(404, '{"error":"not found","code":"not_found"}'));
		$this->expectExceptionThrown(NotFoundException::class, static fn () => $client->subscriptions->cancel(self::SUB_ID));
		$this->expectExceptionThrown(NotFoundException::class, static fn () => $client->products->delete(self::UUID_A));
		$this->expectExceptionThrown(NotFoundException::class, static fn () => iterator_to_array($client->customers->iterate()));

		$client = $this->client(new MockHttpClient(static fn (): ResponseInterface => MockHttpClient::response(202)));
		$this->expectExceptionThrown(ServerException::class, static fn () => $client->subscriptions->cancel(self::SUB_ID));
	}

	/** @param class-string<\Throwable> $class */
	private function expectExceptionThrown(string $class, Closure $call): void
	{
		try {
			$call();
			$this->fail('want ' . $class);
		} catch (\Throwable $e) {
			$this->assertInstanceOf($class, $e, $e->getMessage());
		}
	}

	/** @return array<string,array{0: string, 1: Closure(QBitFlow): mixed}> */
	public static function invalidInputs(): array
	{
		return [
			'products.get empty' => ['uuid', static fn (QBitFlow $c) => $c->products->get('')],
			'products.get not uuid' => ['uuid', static fn (QBitFlow $c) => $c->products->get('42')],
			'products.update' => ['uuid', static fn (QBitFlow $c) => $c->products->update('../x', new Params\UpdateProductParams())],
			'products.delete' => ['uuid', static fn (QBitFlow $c) => $c->products->delete('')],
			'products.getByReference' => ['reference', static fn (QBitFlow $c) => $c->products->getByReference('  ')],
			'customers.get' => ['uuid', static fn (QBitFlow $c) => $c->customers->get('pay@' . self::UUID_A)],
			'customers.update' => ['uuid', static fn (QBitFlow $c) => $c->customers->update('', new Params\UpdateCustomerParams())],
			'customers.delete' => ['uuid', static fn (QBitFlow $c) => $c->customers->delete('x')],
			'customers.getByEmail' => ['email', static fn (QBitFlow $c) => $c->customers->getByEmail('')],
			'customers.getByReference' => ['reference', static fn (QBitFlow $c) => $c->customers->getByReference('')],
			'checkoutSessions.getStatus' => ['uuid', static fn (QBitFlow $c) => $c->checkoutSessions->getStatus('')],
			'checkoutSessions.expire' => ['uuid', static fn (QBitFlow $c) => $c->checkoutSessions->expire('cs_123')],
			'payments.get' => ['uuid', static fn (QBitFlow $c) => $c->payments->get('pay@123')],
			'payments.getByReference' => ['reference', static fn (QBitFlow $c) => $c->payments->getByReference('')],
			'subscriptions.get' => ['uuid', static fn (QBitFlow $c) => $c->subscriptions->get('bad@' . self::UUID_A)],
			'subscriptions.getByReference' => ['reference', static fn (QBitFlow $c) => $c->subscriptions->getByReference('')],
			'subscriptions.listBills' => ['uuid', static fn (QBitFlow $c) => $c->subscriptions->listBills('')],
			'subscriptions.getBill' => ['billUuid', static fn (QBitFlow $c) => $c->subscriptions->getBill('1')],
			'subscriptions.getPublicHistory' => ['subscriptionUuid', static fn (QBitFlow $c) => $c->subscriptions->getPublicHistory('')],
			'subscriptions.cancel' => ['uuid', static fn (QBitFlow $c) => $c->subscriptions->cancel('', new Params\CancelSubscriptionParams(true))],
			'subscriptions.executeTestBilling' => ['uuid', static fn (QBitFlow $c) => $c->subscriptions->executeTestBilling('sub')],
			'members.get' => ['userUuid', static fn (QBitFlow $c) => $c->members->get('')],
			'members.update' => ['userUuid', static fn (QBitFlow $c) => $c->members->update('1', new Params\UpdateMemberParams(0))],
			'members.remove' => ['userUuid', static fn (QBitFlow $c) => $c->members->remove('')],
			'members.trust' => ['userUuid', static fn (QBitFlow $c) => $c->members->trust('x')],
			'members.getHeldFunds' => ['userUuid', static fn (QBitFlow $c) => $c->members->getHeldFunds('')],
			'invitations.revoke' => ['uuid', static fn (QBitFlow $c) => $c->invitations->revoke('')],
			'wallets.listForMember' => ['userUuid', static fn (QBitFlow $c) => $c->wallets->listForMember('1')],
			'webhooks.endpoints.get' => ['uuid', static fn (QBitFlow $c) => $c->webhooks->endpoints->get('')],
			'webhooks.endpoints.update' => ['uuid', static fn (QBitFlow $c) => $c->webhooks->endpoints->update('x', new Params\UpdateWebhookEndpointParams())],
			'webhooks.endpoints.delete' => ['uuid', static fn (QBitFlow $c) => $c->webhooks->endpoints->delete('')],
			'webhooks.events.get' => ['id', static fn (QBitFlow $c) => $c->webhooks->events->get('')],
			'webhooks.verifyRemote uuid' => ['endpointUuid', static fn (QBitFlow $c) => $c->webhooks->verifyRemote('', '{}', 't=1,v1=a')],
			'webhooks.verifyRemote body' => ['body', static fn (QBitFlow $c) => $c->webhooks->verifyRemote(self::UUID_A, '', 't=1,v1=a')],
			'webhooks.verifyRemote utf8' => ['body', static fn (QBitFlow $c) => $c->webhooks->verifyRemote(self::UUID_A, "\xff", 't=1,v1=a')],
			'currencies.get' => ['id', static fn (QBitFlow $c) => $c->currencies->get(0)],

			'products.create price' => ['price', static fn (QBitFlow $c) => $c->products->create(new Params\CreateProductParams(name: 'Pro', price: -1))],
			'products.update name' => ['name', static fn (QBitFlow $c) => $c->products->update(self::UUID_A, new Params\UpdateProductParams(name: '<b>'))],
			'customers.create email' => ['email', static fn (QBitFlow $c) => $c->customers->create(new Params\CreateCustomerParams(name: 'Ada', email: 'ada'))],
			'customers.update phone' => ['phoneNumber', static fn (QBitFlow $c) => $c->customers->update(self::UUID_A, new Params\UpdateCustomerParams(phoneNumber: 'call me'))],
			'customers.list email' => ['email', static fn (QBitFlow $c) => $c->customers->list(new Params\CustomerListParams(email: 'x'))],
			'checkoutSessions.createPayment mixed' => ['productUuid', static fn (QBitFlow $c) => $c->checkoutSessions->createPayment(new Params\CreatePaymentSessionParams(productUuid: self::UUID_A, productReference: 'pro'))],
			'checkoutSessions.createSubscription frequency' => ['frequency.unit', static fn (QBitFlow $c) => $c->checkoutSessions->createSubscription(new Params\CreateSubscriptionSessionParams(productUuid: self::UUID_A, frequency: new Models\Duration(1)))],
			'payments.list exclusive' => ['userUuid', static fn (QBitFlow $c) => $c->payments->list(new Params\PaymentListParams(includeMembers: true, userUuid: self::MEMBER_UUID))],
			'payments.listCombined source' => ['source', static fn (QBitFlow $c) => $c->payments->listCombined(new Params\CombinedPaymentListParams(source: 'bills'))],
			'failures.list kind' => ['kind', static fn (QBitFlow $c) => $c->failures->list(new Params\FailureListParams(kind: 'refund'))],
			'subscriptions.list status' => ['status', static fn (QBitFlow $c) => $c->subscriptions->list(new Params\SubscriptionListParams(status: 'lowOnFunds'))],
			'refunds.list userUuid' => ['userUuid', static fn (QBitFlow $c) => $c->refunds->list(new Params\RefundListParams(userUuid: '1'))],
			'refunds.listInactive exclusive' => ['userUuid', static fn (QBitFlow $c) => $c->refunds->listInactive(new Params\RefundListParams(includeMembers: true, userUuid: self::MEMBER_UUID))],
			'refunds.initiate percent' => ['refundPercent', static fn (QBitFlow $c) => $c->refunds->initiate(new Params\InitiateRefundParams(txUuid: self::PAY_ID, refundPercent: 0.0))],
			'members.update fee' => ['organizationFeePercent', static fn (QBitFlow $c) => $c->members->update(self::MEMBER_UUID, new Params\UpdateMemberParams(51))],
			'invitations.create email' => ['email', static fn (QBitFlow $c) => $c->invitations->create(new Params\CreateInvitationParams(email: ''))],
			'invitations.list status' => ['status', static fn (QBitFlow $c) => $c->invitations->list(new Params\InvitationListParams(status: 'open'))],
			'wallets.listSupportedCurrencies' => ['userUuid', static fn (QBitFlow $c) => $c->wallets->listSupportedCurrencies(new Params\SupportedCurrenciesParams(userUuid: 'x'))],
			'accounting.exportJson order' => ['to', static fn (QBitFlow $c) => $c->accounting->exportJson('2026-09-30', '2026-09-01')],
			'accounting.exportCsv date' => ['from', static fn (QBitFlow $c) => $c->accounting->exportCsv('2026-02-30', '2026-03-01')],
			'webhooks.endpoints.create url' => ['url', static fn (QBitFlow $c) => $c->webhooks->endpoints->create(new Params\CreateWebhookEndpointParams(url: 'ftp://x'))],
			'webhooks.endpoints.update test event' => ['events[0]', static fn (QBitFlow $c) => $c->webhooks->endpoints->update(self::UUID_A, new Params\UpdateWebhookEndpointParams(events: [EventType::WEBHOOK_TEST]))],
			'webhooks.events.list type' => ['type', static fn (QBitFlow $c) => $c->webhooks->events->list(new Params\EventListParams(type: 'payment.failed'))],
			'request options after params' => ['name', static fn (QBitFlow $c) => $c->products->update(self::UUID_A, new Params\UpdateProductParams(name: '<b>'), new RequestOptions(requestId: 'bad id'))],
		];
	}

	/** @param Closure(QBitFlow): mixed $call */
	#[Test]
	#[DataProvider('invalidInputs')]
	public function it_checks_inputs_before_sending(string $field, Closure $call): void
	{
		$client = $this->client(MockHttpClient::static(200, '{}'));
		$this->assertContains($field, $this->failingFields(static fn () => $call($client)));
		$this->assertSame(0, $this->http->count(), 'nothing is sent');
	}

	#[Test]
	public function verify_remote_maps_the_answers(): void
	{
		$cases = [
			'valid' => [200, '{"message":"ok"}', null],
			'invalid signature' => [400, '{"error":"invalid signature","code":"invalid_signature","requestId":"r-9"}', WebhookSignatureException::class],
			'validation' => [400, '{"error":"invalid","code":"validation_failed","details":{"errors":[{"field":"signature","message":"required"}]}}', ValidationException::class],
			'other bad request' => [400, '{"error":"bad","code":"bad_request"}', BadRequestException::class],
			'not found' => [404, '{"error":"not found","code":"not_found"}', NotFoundException::class],
			'server' => [500, '{"error":"boom","code":"internal"}', ServerException::class],
		];
		foreach ($cases as $name => [$status, $reply, $class]) {
			$client = $this->client(MockHttpClient::static($status, $reply));
			try {
				$client->webhooks->verifyRemote(self::UUID_A, '{"id":"evt_1"}', 't=1790856000,v1=abc');
				$this->assertNull($class, $name);
			} catch (\Throwable $e) {
				$this->assertSame($class, $e::class, $name);
				if ($e instanceof WebhookSignatureException) {
					$this->assertSame(WebhookSignatureException::REASON_INVALID_SIGNATURE, $e->reason);
					$this->assertSame(400, $e->status);
					$this->assertSame('invalid_signature', $e->apiCode);
					$this->assertSame('r-9', $e->requestId);
				}
			}
			$this->assertSame(1, $this->http->count(), $name . ': verify is never retried');
		}

		$client = $this->client(MockHttpClient::static(200, '{}'));
		try {
			$client->webhooks->verifyRemote(self::UUID_A, '{}', '');
			$this->fail('want a WebhookSignatureException');
		} catch (WebhookSignatureException $e) {
			$this->assertSame(WebhookSignatureException::REASON_MISSING_HEADER, $e->reason);
		}
		$this->assertSame(['body'], $this->failingFields(static fn () => $client->webhooks->verifyRemote(self::UUID_A, str_repeat('a', 1048577), 't=1,v1=a')));
		$this->assertSame(0, $this->http->count());
	}

	/**
	 * name → [path, filters, item, walk(client, stopAfter): ids]
	 *
	 * @return array<string,array{0: string, 1: string, 2: Closure(int): string, 3: Closure(QBitFlow, int): list<string>}>
	 */
	public static function iterators(): array
	{
		$uuidItem = static fn (int $i): string => sprintf('{"uuid":"id%d"}', $i);
		$collect = static function (iterable $items, Closure $id, int $stopAfter): array {
			$ids = [];
			foreach ($items as $item) {
				$ids[] = $id($item);
				if ($stopAfter > 0 && count($ids) === $stopAfter) {
					break;
				}
			}

			return $ids;
		};
		$byUuid = static fn (object $v): string => $v->uuid;

		return [
			'customers.iterate' => ['/customer/all', 'email=a%40b.co&limit=2&verified=false', $uuidItem,
				static fn (QBitFlow $c, int $n) => $collect($c->customers->iterate(new Params\CustomerListParams(limit: 2, email: 'a@b.co', verified: false)), $byUuid, $n)],
			'payments.iterate' => ['/transaction/payments', 'createdAfter=' . self::AFTER_Q . '&limit=2', $uuidItem,
				static fn (QBitFlow $c, int $n) => $collect($c->payments->iterate(new Params\PaymentListParams(limit: 2, createdAfter: self::after())), $byUuid, $n)],
			'payments.iterateCombined' => ['/transaction/payments/combined', 'source=payment', $uuidItem,
				static fn (QBitFlow $c, int $n) => $collect($c->payments->iterateCombined(new Params\CombinedPaymentListParams(source: CombinedPaymentSource::PAYMENT)), $byUuid, $n)],
			'failures.iterate' => ['/transaction/failures', 'category=reverted', $uuidItem,
				static fn (QBitFlow $c, int $n) => $collect($c->failures->iterate(new Params\FailureListParams(category: FailureCategory::REVERTED)), $byUuid, $n)],
			'subscriptions.iterate' => ['/transaction/subscriptions', 'includeMembers=true&status=active', $uuidItem,
				static fn (QBitFlow $c, int $n) => $collect($c->subscriptions->iterate(new Params\SubscriptionListParams(includeMembers: true, status: SubscriptionStatus::ACTIVE)), $byUuid, $n)],
			'subscriptions.iterateBills' => ['/transaction/subscription/' . self::SUB_ID . '/bills', 'limit=100', $uuidItem,
				static fn (QBitFlow $c, int $n) => $collect($c->subscriptions->iterateBills(self::SUB_ID, new Params\BillListParams(limit: 100)), $byUuid, $n)],
			'refunds.iterateInactive' => ['/transaction/refunds/all/inactive', 'held=false', $uuidItem,
				static fn (QBitFlow $c, int $n) => $collect($c->refunds->iterateInactive(new Params\RefundListParams(held: false)), $byUuid, $n)],
			'members.iterate' => ['/members', 'limit=1', static fn (int $i): string => sprintf('{"userUuid":"id%d"}', $i),
				static fn (QBitFlow $c, int $n) => $collect($c->members->iterate(new Params\MemberListParams(limit: 1)), static fn (object $v): string => $v->userUuid, $n)],
			'invitations.iterate' => ['/invitations', 'status=expired', $uuidItem,
				static fn (QBitFlow $c, int $n) => $collect($c->invitations->iterate(new Params\InvitationListParams(status: InvitationStatus::EXPIRED)), $byUuid, $n)],
			'webhooks.events.iterate' => ['/webhooks/events', 'type=payment.completed',
				static fn (int $i): string => sprintf('{"id":"id%d","type":"payment.completed","version":"v2","createdAt":"2026-10-01T12:00:00Z","data":{}}', $i),
				static fn (QBitFlow $c, int $n) => $collect($c->webhooks->events->iterate(new Params\EventListParams(type: EventType::PAYMENT_COMPLETED)), static fn (object $v): string => $v->id, $n)],
		];
	}

	#[Test]
	#[DataProvider('iterators')]
	public function each_iterator_walks_two_pages(string $path, string $filters, Closure $item, Closure $walk): void
	{
		$client = $this->client(new MockHttpClient(static function (RequestInterface $r) use ($item): ResponseInterface {
			parse_str($r->getUri()->getQuery(), $q);

			return match ($q['cursor'] ?? '') {
				'' => MockHttpClient::response(200, '{"items":[' . $item(0) . ',' . $item(1) . '],"nextCursor":"id1"}'),
				'id1' => MockHttpClient::response(200, '{"items":[' . $item(2) . '],"nextCursor":null}'),
				default => MockHttpClient::response(400, '{"error":"bad cursor","code":"validation_failed"}'),
			};
		}));

		$this->assertSame(['id0', 'id1', 'id2'], $walk($client, 0));
		$this->assertSame(2, $this->http->count());
		foreach ($this->http->requests as $i => $request) {
			$this->assertSame('GET', $request->getMethod());
			$this->assertSame($path, self::pathOf($request));
			parse_str($request->getUri()->getQuery(), $q);
			$cursor = $q['cursor'] ?? '';
			unset($q['cursor']);
			ksort($q);
			$this->assertSame($filters, http_build_query($q, '', '&', PHP_QUERY_RFC3986), "page $i filters");
			$this->assertSame(['', 'id1'][$i], $cursor);
		}

		$this->assertSame(['id0'], $walk($client, 1), 'an early break stops after the first page');
		$this->assertSame(3, $this->http->count());
	}

	#[Test]
	public function iterator_errors_end_the_walk_and_bad_input_sends_nothing(): void
	{
		$client = $this->client(new MockHttpClient(static function (RequestInterface $r): ResponseInterface {
			return $r->getUri()->getQuery() === ''
				? MockHttpClient::response(200, '{"items":[{"uuid":"id0"}],"nextCursor":"id0"}')
				: MockHttpClient::response(401, '{"error":"unauthorized","code":"unauthorized"}');
		}));
		$ids = [];
		try {
			foreach ($client->payments->iterate() as $payment) {
				$ids[] = $payment->uuid;
			}
			$this->fail('want an AuthenticationException');
		} catch (AuthenticationException) {
			$this->assertSame(['id0'], $ids);
		}

		$before = $this->http->count();
		$this->assertSame(['uuid'], $this->failingFields(static fn () => iterator_to_array($client->subscriptions->iterateBills('nope'))));
		$this->assertSame(['onBehalfOf'], $this->failingFields(static fn () => iterator_to_array($client->members->iterate(null, new RequestOptions(onBehalfOf: 'x')))));
		$this->assertSame($before, $this->http->count());
	}
}
