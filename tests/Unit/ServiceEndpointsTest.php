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
use QBitFlow\Enums\EventType;
use QBitFlow\Enums\FailureCategory;
use QBitFlow\Enums\FailureKind;
use QBitFlow\Enums\InvitationStatus;
use QBitFlow\Enums\RefundStatus;
use QBitFlow\Enums\SubscriptionStatus;
use QBitFlow\Enums\TransactionKind;
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

	private const REFUND_ID = 'refund@019eca82-5680-7b00-8000-0000000000d4';

	private const EVENT_ID = 'evt_bd1a913d-188e-5ac5-a2d6-5fd25cd67301';

	/** An opaque cursor, as the API hands them out (never an id). */
	private const CURSOR = 'djF8MDE5ZWNhODItNTY4MC03YjAwLTgwMDAtMDAwMDAwMDAwMGMx';

	/** The next page's cursor in {@see Fixtures::page()}. */
	private const NEXT = 'djF8MDE5ZWNhODItNTY4MC03YjAw';

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
			'products.list' => $r(static fn (QBitFlow $c, ?RequestOptions $o) => $c->products->list(new Params\ProductListParams(limit: 10, cursor: self::CURSOR,
				includeHidden: true, subscription: false, reference: 'pro'), $o),
				'GET', '/products', $page($m('Product')), 'cursor=' . self::CURSOR . '&includeHidden=true&limit=10&reference=pro&subscription=false',
				check: static function (self $t, Page $v): void {
					$t->assertCount(1, $v->items);
					$t->assertSame('p-1', $v->items[0]->uuid);
					$t->assertNotNull($v->items[0]->subscription);
					$t->assertTrue($v->hasMore);
					$t->assertSame(self::NEXT, $v->nextCursor);
				}),
			'products.list no params' => $r(static fn (QBitFlow $c, ?RequestOptions $o) => $c->products->list(null, $o), 'GET', '/products', '{"items":[],"hasMore":false}',
				check: static fn (self $t, Page $v) => $t->assertSame([[], false, null], [$v->items, $v->hasMore, $v->nextCursor])),
			'products.create' => $r(static fn (QBitFlow $c, ?RequestOptions $o) => $c->products->create(new Params\CreateProductParams(name: 'Pro', priceUsd: '9.99', reference: 'pro',
				subscription: new Params\SubscriptionTermsParams(Models\Duration::months(1), new Models\Duration(), 3), metadata: ['sku' => 'PRO-M', '0' => 'zero']), $o),
				'POST', '/products', $m('Product'),
				body: '{"name":"Pro","priceUsd":"9.99","reference":"pro","subscription":{"frequency":{"value":1,"unit":"months"},"trialPeriod":{"value":0},"minPeriods":3},"metadata":{"sku":"PRO-M","0":"zero"}}',
				idempotent: true, status: 201,
				check: static function (self $t, Models\Product $v): void {
					$t->assertSame('p-1', $v->uuid);
					$t->assertSame('9.99', $v->priceUsd);
					$t->assertSame(['sku' => 'PRO-1'], $v->metadata);
				}),
			'products.get' => $r(static fn (QBitFlow $c, ?RequestOptions $o) => $c->products->get(self::UUID_A, $o), 'GET', '/products/' . self::UUID_A, $m('Product')),
			'products.getByReference' => $r(static fn (QBitFlow $c, ?RequestOptions $o) => $c->products->getByReference('pro', $o), 'GET', '/products',
				$list('{"uuid":"p-2","reference":"pro-2"}', $m('Product')), 'reference=pro',
				check: static fn (self $t, Models\Product $v) => $t->assertSame('p-1', $v->uuid, 'the item whose reference is the one asked for')),
			'products.update' => $r(static fn (QBitFlow $c, ?RequestOptions $o) => $c->products->update(self::UUID_A, new Params\UpdateProductParams(description: '', priceUsd: '12.50',
				isActive: false, metadata: ['legacy' => '', 'sku' => 'PRO-M2']), $o),
				'PATCH', '/products/' . self::UUID_A, $m('Product'), body: '{"description":"","priceUsd":"12.50","isActive":false,"metadata":{"legacy":"","sku":"PRO-M2"}}'),
			'products.delete' => $r(static fn (QBitFlow $c, ?RequestOptions $o) => $c->products->delete(self::UUID_A, $o), 'DELETE', '/products/' . self::UUID_A, '', status: 204,
				check: static fn (self $t, mixed $v) => $t->assertNull($v)),

			'customers.list' => $r(static fn (QBitFlow $c, ?RequestOptions $o) => $c->customers->list(new Params\CustomerListParams(limit: 5, cursor: self::CURSOR, email: 'ada+1@example.com',
				reference: 'crm-1', verified: true), $o),
				'GET', '/customers', $page($m('Customer')), 'cursor=' . self::CURSOR . '&email=ada%2B1%40example.com&limit=5&reference=crm-1&verified=true',
				check: static function (self $t, Page $v): void {
					$t->assertCount(1, $v->items);
					$t->assertTrue($v->hasMore);
					$t->assertSame(self::NEXT, $v->nextCursor);
				}),
			'customers.create' => $r(static fn (QBitFlow $c, ?RequestOptions $o) => $c->customers->create(new Params\CreateCustomerParams(firstName: 'Ada', email: 'ada@example.com',
				lastName: 'Lovelace', phoneNumber: '+33 6 12 34 56 78', reference: 'crm-1', metadata: ['crm:id' => 'C-5521']), $o),
				'POST', '/customers', $m('Customer'),
				body: '{"firstName":"Ada","lastName":"Lovelace","email":"ada@example.com","phoneNumber":"+33 6 12 34 56 78","reference":"crm-1","metadata":{"crm:id":"C-5521"}}',
				idempotent: true, status: 201,
				check: static function (self $t, Models\Customer $v): void {
					$t->assertSame(['c-1', 'Ada', true, 'm-1'], [$v->uuid, $v->firstName, $v->verified, $v->userUuid]);
				}),
			'customers.get' => $r(static fn (QBitFlow $c, ?RequestOptions $o) => $c->customers->get(self::UUID_A, $o), 'GET', '/customers/' . self::UUID_A, $m('Customer')),
			'customers.getByReference' => $r(static fn (QBitFlow $c, ?RequestOptions $o) => $c->customers->getByReference('crm-1', $o), 'GET', '/customers', $list($m('Customer')), 'reference=crm-1'),
			'customers.update' => $r(static fn (QBitFlow $c, ?RequestOptions $o) => $c->customers->update(self::UUID_A, new Params\UpdateCustomerParams(lastName: '', email: 'new@example.com',
				phoneNumber: '', address: '', metadata: ['oldKey' => '', 'tier' => 'gold']), $o),
				'PATCH', '/customers/' . self::UUID_A, $m('Customer'), body: '{"email":"new@example.com","lastName":"","phoneNumber":"","address":"","metadata":{"oldKey":"","tier":"gold"}}'),
			'customers.delete' => $r(static fn (QBitFlow $c, ?RequestOptions $o) => $c->customers->delete(self::UUID_A, $o), 'DELETE', '/customers/' . self::UUID_A, '', status: 204),

			'currencies.list' => $r(static fn (QBitFlow $c, ?RequestOptions $o) => $c->currencies->list(new Params\CurrencyListParams(test: true, main: true), $o),
				'GET', '/currencies', $list($m('Currency'), Fixtures::NATIVE), 'main=true&test=true',
				check: static fn (self $t, array $v) => $t->assertSame(['BASE:USDC', 'ETH'], [$v[0]->code, $v[1]->code])),
			'currencies.list no params' => $r(static fn (QBitFlow $c, ?RequestOptions $o) => $c->currencies->list(null, $o), 'GET', '/currencies', '{"items":[],"hasMore":false}',
				check: static fn (self $t, array $v) => $t->assertSame([], $v)),
			'currencies.get' => $r(static fn (QBitFlow $c, ?RequestOptions $o) => $c->currencies->get(8, $o), 'GET', '/currencies/8', $m('Currency'),
				check: static fn (self $t, Models\Currency $v) => $t->assertSame([8, 'USDC', 'BASE'], [$v->id, $v->symbol, $v->chain])),

			'checkoutSessions.createPayment' => $r(static fn (QBitFlow $c, ?RequestOptions $o) => $c->checkoutSessions->createPayment(new Params\CreatePaymentSessionParams(
				productName: 'T-shirt', description: 'Blue', priceUsd: '4.50', reference: 'order-1', successUrl: 'https://shop.example/ok?id={{UUID}}',
				cancelUrl: 'https://shop.example/ko', customerReference: 'crm-1', expiresInMinutes: 30, fees: new Params\CheckoutFees(
					processingFee: true, items: [new Params\FeeItem('Shipping', '0.75', 'Standard, 3 to 5 days'), new Params\FeeItem('VAT (20%)', '0.80')]),
				metadata: ['orderId' => 'order-1']), $o),
				'POST', '/checkout-sessions/payment', $m('CreatedCheckoutSession'),
				body: '{"reference":"order-1","productName":"T-shirt","description":"Blue","priceUsd":"4.50","successUrl":"https://shop.example/ok?id={{UUID}}","cancelUrl":"https://shop.example/ko","customerReference":"crm-1","expiresInMinutes":30,'
					. '"metadata":{"orderId":"order-1"},"fees":{"processingFee":true,"items":[{"label":"Shipping","description":"Standard, 3 to 5 days","amountUsd":"0.75"},{"label":"VAT (20%)","amountUsd":"0.80"}]}}',
				idempotent: true, status: 201,
				check: static function (self $t, Models\CreatedCheckoutSession $v): void {
					$t->assertSame('pay@1', $v->uuid);
					$t->assertNotSame('', $v->link);
					$t->assertSame('2026-10-02T12:00:00+00:00', $v->expiresAt->format(DATE_ATOM));
				}),
			'checkoutSessions.createSubscription' => $r(static fn (QBitFlow $c, ?RequestOptions $o) => $c->checkoutSessions->createSubscription(new Params\CreateSubscriptionSessionParams(
				productUuid: self::UUID_A, customerUuid: self::UUID_B, frequency: Models\Duration::weeks(1), trialPeriod: Models\Duration::days(14), minPeriods: 0,
				metadata: ['plan' => 'pro']), $o),
				'POST', '/checkout-sessions/subscription', $m('CreatedCheckoutSession'),
				body: '{"productUuid":"' . self::UUID_A . '","customerUuid":"' . self::UUID_B . '","metadata":{"plan":"pro"},"frequency":{"value":1,"unit":"weeks"},"trialPeriod":{"value":14,"unit":"days"},"minPeriods":0}',
				idempotent: true, status: 201),
			'checkoutSessions.createSubscription with fees' => $r(static fn (QBitFlow $c, ?RequestOptions $o) => $c->checkoutSessions->createSubscription(new Params\CreateSubscriptionSessionParams(
				productName: 'Pro', priceUsd: '9.99', frequency: Models\Duration::months(1), fees: new Params\CheckoutFees(
					processingFee: true, items: [new Params\FeeItem('VAT (20%)', '2', 'French VAT')])), $o),
				'POST', '/checkout-sessions/subscription', $m('CreatedCheckoutSession'),
				body: '{"productName":"Pro","priceUsd":"9.99","frequency":{"value":1,"unit":"months"},"fees":{"processingFee":true,"items":[{"label":"VAT (20%)","description":"French VAT","amountUsd":"2"}]}}',
				idempotent: true, status: 201),
			'checkoutSessions.createSubscription processing fee off' => $r(static fn (QBitFlow $c, ?RequestOptions $o) => $c->checkoutSessions->createSubscription(new Params\CreateSubscriptionSessionParams(
				productUuid: self::UUID_A, fees: new Params\CheckoutFees(processingFee: false)), $o),
				'POST', '/checkout-sessions/subscription', $m('CreatedCheckoutSession'),
				body: '{"productUuid":"' . self::UUID_A . '","fees":{"processingFee":false}}',
				idempotent: true, status: 201),
			'checkoutSessions.get' => $r(static fn (QBitFlow $c, ?RequestOptions $o) => $c->checkoutSessions->get(self::PAY_ID, $o),
				'GET', '/checkout-sessions/' . self::PAY_ID, $m('CheckoutSessionStatus'),
				check: static function (self $t, Models\CheckoutSessionStatus $v): void {
					$t->assertSame(CheckoutSessionStatusValue::CREATED, $v->status);
					$t->assertNotNull($v->lastAttempt);
					$t->assertNotNull($v->expiresAt);
				}),
			'checkoutSessions.expire' => $r(static fn (QBitFlow $c, ?RequestOptions $o) => $c->checkoutSessions->expire(self::SUB_ID, $o),
				'POST', '/checkout-sessions/' . self::SUB_ID . '/expire', '{"uuid":"' . self::SUB_ID . '","status":"expired","expiresAt":"2026-10-01T12:30:00Z"}',
				check: static fn (self $t, Models\CheckoutSessionStatus $v) => $t->assertSame(CheckoutSessionStatusValue::EXPIRED, $v->status)),

			'payments.list' => $r(static fn (QBitFlow $c, ?RequestOptions $o) => $c->payments->list(new Params\PaymentListParams(limit: 50, cursor: self::CURSOR,
				customerUuid: self::UUID_B, productUuid: self::UUID_A, createdAfter: self::after(), createdBefore: self::before(), userUuid: self::MEMBER_UUID,
				reference: 'order-1', refunded: false), $o),
				'GET', '/payments', $page($m('Payment')),
				'createdAfter=' . self::AFTER_Q . '&createdBefore=' . self::BEFORE_Q . '&cursor=' . self::CURSOR . '&customerUuid=' . self::UUID_B
					. '&limit=50&productUuid=' . self::UUID_A . '&reference=order-1&refunded=false&userUuid=' . self::MEMBER_UUID,
				check: static function (self $t, Page $v): void {
					$t->assertSame('pay@1', $v->items[0]->uuid);
					$t->assertSame('BASE:USDC', $v->items[0]->currency->code);
				}),
			'payments.get' => $r(static fn (QBitFlow $c, ?RequestOptions $o) => $c->payments->get(self::PAY_ID, new Params\ReadParams(includeMembers: true), $o),
				'GET', '/payments/' . self::PAY_ID, $m('Payment'), 'includeMembers=true',
				check: static function (self $t, Models\Payment $v): void {
					$t->assertSame(['order-1', '8.5', '10', '1.30'], [$v->reference, $v->priceUsd, $v->amountUsd, $v->fees[0]->amountUsd]);
					$t->assertSame('10', $v->settlement->organizationFee?->feePercent);
				}),
			'payments.get bare uuid' => $r(static fn (QBitFlow $c, ?RequestOptions $o) => $c->payments->get(substr(self::PAY_ID, 4), null, $o),
				'GET', '/payments/' . substr(self::PAY_ID, 4), $m('Payment')),
			'payments.getByReference' => $r(static fn (QBitFlow $c, ?RequestOptions $o) => $c->payments->getByReference('order-1', $o), 'GET', '/payments', $list($m('Payment')), 'reference=order-1'),

			'transactions.list' => $r(static fn (QBitFlow $c, ?RequestOptions $o) => $c->transactions->list(new Params\TransactionListParams(includeMembers: true,
				type: TransactionKind::BILL, subscriptionUuid: self::SUB_ID, refunded: true), $o),
				'GET', '/transactions', $page($m('Transaction')),
				'includeMembers=true&refunded=true&subscriptionUuid=sub%40019eca82-5680-7b00-8000-0000000000d2&type=bill',
				check: static fn (self $t, Page $v) => $t->assertSame([TransactionKind::BILL, 'sub@1'], [$v->items[0]->type, $v->items[0]->subscriptionUuid])),

			'failures.list' => $r(static fn (QBitFlow $c, ?RequestOptions $o) => $c->failures->list(new Params\FailureListParams(limit: 3, kind: FailureKind::BILL,
				category: FailureCategory::ALLOWANCE_EXHAUSTED, subscriptionUuid: self::SUB_ID), $o),
				'GET', '/payment-failures', $page($m('Failure')),
				'category=allowanceExhausted&kind=bill&limit=3&subscriptionUuid=sub%40019eca82-5680-7b00-8000-0000000000d2',
				check: static fn (self $t, Page $v) => $t->assertSame([FailureKind::BILL, '9.99'], [$v->items[0]->kind, $v->items[0]->attempted])),

			'subscriptions.list' => $r(static fn (QBitFlow $c, ?RequestOptions $o) => $c->subscriptions->list(new Params\SubscriptionListParams(customerUuid: self::UUID_B,
				createdAfter: self::after(), status: SubscriptionStatus::PAST_DUE, reference: 'crm:42'), $o),
				'GET', '/subscriptions', $page($m('Subscription')),
				'createdAfter=' . self::AFTER_Q . '&customerUuid=' . self::UUID_B . '&reference=crm%3A42&status=pastDue',
				check: static function (self $t, Page $v): void {
					$t->assertSame(SubscriptionStatus::STOPPED, $v->items[0]->status);
					$t->assertNotNull($v->items[0]->dunning);
				}),
			'subscriptions.get' => $r(static fn (QBitFlow $c, ?RequestOptions $o) => $c->subscriptions->get(self::SUB_ID, null, $o), 'GET', '/subscriptions/' . self::SUB_ID, $m('Subscription')),
			'subscriptions.getByReference' => $r(static fn (QBitFlow $c, ?RequestOptions $o) => $c->subscriptions->getByReference('customer-7731', $o),
				'GET', '/subscriptions', $list($m('Subscription')), 'reference=customer-7731'),
			'subscriptions.update' => $r(static fn (QBitFlow $c, ?RequestOptions $o) => $c->subscriptions->update(self::SUB_ID, new Params\UpdateSubscriptionParams(
				metadata: ['coupon' => '', 'plan' => 'pro-annual']), $o),
				'PATCH', '/subscriptions/' . self::SUB_ID, $m('Subscription'), body: '{"metadata":{"coupon":"","plan":"pro-annual"}}',
				check: static fn (self $t, Models\Subscription $v) => $t->assertSame('pro', $v->metadata['plan'])),
			'subscriptions.listBills' => $r(static fn (QBitFlow $c, ?RequestOptions $o) => $c->subscriptions->listBills(self::SUB_ID, new Params\BillListParams(limit: 100, cursor: self::CURSOR), $o),
				'GET', '/subscriptions/' . self::SUB_ID . '/bills', $page($m('Bill')), 'cursor=' . self::CURSOR . '&limit=100',
				check: static function (self $t, Page $v): void {
					$t->assertSame('sub-hist@1', $v->items[0]->uuid);
					$t->assertSame('sub@1', $v->items[0]->subscriptionUuid);
				}),
			'subscriptions.getBill' => $r(static fn (QBitFlow $c, ?RequestOptions $o) => $c->subscriptions->getBill(self::BILL_ID, new Params\ReadParams(includeMembers: true), $o),
				'GET', '/bills/' . self::BILL_ID, $m('Bill'), 'includeMembers=true'),
			'subscriptions.cancel 202' => $r(static fn (QBitFlow $c, ?RequestOptions $o) => $c->subscriptions->cancel(self::SUB_ID, new Params\CancelSubscriptionParams(immediate: false), $o),
				'POST', '/subscriptions/' . self::SUB_ID . '/cancel', $m('Subscription'), 'immediate=false', status: 202,
				check: static function (self $t, Models\SubscriptionCancellation $v): void {
					$t->assertTrue($v->pending);
					$t->assertSame('sub@1', $v->subscription->uuid);
				}),
			'subscriptions.cancel 200' => $r(static fn (QBitFlow $c, ?RequestOptions $o) => $c->subscriptions->cancel(self::SUB_ID, null, $o),
				'POST', '/subscriptions/' . self::SUB_ID . '/cancel', '{"uuid":"sub@1","status":"cancelled","cancellationReason":"merchant"}',
				check: static function (self $t, Models\SubscriptionCancellation $v): void {
					$t->assertFalse($v->pending);
					$t->assertSame(SubscriptionStatus::CANCELLED, $v->subscription->status);
				}),
			'subscriptions.testBill' => $r(static fn (QBitFlow $c, ?RequestOptions $o) => $c->subscriptions->testBill(self::SUB_ID, $o),
				'POST', '/subscriptions/' . self::SUB_ID . '/test-bill', $m('TestBill'),
				check: static function (self $t, Models\TestBill $v): void {
					$t->assertSame([BillingStage::WAITING, BillingOutcome::PAID, 'insufficient_allowance'], [$v->stage, $v->outcome, $v->failureCode]);
				}),

			'refunds.list' => $r(static fn (QBitFlow $c, ?RequestOptions $o) => $c->refunds->list(new Params\RefundListParams(limit: 25, cursor: self::CURSOR,
				status: [RefundStatus::APPROVED, RefundStatus::REJECTED], txUuid: self::PAY_ID, held: false, includeMembers: true), $o),
				'GET', '/refunds', $page($m('Refund')),
				'cursor=' . self::CURSOR . '&held=false&includeMembers=true&limit=25&status=approved&status=rejected&txUuid=pay%40019eca82-5680-7b00-8000-0000000000d1',
				check: static function (self $t, Page $v): void {
					$t->assertNotNull($v->items[0]->approval);
					$t->assertSame(RefundStatus::APPROVED, $v->items[0]->status);
				}),
			'refunds.get' => $r(static fn (QBitFlow $c, ?RequestOptions $o) => $c->refunds->get(self::REFUND_ID, $o), 'GET', '/refunds/' . self::REFUND_ID, $m('Refund'),
				check: static fn (self $t, Models\Refund $v) => $t->assertSame(['refund@1', '50', '5.0021'], [$v->uuid, $v->refundPercent, $v->amount])),

			'members.list' => $r(static fn (QBitFlow $c, ?RequestOptions $o) => $c->members->list(new Params\MemberListParams(limit: 20, cursor: self::CURSOR), $o),
				'GET', '/members', $page($m('Member')), 'cursor=' . self::CURSOR . '&limit=20',
				check: static fn (self $t, Page $v) => $t->assertSame('BASE:USDC', $v->items[0]->acceptedCurrencies[0]->code)),
			'members.get' => $r(static fn (QBitFlow $c, ?RequestOptions $o) => $c->members->get(self::MEMBER_UUID, $o), 'GET', '/members/' . self::MEMBER_UUID, $m('Member')),
			'members.update' => $r(static fn (QBitFlow $c, ?RequestOptions $o) => $c->members->update(self::MEMBER_UUID, new Params\UpdateMemberParams(organizationFeePercent: '7.5'), $o),
				'PATCH', '/members/' . self::MEMBER_UUID, $m('Member'), body: '{"organizationFeePercent":"7.5"}'),
			'members.remove' => $r(static fn (QBitFlow $c, ?RequestOptions $o) => $c->members->remove(self::MEMBER_UUID, $o), 'DELETE', '/members/' . self::MEMBER_UUID, '', status: 204),
			'members.trust' => $r(static fn (QBitFlow $c, ?RequestOptions $o) => $c->members->trust(self::MEMBER_UUID, $o),
				'POST', '/members/' . self::MEMBER_UUID . '/trust', '{"userUuid":"' . self::MEMBER_UUID . '","trustedAt":"2026-10-07T10:00:00+02:00"}',
				check: static fn (self $t, Models\Member $v) => $t->assertNotNull($v->trustedAt)),
			'members.listHeldFunds' => $r(static fn (QBitFlow $c, ?RequestOptions $o) => $c->members->listHeldFunds($o), 'GET', '/members/held-funds', $list($m('MemberHeldFunds')),
				check: static fn (self $t, array $v) => $t->assertSame([2, '98.5'], [$v[0]->count, $v[0]->totalUsd])),
			'members.getHeldFunds' => $r(static fn (QBitFlow $c, ?RequestOptions $o) => $c->members->getHeldFunds(self::MEMBER_UUID, $o), 'GET', '/members/' . self::MEMBER_UUID . '/held-funds', $m('HeldFunds'),
				check: static fn (self $t, Models\HeldFunds $v) => $t->assertSame('-1.0933', $v->totalUsd)),
			'members.getOwnHeldFunds' => $r(static fn (QBitFlow $c, ?RequestOptions $o) => $c->members->getOwnHeldFunds($o), 'GET', '/held-funds', '{"ledgers":[],"totalUsd":"0"}',
				check: static fn (self $t, Models\HeldFunds $v) => $t->assertSame([[], '0'], [$v->ledgers, $v->totalUsd])),

			'invitations.create' => $r(static fn (QBitFlow $c, ?RequestOptions $o) => $c->invitations->create(new Params\CreateInvitationParams(email: 'seller@example.com', holdFunds: true,
				organizationFeePercent: '2.5', redirectUrl: 'https://shop.example/welcome'), $o),
				'POST', '/invitations', $m('CreatedInvitation'),
				body: '{"email":"seller@example.com","role":"user","holdFunds":true,"organizationFeePercent":"2.5","redirectUrl":"https://shop.example/welcome"}', idempotent: true, status: 201,
				check: static fn (self $t, Models\CreatedInvitation $v) => $t->assertSame([InvitationStatus::PENDING, 'https://qbitflow.app/invite?token=x'], [$v->status, $v->link])),
			'invitations.create minimal' => $r(static fn (QBitFlow $c, ?RequestOptions $o) => $c->invitations->create(new Params\CreateInvitationParams(email: 'seller@example.com', holdFunds: false), $o),
				'POST', '/invitations', $m('CreatedInvitation'), body: '{"email":"seller@example.com","role":"user","holdFunds":false}', idempotent: true, status: 201),
			'invitations.list' => $r(static fn (QBitFlow $c, ?RequestOptions $o) => $c->invitations->list(new Params\InvitationListParams(status: InvitationStatus::PENDING), $o),
				'GET', '/invitations', $page($m('Invitation')), 'status=pending'),
			'invitations.revoke' => $r(static fn (QBitFlow $c, ?RequestOptions $o) => $c->invitations->revoke(self::UUID_A, $o), 'DELETE', '/invitations/' . self::UUID_A, '', status: 204,
				check: static fn (self $t, mixed $v) => $t->assertNull($v)),

			'wallets.list' => $r(static fn (QBitFlow $c, ?RequestOptions $o) => $c->wallets->list(new Params\WalletListParams(withBalances: true), $o),
				'GET', '/wallets', $list($m('Wallet')), 'withBalances=true',
				check: static fn (self $t, array $v) => $t->assertSame('12.5', $v[0]->tokenWallets[0]->balance?->amount)),
			'wallets.listForMember' => $r(static fn (QBitFlow $c, ?RequestOptions $o) => $c->wallets->listForMember(self::MEMBER_UUID, $o), 'GET', '/members/' . self::MEMBER_UUID . '/wallets', $list($m('Wallet'))),
			'wallets.listAcceptedCurrencies' => $r(static fn (QBitFlow $c, ?RequestOptions $o) => $c->wallets->listAcceptedCurrencies(new Params\AcceptedCurrenciesParams(userUuid: self::MEMBER_UUID), $o),
				'GET', '/wallets/accepted-currencies', $list($m('Currency')), 'userUuid=' . self::MEMBER_UUID,
				check: static fn (self $t, array $v) => $t->assertSame(3, $v[0]->mainCurrencyId)),

			'accounting.exportJson' => $r(static fn (QBitFlow $c, ?RequestOptions $o) => $c->accounting->exportJson('2026-09-01', '2026-09-30', $o),
				'GET', '/accounting/export', $list($m('AccountingEvent')), 'format=json&from=2026-09-01&to=2026-09-30',
				check: static fn (self $t, array $v) => $t->assertSame(['refund', 'refund@1'], [$v[0]->type, $v[0]->txUuid])),
			'accounting.exportCsv' => $r(static fn (QBitFlow $c, ?RequestOptions $o) => $c->accounting->exportCsv('2026-09-01', '2026-09-30', $o),
				'GET', '/accounting/export', "txUuid,type\npay@1,payment\n", 'format=csv&from=2026-09-01&to=2026-09-30', accept: 'text/csv, application/json',
				check: static fn (self $t, string $v) => $t->assertSame("txUuid,type\npay@1,payment\n", $v)),

			'webhooks.verifyRemote' => $r(static fn (QBitFlow $c, ?RequestOptions $o) => $c->webhooks->verifyRemote(self::UUID_A, '{"id":"evt_1"}', 't=1790856000,v1=abc', $o),
				'POST', '/webhooks/verify', '{"message":"Signature valid"}',
				body: '{"endpointUuid":"' . self::UUID_A . '","body":"{\"id\":\"evt_1\"}","signature":"t=1790856000,v1=abc"}'),
			'webhooks.endpoints.list' => $r(static fn (QBitFlow $c, ?RequestOptions $o) => $c->webhooks->endpoints->list(new Params\EndpointListParams(limit: 5, cursor: self::CURSOR), $o),
				'GET', '/webhooks/endpoints', $page($m('WebhookEndpointCreated')), 'cursor=' . self::CURSOR . '&limit=5',
				check: static fn (self $t, Page $v) => $t->assertSame('https://x.io/hook', $v->items[0]->url)),
			'webhooks.endpoints.create' => $r(static fn (QBitFlow $c, ?RequestOptions $o) => $c->webhooks->endpoints->create(new Params\CreateWebhookEndpointParams(url: 'https://shop.example/hooks',
				events: [EventType::PAYMENT_COMPLETED, EventType::MEMBER_JOINED], includeMembers: false, description: 'Shop'), $o),
				'POST', '/webhooks/endpoints', $m('WebhookEndpointCreated'),
				body: '{"url":"https://shop.example/hooks","events":["payment.completed","member.joined"],"includeMembers":false,"description":"Shop"}', idempotent: true, status: 201,
				check: static fn (self $t, Models\WebhookEndpointCreated $v) => $t->assertSame('whsec_x', $v->secret)),
			'webhooks.endpoints.get' => $r(static fn (QBitFlow $c, ?RequestOptions $o) => $c->webhooks->endpoints->get(self::UUID_A, $o), 'GET', '/webhooks/endpoints/' . self::UUID_A, $m('WebhookEndpointCreated')),
			'webhooks.endpoints.update' => $r(static fn (QBitFlow $c, ?RequestOptions $o) => $c->webhooks->endpoints->update(self::UUID_A, new Params\UpdateWebhookEndpointParams(events: [],
				description: '', payloadVersion: WebhookPayloadVersion::V2, enabled: true), $o),
				'PATCH', '/webhooks/endpoints/' . self::UUID_A, $m('WebhookEndpointCreated'), body: '{"events":[],"description":"","payloadVersion":"v2","enabled":true}'),
			'webhooks.endpoints.delete' => $r(static fn (QBitFlow $c, ?RequestOptions $o) => $c->webhooks->endpoints->delete(self::UUID_A, $o), 'DELETE', '/webhooks/endpoints/' . self::UUID_A, '', status: 204),
			'webhooks.events.list' => $r(static fn (QBitFlow $c, ?RequestOptions $o) => $c->webhooks->events->list(new Params\EventListParams(limit: 2, cursor: self::CURSOR,
				type: EventType::WEBHOOK_TEST, includeMembers: true), $o),
				'GET', '/webhooks/events', Fixtures::page(Fixtures::webhookTestEvent(), null), 'cursor=' . self::CURSOR . '&includeMembers=true&limit=2&type=webhook.test',
				check: static function (self $t, Page $v): void {
					$t->assertFalse($v->hasMore);
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
	public function exactly_six_methods_are_idempotent(): void
	{
		$idempotent = array_filter(self::routes(), static fn (array $route): bool => $route[5]);
		$methods = array_unique(array_map(static fn (string $name): string => explode(' ', $name)[0], array_keys($idempotent)));
		$this->assertCount(6, $methods, 'variants of a method count once');
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

		$client = $this->client(MockHttpClient::static(400, '{"error":"too long","code":"validation_failed","details":{"max":92,"errors":[{"field":"to","message":"at most 92 days"}]}}'));
		$this->expectExceptionThrown(ValidationException::class, static fn () => $client->accounting->exportCsv('2026-01-01', '2026-09-30'));

		$client = $this->client(MockHttpClient::static(400, '{"error":"live subscription","code":"test_mode_only"}'));
		$this->expectExceptionThrown(BadRequestException::class, static fn () => $client->subscriptions->testBill(self::SUB_ID));

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
			'products.getByReference blank' => ['reference', static fn (QBitFlow $c) => $c->products->getByReference('  ')],
			'products.getByReference charset' => ['reference', static fn (QBitFlow $c) => $c->products->getByReference('a/b c')],
			'products.getByReference too long' => ['reference', static fn (QBitFlow $c) => $c->products->getByReference(str_repeat('r', 101))],
			'products.list cursor' => ['cursor', static fn (QBitFlow $c) => $c->products->list(new Params\ProductListParams(cursor: str_repeat('c', 201)))],
			'products.list reference' => ['reference', static fn (QBitFlow $c) => $c->products->list(new Params\ProductListParams(reference: 'a b'))],
			'customers.get' => ['uuid', static fn (QBitFlow $c) => $c->customers->get('pay@' . self::UUID_A)],
			'customers.update' => ['uuid', static fn (QBitFlow $c) => $c->customers->update('', new Params\UpdateCustomerParams())],
			'customers.delete' => ['uuid', static fn (QBitFlow $c) => $c->customers->delete('x')],
			'customers.getByReference' => ['reference', static fn (QBitFlow $c) => $c->customers->getByReference('')],
			'checkoutSessions.get' => ['uuid', static fn (QBitFlow $c) => $c->checkoutSessions->get('')],
			'checkoutSessions.expire' => ['uuid', static fn (QBitFlow $c) => $c->checkoutSessions->expire('cs_123')],
			'payments.get' => ['uuid', static fn (QBitFlow $c) => $c->payments->get('pay@123')],
			'payments.getByReference' => ['reference', static fn (QBitFlow $c) => $c->payments->getByReference('')],
			'subscriptions.get' => ['uuid', static fn (QBitFlow $c) => $c->subscriptions->get('bad@' . self::UUID_A)],
			'subscriptions.getByReference' => ['reference', static fn (QBitFlow $c) => $c->subscriptions->getByReference('')],
			'subscriptions.update uuid' => ['uuid', static fn (QBitFlow $c) => $c->subscriptions->update('', new Params\UpdateSubscriptionParams())],
			'subscriptions.listBills' => ['uuid', static fn (QBitFlow $c) => $c->subscriptions->listBills('')],
			'subscriptions.getBill' => ['billUuid', static fn (QBitFlow $c) => $c->subscriptions->getBill('1')],
			'subscriptions.cancel' => ['uuid', static fn (QBitFlow $c) => $c->subscriptions->cancel('', new Params\CancelSubscriptionParams(true))],
			'subscriptions.testBill' => ['uuid', static fn (QBitFlow $c) => $c->subscriptions->testBill('sub')],
			'refunds.get' => ['uuid', static fn (QBitFlow $c) => $c->refunds->get('refund@1')],
			'members.get' => ['userUuid', static fn (QBitFlow $c) => $c->members->get('')],
			'members.update' => ['userUuid', static fn (QBitFlow $c) => $c->members->update('1', new Params\UpdateMemberParams('0'))],
			'members.remove' => ['userUuid', static fn (QBitFlow $c) => $c->members->remove('')],
			'members.trust' => ['userUuid', static fn (QBitFlow $c) => $c->members->trust('x')],
			'members.getHeldFunds' => ['userUuid', static fn (QBitFlow $c) => $c->members->getHeldFunds('')],
			'invitations.revoke' => ['uuid', static fn (QBitFlow $c) => $c->invitations->revoke('')],
			'wallets.listForMember' => ['userUuid', static fn (QBitFlow $c) => $c->wallets->listForMember('1')],
			'webhooks.endpoints.get' => ['uuid', static fn (QBitFlow $c) => $c->webhooks->endpoints->get('')],
			'webhooks.endpoints.update' => ['uuid', static fn (QBitFlow $c) => $c->webhooks->endpoints->update('x', new Params\UpdateWebhookEndpointParams())],
			'webhooks.endpoints.delete' => ['uuid', static fn (QBitFlow $c) => $c->webhooks->endpoints->delete('')],
			'webhooks.endpoints.list cursor' => ['cursor', static fn (QBitFlow $c) => $c->webhooks->endpoints->list(new Params\EndpointListParams(cursor: str_repeat('c', 201)))],
			'webhooks.events.get' => ['id', static fn (QBitFlow $c) => $c->webhooks->events->get('')],
			'webhooks.verifyRemote uuid' => ['endpointUuid', static fn (QBitFlow $c) => $c->webhooks->verifyRemote('', '{}', 't=1,v1=a')],
			'webhooks.verifyRemote body' => ['body', static fn (QBitFlow $c) => $c->webhooks->verifyRemote(self::UUID_A, '', 't=1,v1=a')],
			'webhooks.verifyRemote utf8' => ['body', static fn (QBitFlow $c) => $c->webhooks->verifyRemote(self::UUID_A, "\xff", 't=1,v1=a')],
			'currencies.get' => ['id', static fn (QBitFlow $c) => $c->currencies->get(0)],

			'products.create price' => ['priceUsd', static fn (QBitFlow $c) => $c->products->create(new Params\CreateProductParams(name: 'Pro', priceUsd: '-1'))],
			'products.create price float form' => ['priceUsd', static fn (QBitFlow $c) => $c->products->create(new Params\CreateProductParams(name: 'Pro', priceUsd: '1e2'))],
			'products.create metadata key' => ['metadata', static fn (QBitFlow $c) => $c->products->create(new Params\CreateProductParams(name: 'Pro', priceUsd: '1', metadata: ['bad key' => 'x']))],
			'products.update name' => ['name', static fn (QBitFlow $c) => $c->products->update(self::UUID_A, new Params\UpdateProductParams(name: '<b>'))],
			'customers.create email' => ['email', static fn (QBitFlow $c) => $c->customers->create(new Params\CreateCustomerParams(firstName: 'Ada', email: 'ada'))],
			'customers.update phone' => ['phoneNumber', static fn (QBitFlow $c) => $c->customers->update(self::UUID_A, new Params\UpdateCustomerParams(phoneNumber: 'call me'))],
			'customers.update metadata value' => ['metadata.note', static fn (QBitFlow $c) => $c->customers->update(self::UUID_A, new Params\UpdateCustomerParams(metadata: ['note' => "a\tb"]))],
			'customers.list email' => ['email', static fn (QBitFlow $c) => $c->customers->list(new Params\CustomerListParams(email: 'x'))],
			'checkoutSessions.createPayment mixed' => ['productUuid', static fn (QBitFlow $c) => $c->checkoutSessions->createPayment(new Params\CreatePaymentSessionParams(productUuid: self::UUID_A, productReference: 'pro'))],
			'checkoutSessions.createPayment fees' => ['fees.items[0].amountUsd', static fn (QBitFlow $c) => $c->checkoutSessions->createPayment(new Params\CreatePaymentSessionParams(productUuid: self::UUID_A,
				fees: new Params\CheckoutFees(items: [new Params\FeeItem('Shipping', '1.999')])))],
			'checkoutSessions.createSubscription fee label' => ['fees.items[0].label', static fn (QBitFlow $c) => $c->checkoutSessions->createSubscription(new Params\CreateSubscriptionSessionParams(productUuid: self::UUID_A,
				fees: new Params\CheckoutFees(items: [new Params\FeeItem('', '2')])))],
			'checkoutSessions.createSubscription frequency' => ['frequency.unit', static fn (QBitFlow $c) => $c->checkoutSessions->createSubscription(new Params\CreateSubscriptionSessionParams(productUuid: self::UUID_A, frequency: new Models\Duration(1)))],
			'payments.list exclusive' => ['userUuid', static fn (QBitFlow $c) => $c->payments->list(new Params\PaymentListParams(includeMembers: true, userUuid: self::MEMBER_UUID))],
			'transactions.list type' => ['type', static fn (QBitFlow $c) => $c->transactions->list(new Params\TransactionListParams(type: 'subscriptionHistory'))],
			'failures.list kind' => ['kind', static fn (QBitFlow $c) => $c->failures->list(new Params\FailureListParams(kind: 'refund'))],
			'subscriptions.update metadata' => ['metadata', static fn (QBitFlow $c) => $c->subscriptions->update(self::SUB_ID, new Params\UpdateSubscriptionParams(metadata: array_fill_keys(array_map(static fn (int $i): string => 'k' . $i, range(1, 51)), 'v')))],
			'refunds.list userUuid' => ['userUuid', static fn (QBitFlow $c) => $c->refunds->list(new Params\RefundListParams(userUuid: '1'))],
			'refunds.list exclusive' => ['userUuid', static fn (QBitFlow $c) => $c->refunds->list(new Params\RefundListParams(includeMembers: true, userUuid: self::MEMBER_UUID))],
			'refunds.list status' => ['status[1]', static fn (QBitFlow $c) => $c->refunds->list(new Params\RefundListParams(status: [RefundStatus::PENDING, 'denied']))],
			'refunds.list too many statuses' => ['status', static fn (QBitFlow $c) => $c->refunds->list(new Params\RefundListParams(status: array_merge(RefundStatus::values(), [RefundStatus::PENDING, RefundStatus::APPROVED])))],
			'members.update fee' => ['organizationFeePercent', static fn (QBitFlow $c) => $c->members->update(self::MEMBER_UUID, new Params\UpdateMemberParams('51'))],
			'members.update fee required' => ['organizationFeePercent', static fn (QBitFlow $c) => $c->members->update(self::MEMBER_UUID, new Params\UpdateMemberParams(''))],
			'invitations.create email' => ['email', static fn (QBitFlow $c) => $c->invitations->create(new Params\CreateInvitationParams(email: '', holdFunds: false))],
			'invitations.create fee' => ['organizationFeePercent', static fn (QBitFlow $c) => $c->invitations->create(new Params\CreateInvitationParams(email: 'a@b.co', holdFunds: false, organizationFeePercent: '2.505'))],
			'invitations.list status' => ['status', static fn (QBitFlow $c) => $c->invitations->list(new Params\InvitationListParams(status: 'open'))],
			'wallets.listAcceptedCurrencies' => ['userUuid', static fn (QBitFlow $c) => $c->wallets->listAcceptedCurrencies(new Params\AcceptedCurrenciesParams(userUuid: 'x'))],
			'accounting.exportJson order' => ['to', static fn (QBitFlow $c) => $c->accounting->exportJson('2026-09-30', '2026-09-01')],
			'accounting.exportCsv date' => ['from', static fn (QBitFlow $c) => $c->accounting->exportCsv('2026-02-30', '2026-03-01')],
			'webhooks.endpoints.create url' => ['url', static fn (QBitFlow $c) => $c->webhooks->endpoints->create(new Params\CreateWebhookEndpointParams(url: 'ftp://x'))],
			'webhooks.endpoints.update test event' => ['events[0]', static fn (QBitFlow $c) => $c->webhooks->endpoints->update(self::UUID_A, new Params\UpdateWebhookEndpointParams(events: [EventType::WEBHOOK_TEST]))],
			'webhooks.endpoints.update payload version' => ['payloadVersion', static fn (QBitFlow $c) => $c->webhooks->endpoints->update(self::UUID_A, new Params\UpdateWebhookEndpointParams(payloadVersion: 'v1'))],
			'webhooks.events.list cursor' => ['cursor', static fn (QBitFlow $c) => $c->webhooks->events->list(new Params\EventListParams(cursor: str_repeat('c', 201)))],
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
			'products.iterate' => ['/products', 'includeHidden=true&limit=2', $uuidItem,
				static fn (QBitFlow $c, int $n) => $collect($c->products->iterate(new Params\ProductListParams(limit: 2, includeHidden: true)), $byUuid, $n)],
			'customers.iterate' => ['/customers', 'email=a%40b.co&limit=2&verified=false', $uuidItem,
				static fn (QBitFlow $c, int $n) => $collect($c->customers->iterate(new Params\CustomerListParams(limit: 2, email: 'a@b.co', verified: false)), $byUuid, $n)],
			'payments.iterate' => ['/payments', 'createdAfter=' . self::AFTER_Q . '&limit=2', $uuidItem,
				static fn (QBitFlow $c, int $n) => $collect($c->payments->iterate(new Params\PaymentListParams(limit: 2, createdAfter: self::after())), $byUuid, $n)],
			'transactions.iterate' => ['/transactions', 'type=payment', $uuidItem,
				static fn (QBitFlow $c, int $n) => $collect($c->transactions->iterate(new Params\TransactionListParams(type: TransactionKind::PAYMENT)), $byUuid, $n)],
			'failures.iterate' => ['/payment-failures', 'category=reverted', $uuidItem,
				static fn (QBitFlow $c, int $n) => $collect($c->failures->iterate(new Params\FailureListParams(category: FailureCategory::REVERTED)), $byUuid, $n)],
			'subscriptions.iterate' => ['/subscriptions', 'includeMembers=true&status=active', $uuidItem,
				static fn (QBitFlow $c, int $n) => $collect($c->subscriptions->iterate(new Params\SubscriptionListParams(includeMembers: true, status: SubscriptionStatus::ACTIVE)), $byUuid, $n)],
			'subscriptions.iterateBills' => ['/subscriptions/' . self::SUB_ID . '/bills', 'limit=100', $uuidItem,
				static fn (QBitFlow $c, int $n) => $collect($c->subscriptions->iterateBills(self::SUB_ID, new Params\BillListParams(limit: 100)), $byUuid, $n)],
			'refunds.iterate' => ['/refunds', 'held=false&status=pending', $uuidItem,
				static fn (QBitFlow $c, int $n) => $collect($c->refunds->iterate(new Params\RefundListParams(status: [RefundStatus::PENDING], held: false)), $byUuid, $n)],
			'members.iterate' => ['/members', 'limit=1', static fn (int $i): string => sprintf('{"userUuid":"id%d"}', $i),
				static fn (QBitFlow $c, int $n) => $collect($c->members->iterate(new Params\MemberListParams(limit: 1)), static fn (object $v): string => $v->userUuid, $n)],
			'invitations.iterate' => ['/invitations', 'status=expired', $uuidItem,
				static fn (QBitFlow $c, int $n) => $collect($c->invitations->iterate(new Params\InvitationListParams(status: InvitationStatus::EXPIRED)), $byUuid, $n)],
			'webhooks.endpoints.iterate' => ['/webhooks/endpoints', 'limit=1', $uuidItem,
				static fn (QBitFlow $c, int $n) => $collect($c->webhooks->endpoints->iterate(new Params\EndpointListParams(limit: 1)), $byUuid, $n)],
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
				'' => MockHttpClient::response(200, '{"items":[' . $item(0) . ',' . $item(1) . '],"hasMore":true,"nextCursor":"id1"}'),
				'id1' => MockHttpClient::response(200, '{"items":[' . $item(2) . '],"hasMore":false}'),
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
				? MockHttpClient::response(200, '{"items":[{"uuid":"id0"}],"hasMore":true,"nextCursor":"id0"}')
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

	/**
	 * A bounded list answers one page; one that claims more is followed with its `nextCursor`
	 * (the filters kept) until `hasMore` is false, so it is never cut short silently.
	 */
	#[Test]
	public function a_bounded_list_follows_a_next_cursor(): void
	{
		$client = $this->client(new MockHttpClient(static function (RequestInterface $r): ResponseInterface {
			parse_str($r->getUri()->getQuery(), $q);

			return match ($q['cursor'] ?? '') {
				'' => MockHttpClient::response(200, '{"items":[{"id":1},{"id":2}],"hasMore":true,"nextCursor":"c1"}'),
				'c1' => MockHttpClient::response(200, '{"items":[{"id":3}],"hasMore":false}'),
				default => MockHttpClient::response(500, '{"error":"unexpected"}'),
			};
		}));

		$currencies = $client->currencies->list(new Params\CurrencyListParams(test: true));
		$this->assertSame([1, 2, 3], array_map(static fn (Models\Currency $c): int => $c->id, $currencies));
		$this->assertSame(2, $this->http->count());
		$this->assertSame(['test=true', 'cursor=c1&test=true'], array_map(static fn (RequestInterface $r): string => $r->getUri()->getQuery(), $this->http->requests));
	}

	/** @return array<string,array{0: string}> */
	public static function boundedListGuards(): array
	{
		return [
			'more without a cursor' => ['{"items":[{"id":1}],"hasMore":true}'],
			'empty cursor' => ['{"items":[{"id":1}],"hasMore":true,"nextCursor":""}'],
			'empty page claiming more' => ['{"items":[],"hasMore":true,"nextCursor":"c1"}'],
		];
	}

	#[Test]
	#[DataProvider('boundedListGuards')]
	public function a_bounded_list_stops_on_the_iterator_guards(string $page): void
	{
		$client = $this->client(MockHttpClient::static(200, $page));
		$client->wallets->list();
		$this->assertSame(1, $this->http->count(), 'one request: the walk stops');
	}

	#[Test]
	public function a_bounded_list_stops_on_a_cursor_that_does_not_move(): void
	{
		$client = $this->client(MockHttpClient::static(200, '{"items":[{"userUuid":"m-1"}],"hasMore":true,"nextCursor":"same"}'));
		$this->assertCount(2, $client->members->listHeldFunds(), 'the first page, then the cursor\'s page');
		$this->assertSame(2, $this->http->count(), 'the second answer repeats the cursor just sent: stop');
		$this->assertSame('cursor=same', $this->http->last()->getUri()->getQuery());
	}

	#[Test]
	public function a_bounded_list_refuses_a_bare_array(): void
	{
		$client = $this->client(MockHttpClient::static(200, '[{"id":8}]'));
		$this->expectException(ServerException::class);
		$client->currencies->list();
	}

	#[Test]
	public function get_by_reference_returns_the_exact_match_or_a_not_found(): void
	{
		$client = $this->client(MockHttpClient::static(200, '{"items":[{"uuid":"c-9","reference":"CRM-1"}],"hasMore":false}', ['X-Request-Id' => 'req-7']));
		try {
			$client->customers->getByReference('crm-1');
			$this->fail('want a NotFoundException');
		} catch (NotFoundException $e) {
			$this->assertSame([404, 'not_found', 'req-7', []], [$e->status, $e->apiCode, $e->requestId, $e->details], 'like a 404, with the list answer\'s request id');
			$this->assertSame('no customer with this reference', $e->errorMessage);
		}
		$this->assertSame('reference=crm-1', $this->http->last()->getUri()->getQuery());

		$client = $this->client(MockHttpClient::static(200, '{"items":[],"hasMore":false}'));
		$this->expectExceptionThrown(NotFoundException::class, static fn () => $client->subscriptions->getByReference('customer-7731'));
		$this->expectExceptionThrown(NotFoundException::class, static fn () => $client->payments->getByReference('order-1'));

		$client = $this->client(MockHttpClient::static(404, '{"error":"no such space","code":"not_found","requestId":"r-404"}'));
		try {
			$client->products->getByReference('pro');
			$this->fail('want a NotFoundException');
		} catch (NotFoundException $e) {
			$this->assertSame('r-404', $e->requestId, 'an API error answer is the API\'s');
		}
	}

	/** @return array<string,array{0: string}> */
	public static function iteratorGuards(): array
	{
		return [
			'more without a cursor' => ['{"items":[{"uuid":"a"}],"hasMore":true}'],
			'cursor that does not move' => ['{"items":[{"uuid":"a"}],"hasMore":true,"nextCursor":"' . self::CURSOR . '"}'],
			'empty page claiming more' => ['{"items":[],"hasMore":true,"nextCursor":"elsewhere"}'],
			'last page' => ['{"items":[{"uuid":"a"}],"hasMore":false,"nextCursor":"ignored"}'],
		];
	}

	#[Test]
	#[DataProvider('iteratorGuards')]
	public function an_iterator_stops_on_its_guards(string $page): void
	{
		$client = $this->client(MockHttpClient::static(200, $page));
		iterator_to_array($client->refunds->iterate(new Params\RefundListParams(cursor: self::CURSOR)), false);
		$this->assertSame(1, $this->http->count(), 'one request, never an extra page');
		$this->assertStringContainsString('cursor=' . self::CURSOR, $this->http->last()->getUri()->getQuery(), 'the walk starts at the given cursor');
	}

	#[Test]
	public function a_delete_answers_nothing(): void
	{
		foreach ([[204, ''], [200, '{"message":"deleted"}']] as [$status, $body]) {
			$client = $this->client(MockHttpClient::static($status, $body));
			$client->webhooks->endpoints->delete(self::UUID_A);
			$client->invitations->revoke(self::UUID_A);
			$this->assertSame(['DELETE', 'DELETE'], array_map(static fn (RequestInterface $r): string => $r->getMethod(), $this->http->requests));
		}
	}
}
