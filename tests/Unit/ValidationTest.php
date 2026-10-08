<?php

declare(strict_types=1);

namespace QBitFlow\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use QBitFlow\Enums\EventType;
use QBitFlow\Enums\SubscriptionStatus;
use QBitFlow\Enums\WebhookPayloadVersion;
use QBitFlow\Http\Transport;
use QBitFlow\Models\Duration;
use QBitFlow\Params\CombinedPaymentListParams;
use QBitFlow\Params\CreateCustomerParams;
use QBitFlow\Params\CreateInvitationParams;
use QBitFlow\Params\CreatePaymentSessionParams;
use QBitFlow\Params\CreateProductParams;
use QBitFlow\Params\CreateSubscriptionSessionParams;
use QBitFlow\Params\CreateWebhookEndpointParams;
use QBitFlow\Params\CustomerListParams;
use QBitFlow\Params\EventListParams;
use QBitFlow\Params\FailureListParams;
use QBitFlow\Params\InitiateRefundParams;
use QBitFlow\Params\InvitationListParams;
use QBitFlow\Params\PaymentListParams;
use QBitFlow\Params\RefundListParams;
use QBitFlow\Params\SubscriptionListParams;
use QBitFlow\Params\SubscriptionTermsParams;
use QBitFlow\Params\SupportedCurrenciesParams;
use QBitFlow\Params\UpdateCustomerParams;
use QBitFlow\Params\UpdateMemberParams;
use QBitFlow\Params\UpdateProductParams;
use QBitFlow\Params\UpdateWebhookEndpointParams;
use QBitFlow\Support\Validator;
use QBitFlow\Tests\Support\TestCase;

/**
 * The client-side checks (behaviour §8, §10) and what update params send.
 */
final class ValidationTest extends TestCase
{
	private const UUID = '019eca82-5680-7b00-8000-0000000000b1';

	/** @param list<string> $want */
	private function check(string $name, object $params, array $want = []): void
	{
		$this->assertSame($want, $this->failingFields(static fn () => $params->validate()), $name);
	}

	#[Test]
	public function the_name_rule(): void
	{
		foreach (['Pro plan', 'O’Brien', 'Smith & Co', 'prod/backend', 'Dr. Who (II)', 'Zoë', '李小龙', 'ab', str_repeat('é', 100)] as $s) {
			$this->check('name ' . $s, new CreateProductParams(name: $s, price: 1));
		}
		foreach (['a', '  ', " \t ", "line\nbreak", "tab\there", '<b>', 'x{y}', 'a[1]', 'back`tick', 'back\\slash', 'pi|pe', 'semi;colon',
			'quo"te', 'til~de', 'car^et', "bidi\u{202E}name", "bidi\u{2066}x", "nul\x00x", str_repeat('é', 101), "\u{A0}\u{A0}\u{A0}"] as $s) {
			$this->check('name ' . $s, new CreateProductParams(name: $s, price: 1), ['name']);
		}
		$this->check('lastName 1 char', new CreateCustomerParams(name: 'Ada', email: 'a@b.co', lastName: 'L'));
		$this->check('lastName newline', new CreateCustomerParams(name: 'Ada', email: 'a@b.co', lastName: "L\n"), ['lastName']);
	}

	#[Test]
	public function the_text_rule(): void
	{
		foreach (["Two\nlines\r\nand\ttab", 'Lifetime access (Pro) & more: 100%', 'ok'] as $s) {
			$this->check('description', new CreateProductParams(name: 'Pro', price: 1, description: $s));
		}
		foreach (['   ', "\n\n", '<script>', "x\x07bell", str_repeat('x', 501), 'a'] as $s) {
			$this->check('description', new CreateProductParams(name: 'Pro', price: 1, description: $s), ['description']);
		}
		$this->check('address 1 char', new CreateCustomerParams(name: 'Ada', email: 'a@b.co', address: 'x'));
		$this->check('address 501', new CreateCustomerParams(name: 'Ada', email: 'a@b.co', address: str_repeat('x', 501)), ['address']);
		$this->check('endpoint description 201', new CreateWebhookEndpointParams(url: 'https://x.io', description: str_repeat('x', 201)), ['description']);
		$this->check('refund reason', new InitiateRefundParams(txUuid: 'pay@' . self::UUID, reason: 'Broken, can you refund?', merchantMessage: "Sorry\nTeam"));
		$this->check('refund reason bad', new InitiateRefundParams(txUuid: 'pay@' . self::UUID, reason: '{x}', merchantMessage: str_repeat('m', 501)), ['reason', 'merchantMessage']);
	}

	#[Test]
	public function the_reference_rule(): void
	{
		foreach (['order-1042', 'INV_2026.10:01@shop', 'a', str_repeat('r', 100)] as $s) {
			$this->check('reference ' . $s, new CreateCustomerParams(name: 'Ada', email: 'a@b.co', reference: $s));
		}
		foreach (['has space', 'slash/x', 'é', '#1', str_repeat('r', 101)] as $s) {
			$this->check('reference ' . $s, new CreateCustomerParams(name: 'Ada', email: 'a@b.co', reference: $s), ['reference']);
		}
		$this->check('session refs', new CreatePaymentSessionParams(productReference: 'bad ref', reference: 'o/1', customerReference: 'c 1'),
			['reference', 'productReference', 'customerReference']);
		$this->check('subscription filter', new SubscriptionListParams(reference: 'bad ref'), ['reference']);
	}

	#[Test]
	public function the_phone_rule(): void
	{
		foreach (['+33 6 12 34 56 78', '555 (123) 4567', '06.12.34.56.78', '123456'] as $s) {
			$this->check('phone ' . $s, new CreateCustomerParams(name: 'Ada', email: 'a@b.co', phoneNumber: $s));
		}
		foreach (['12345', '+', 'phone', '+33 6 12 34 56 7x', '-123456', '123456-', '+' . str_repeat('1', 32)] as $s) {
			$this->check('phone ' . $s, new CreateCustomerParams(name: 'Ada', email: 'a@b.co', phoneNumber: $s), ['phoneNumber']);
		}
	}

	#[Test]
	public function the_email_rule(): void
	{
		foreach (['a@b.co', 'First.Last+tag@Example.COM', 'x@sub.domain.io'] as $s) {
			$this->check('email ' . $s, new CreateCustomerParams(name: 'Ada', email: $s));
		}
		foreach (['no-at', 'a@b', '@b.co', 'a@.b.co', 'a@b.co.', 'a b@c.io', 'a@@b.co', 'a@b@c.io', str_repeat('a', 250) . '@b.co'] as $s) {
			$this->check('email ' . $s, new CreateCustomerParams(name: 'Ada', email: $s), ['email']);
		}
		$this->check('email required', new CreateCustomerParams(name: 'Ada', email: ''), ['email']);
		$this->check('invitation email', new CreateInvitationParams(email: 'nope'), ['email']);
		$this->check('filter email', new CustomerListParams(email: 'nope'), ['email']);
	}

	#[Test]
	public function the_url_rule(): void
	{
		foreach (['https://shop.example.com/thanks?id={{UUID}}&t={{TRANSACTION_TYPE}}', 'http://localhost:8080/x', 'HTTPS://EXAMPLE.COM'] as $s) {
			$this->check('url ' . $s, new CreatePaymentSessionParams(productUuid: self::UUID, successUrl: $s, cancelUrl: $s));
		}
		foreach (['ftp://x.io', '/relative', 'https://', 'javascript:alert(1)', 'https://x.io/' . str_repeat('p', 2040), 'https://shop example.com/x'] as $s) {
			$this->check('url ' . $s, new CreatePaymentSessionParams(productUuid: self::UUID, successUrl: $s), ['successUrl']);
		}
		$this->check('redirectUrl', new CreateInvitationParams(email: 'a@b.co', redirectUrl: 'nope'), ['redirectUrl']);
		$this->check('endpoint url required', new CreateWebhookEndpointParams(url: ''), ['url']);
		$this->check('endpoint url update', new UpdateWebhookEndpointParams(url: 'x'), ['url']);
	}

	#[Test]
	public function the_price_rule(): void
	{
		foreach ([0.01, 5, 1e6] as $p) {
			$this->check('price', new CreateProductParams(name: 'Pro', price: $p));
		}
		foreach ([0, -1, NAN, INF] as $p) {
			$this->check('price', new CreateProductParams(name: 'Pro', price: $p), ['price']);
		}
		$this->check('update price', new UpdateProductParams(price: 0.0), ['price']);
		$this->check('update no price', new UpdateProductParams());
	}

	#[Test]
	public function the_percent_rule(): void
	{
		foreach ([0.01, 1.5, 33.33, 100] as $p) {
			$this->check('refund', new InitiateRefundParams(txUuid: self::UUID, refundPercent: $p));
		}
		$tenth = 0.1;
		foreach ([0, -1, 100.01, 1.155, $tenth + 0.2, NAN, INF] as $p) {
			$this->check('refund ' . var_export($p, true), new InitiateRefundParams(txUuid: self::UUID, refundPercent: $p), ['refundPercent']);
		}
		foreach ([0, 0.05, 1.5, 50] as $p) {
			$this->check('member fee', new UpdateMemberParams($p));
			$this->check('invitation fee', new CreateInvitationParams(email: 'a@b.co', organizationFeePercent: $p));
		}
		foreach ([-0.01, 50.01, 1.234] as $p) {
			$this->check('member fee', new UpdateMemberParams($p), ['organizationFeePercent']);
		}
	}

	#[Test]
	public function the_duration_rule(): void
	{
		$month = Duration::months(1);
		$cases = [
			'monthly' => [new SubscriptionTermsParams($month), []],
			'1 year' => [new SubscriptionTermsParams(Duration::years(1)), []],
			'365 days' => [new SubscriptionTermsParams(Duration::days(365)), []],
			'52 weeks' => [new SubscriptionTermsParams(Duration::weeks(52)), []],
			'12 months' => [new SubscriptionTermsParams(Duration::months(12)), []],
			'5 seconds (the mode minimum is the API\'s)' => [new SubscriptionTermsParams(Duration::seconds(5)), []],
			'13 months > 1 year' => [new SubscriptionTermsParams(Duration::months(13)), ['subscription.frequency']],
			'2 years' => [new SubscriptionTermsParams(Duration::years(2)), ['subscription.frequency']],
			'max uint32' => [new SubscriptionTermsParams(Duration::years(4294967295)), ['subscription.frequency']],
			'frequency 0' => [new SubscriptionTermsParams(Duration::days(0)), ['subscription.frequency.value']],
			'no unit' => [new SubscriptionTermsParams(new Duration(3)), ['subscription.frequency.unit']],
			'bad unit' => [new SubscriptionTermsParams(new Duration(1, 'fortnights')), ['subscription.frequency.unit']],
			'frequency required on create' => [new SubscriptionTermsParams(), ['subscription.frequency']],
			'trial 0 without unit' => [new SubscriptionTermsParams($month, new Duration()), []],
			'trial 14 days' => [new SubscriptionTermsParams($month, Duration::days(14)), []],
			'trial no unit' => [new SubscriptionTermsParams($month, new Duration(14)), ['subscription.trialPeriod.unit']],
			'trial 0 bad unit' => [new SubscriptionTermsParams($month, new Duration(0, 'x')), ['subscription.trialPeriod.unit']],
			'minPeriods 1000' => [new SubscriptionTermsParams($month, minPeriods: 1000), []],
			'minPeriods 0' => [new SubscriptionTermsParams($month, minPeriods: 0), []],
			'minPeriods 1001' => [new SubscriptionTermsParams($month, minPeriods: 1001), ['subscription.minPeriods']],
		];
		foreach ($cases as $name => [$terms, $want]) {
			$this->check($name, new CreateProductParams(name: 'Pro', price: 1, subscription: $terms), $want);
		}

		$this->check('session no terms', new CreateSubscriptionSessionParams(productUuid: self::UUID));
		$this->check('session terms', new CreateSubscriptionSessionParams(productUuid: self::UUID, frequency: new Duration(1), minPeriods: 2000), ['frequency.unit', 'minPeriods']);
		$this->check('update trial only', new UpdateProductParams(subscription: new SubscriptionTermsParams(trialPeriod: new Duration())));
	}

	#[Test]
	public function the_session_product_choice(): void
	{
		$cases = [
			'by uuid' => [new CreatePaymentSessionParams(productUuid: self::UUID), []],
			'by reference' => [new CreatePaymentSessionParams(productReference: 'pro-plan'), []],
			'inline' => [new CreatePaymentSessionParams(productName: 'Pro', price: 10), []],
			'inline + description' => [new CreatePaymentSessionParams(productName: 'Pro', description: 'Lifetime', price: 10), []],
			'nothing' => [new CreatePaymentSessionParams(), ['productUuid']],
			'uuid + reference' => [new CreatePaymentSessionParams(productUuid: self::UUID, productReference: 'x'), ['productUuid']],
			'uuid + inline' => [new CreatePaymentSessionParams(productUuid: self::UUID, productName: 'Pro', price: 1), ['productUuid']],
			'uuid + description' => [new CreatePaymentSessionParams(productUuid: self::UUID, description: 'x y'), ['productUuid']],
			'inline without price' => [new CreatePaymentSessionParams(productName: 'Pro'), ['price']],
			'inline without name' => [new CreatePaymentSessionParams(price: 3), ['productName']],
			'inline negative price' => [new CreatePaymentSessionParams(productName: 'Pro', price: -3), ['price']],
			'bad uuid' => [new CreatePaymentSessionParams(productUuid: '42'), ['productUuid']],
			'bad customer uuid' => [new CreatePaymentSessionParams(productUuid: self::UUID, customerUuid: 'x'), ['customerUuid']],
			'expires 0 (unset)' => [new CreatePaymentSessionParams(productUuid: self::UUID, expiresInMinutes: 0), []],
			'expires 10' => [new CreatePaymentSessionParams(productUuid: self::UUID, expiresInMinutes: 10), []],
			'expires 1440' => [new CreatePaymentSessionParams(productUuid: self::UUID, expiresInMinutes: 1440), []],
			'expires 9' => [new CreatePaymentSessionParams(productUuid: self::UUID, expiresInMinutes: 9), ['expiresInMinutes']],
			'expires 1441' => [new CreatePaymentSessionParams(productUuid: self::UUID, expiresInMinutes: 1441), ['expiresInMinutes']],
		];
		foreach ($cases as $name => [$params, $want]) {
			$this->check($name, $params, $want);
		}
		$this->check('subscription inline', new CreateSubscriptionSessionParams(productName: 'Pro', price: 9.99, frequency: Duration::months(1)));
		$this->check('subscription mixing', new CreateSubscriptionSessionParams(productReference: 'pro', price: 9.99), ['productUuid']);
	}

	#[Test]
	public function ids(): void
	{
		foreach ([self::UUID, '019ECA82-5680-7B00-8000-0000000000B1', 'pay@' . self::UUID, 'sub@' . self::UUID, 'payg@' . self::UUID,
			'sub-hist@' . self::UUID, 'refund@' . self::UUID, 'transfer@' . self::UUID] as $id) {
			$this->assertTrue(Validator::isTxId($id), $id);
		}
		foreach (['', 'pay@', 'pay@x', 'evt@' . self::UUID, '@' . self::UUID, 'PAY@' . self::UUID, self::UUID . '0', '42'] as $id) {
			$this->assertFalse(Validator::isTxId($id), $id);
		}
		$this->check('txUuid required', new InitiateRefundParams(txUuid: ''), ['txUuid']);
		$this->check('txUuid bad', new InitiateRefundParams(txUuid: 'order-1'), ['txUuid']);
		$this->check('subscriptionUuid filter', new FailureListParams(subscriptionUuid: 'sub@nope'), ['subscriptionUuid']);

		Validator::pathUuid('uuid', self::UUID);
		Validator::pathUuid('uuid', '00000000-0000-0000-0000-000000000000'); // the API answers 404
		$this->assertSame(['uuid'], $this->failingFields(static fn () => Validator::pathUuid('uuid', 'pay@' . self::UUID)));
		$this->assertSame(['uuid'], $this->failingFields(static fn () => Validator::pathTxId('uuid', 'x')));
		Validator::pathRequired('reference', 'a/b');
		$this->assertSame(['reference'], $this->failingFields(static fn () => Validator::pathRequired('reference', ' ')));
		$this->assertSame(['onBehalfOf'], $this->failingFields(static fn () => Transport::checkOnBehalfOf('00000000-0000-0000-0000-000000000000')));
	}

	#[Test]
	public function filters(): void
	{
		$this->check('exclusive', new PaymentListParams(includeMembers: true, userUuid: self::UUID), ['userUuid']);
		$this->check('user only', new PaymentListParams(userUuid: self::UUID));
		$this->check('bad uuids', new SubscriptionListParams(customerUuid: 'x', productUuid: 'y', userUuid: 'z'), ['customerUuid', 'productUuid', 'userUuid']);
		$this->check('status', new SubscriptionListParams(status: 'hibernating'), ['status']);
		$this->check('status ok', new SubscriptionListParams(status: SubscriptionStatus::CANCELLED));
		$this->check('source', new CombinedPaymentListParams(source: 'subscription_history'), ['source']);
		$this->check('kind/category', new FailureListParams(kind: 'refund', category: 'boom'), ['kind', 'category']);
		$this->check('invitation status', new InvitationListParams(status: 'open'), ['status']);
		$this->check('event type', new EventListParams(type: 'payment.done'), ['type']);
		$this->check('event type ok', new EventListParams(type: EventType::WEBHOOK_TEST));
		$this->check('refund exclusive', new RefundListParams(includeMembers: true, userUuid: self::UUID), ['userUuid']);
		$this->check('refund includeMembers false + user', new RefundListParams(includeMembers: false, userUuid: self::UUID));
		$this->check('supported currencies', new SupportedCurrenciesParams(userUuid: 'x'), ['userUuid']);
	}

	#[Test]
	public function dates(): void
	{
		foreach ([['2026-06-01', '2026-06-01'], ['2024-02-29', '2026-12-31']] as [$from, $to]) {
			$v = new Validator();
			$v->dateRange('from', $from, 'to', $to);
			$v->throwIfAny();
		}
		foreach ([['2026-06-02', '2026-06-01'], ['2026-13-01', '2026-12-01'], ['2025-02-29', '2025-03-01'], ['26-01-01', '2026-01-01'], ['2026-1-1', '2026-01-02']] as [$from, $to]) {
			$v = new Validator();
			$v->dateRange('from', $from, 'to', $to);
			$this->assertNotSame([], $this->failingFields(static fn () => $v->throwIfAny()), "$from..$to");
		}
	}

	#[Test]
	public function endpoint_events(): void
	{
		$twenty = array_fill(0, 20, EventType::PAYMENT_COMPLETED);
		$this->check('20 events', new CreateWebhookEndpointParams(url: 'https://x.io', events: $twenty));
		$this->check('21 events', new CreateWebhookEndpointParams(url: 'https://x.io', events: [...$twenty, EventType::REFUND_DENIED]), ['events']);
		$this->check('webhook.test', new CreateWebhookEndpointParams(url: 'https://x.io', events: [EventType::REFUND_DENIED, EventType::WEBHOOK_TEST]), ['events[1]']);
		$this->check('empty entry', new CreateWebhookEndpointParams(url: 'https://x.io', events: ['']), ['events[0]']);
		$this->check('update webhook.test', new UpdateWebhookEndpointParams(events: [EventType::WEBHOOK_TEST]), ['events[0]']);
		$this->check('unknown type passes (the API judges)', new CreateWebhookEndpointParams(url: 'https://x.io', events: ['future.event']));
		$this->check('payloadVersion v1', new UpdateWebhookEndpointParams(payloadVersion: WebhookPayloadVersion::V1), ['payloadVersion']);
		$this->check('payloadVersion v2', new UpdateWebhookEndpointParams(payloadVersion: WebhookPayloadVersion::V2));
	}

	#[Test]
	public function header_values(): void
	{
		foreach (['a', 'order-1042', '~!@#$%^&*()_+{}|:<>?', str_repeat('k', 255)] as $k) {
			$this->assertTrue(Validator::isIdempotencyKey($k), $k);
		}
		foreach (['', 'a b', "tab\t", 'é', "del\x7f", str_repeat('k', 256)] as $k) {
			$this->assertFalse(Validator::isIdempotencyKey($k), $k);
		}
		foreach (['a', 'sdk-probe.123:abc', 'A_b-C.d:E', str_repeat('r', 128)] as $id) {
			$this->assertTrue(Validator::isRequestId($id), $id);
		}
		foreach (['', 'a b', 'a/b', 'a@b', str_repeat('r', 129)] as $id) {
			$this->assertFalse(Validator::isRequestId($id), $id);
		}
	}

	#[Test]
	public function update_semantics(): void
	{
		$cases = [
			[new UpdateCustomerParams(), '{}'],
			[new UpdateCustomerParams(phoneNumber: '', address: ''), '{"phoneNumber":"","address":""}'],
			[new UpdateCustomerParams(name: 'Ada', phoneNumber: '+33 6 12 34 56 78'), '{"name":"Ada","phoneNumber":"+33 6 12 34 56 78"}'],
			[new UpdateCustomerParams(name: '', email: ''), '{}'],
			[new UpdateProductParams(), '{}'],
			[new UpdateProductParams(description: ''), '{"description":""}'],
			[new UpdateProductParams(price: 9.99, isActive: false, removeSubscription: true), '{"price":9.99,"isActive":false,"removeSubscription":true}'],
			[new UpdateProductParams(subscription: new SubscriptionTermsParams()), '{"subscription":{}}'],
			[new UpdateWebhookEndpointParams(), '{}'],
			[new UpdateWebhookEndpointParams(events: [], description: ''), '{"events":[],"description":""}'],
			[new UpdateWebhookEndpointParams(payloadVersion: WebhookPayloadVersion::V2, enabled: false), '{"payloadVersion":"v2","enabled":false}'],
			[new UpdateMemberParams(0), '{"organizationFeePercent":0}'],
			[new CreateInvitationParams(email: 'a@b.co'), '{"email":"a@b.co","role":"user","trustLayer":false}'],
			[new CreateInvitationParams(email: 'a@b.co', trustLayer: true, organizationFeePercent: 2.5, redirectUrl: 'https://x.io'),
				'{"email":"a@b.co","role":"user","trustLayer":true,"organizationFeePercent":2.5,"redirectUrl":"https://x.io"}'],
			[new CreateProductParams(name: 'Pro', price: 10), '{"name":"Pro","price":10}'],
			[new CreateProductParams(name: 'Pro', price: 10, subscription: new SubscriptionTermsParams(Duration::months(1), new Duration(), 0)),
				'{"name":"Pro","price":10,"subscription":{"frequency":{"value":1,"unit":"months"},"trialPeriod":{"value":0},"minPeriods":0}}'],
			[new CreatePaymentSessionParams(productUuid: self::UUID), '{"productUuid":"' . self::UUID . '"}'],
			[new CreateSubscriptionSessionParams(productName: 'Pro', price: 9.99, expiresInMinutes: 30, frequency: Duration::weeks(1)),
				'{"productName":"Pro","price":9.99,"expiresInMinutes":30,"frequency":{"value":1,"unit":"weeks"}}'],
			[new InitiateRefundParams(txUuid: 'pay@x'), '{"txUuid":"pay@x"}'],
			[new CreateWebhookEndpointParams(url: 'https://x.io', includeMembers: false), '{"url":"https://x.io","includeMembers":false}'],
			[new CreateWebhookEndpointParams(url: 'https://x.io', events: []), '{"url":"https://x.io"}'],
		];
		foreach ($cases as [$params, $want]) {
			$this->assertSame($want, Transport::encodeBody($params->toArray()), $params::class);
		}

		$this->check('clear phone/address', new UpdateCustomerParams(phoneNumber: '', address: ''));
		$this->check('bad phone', new UpdateCustomerParams(phoneNumber: 'abc'), ['phoneNumber']);
		$this->check('blank address', new UpdateCustomerParams(address: '   '), ['address']);
		$this->check('clear description', new UpdateProductParams(description: ''));
		$this->check('short description', new UpdateProductParams(description: 'x'), ['description']);
		$this->check('remove + set', new UpdateProductParams(subscription: new SubscriptionTermsParams(), removeSubscription: true), ['removeSubscription']);
		$this->check('clear endpoint description', new UpdateWebhookEndpointParams(description: ''));
	}

	#[Test]
	public function every_failing_field_is_collected(): void
	{
		$params = new CreateCustomerParams(name: 'x', email: 'nope', lastName: '<', phoneNumber: '1', address: ' ', reference: 'a b');
		$this->check('all', $params, ['name', 'lastName', 'email', 'phoneNumber', 'address', 'reference']);
		try {
			$params->validate();
		} catch (\QBitFlow\Exceptions\ValidationException $e) {
			$this->assertStringStartsWith('validation failed; name: name must be 2 to 100 characters', $e->getMessage());
			$this->assertSame('validation failed', $e->errorMessage);
		}
	}
}
