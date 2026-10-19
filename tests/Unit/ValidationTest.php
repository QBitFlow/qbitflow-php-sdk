<?php

declare(strict_types=1);

namespace QBitFlow\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use QBitFlow\Enums\EventType;
use QBitFlow\Enums\RefundStatus;
use QBitFlow\Enums\SubscriptionStatus;
use QBitFlow\Enums\TransactionKind;
use QBitFlow\Enums\WebhookPayloadVersion;
use QBitFlow\Http\Transport;
use QBitFlow\Models\Duration;
use QBitFlow\Params\AcceptedCurrenciesParams;
use QBitFlow\Params\CheckoutFees;
use QBitFlow\Params\CreateCustomerParams;
use QBitFlow\Params\CreateInvitationParams;
use QBitFlow\Params\CreatePaymentSessionParams;
use QBitFlow\Params\CreateProductParams;
use QBitFlow\Params\CreateSubscriptionSessionParams;
use QBitFlow\Params\CreateWebhookEndpointParams;
use QBitFlow\Params\CustomerListParams;
use QBitFlow\Params\EventListParams;
use QBitFlow\Params\FailureListParams;
use QBitFlow\Params\FeeItem;
use QBitFlow\Params\CreateRefundParams;
use QBitFlow\Params\InvitationListParams;
use QBitFlow\Params\PaymentListParams;
use QBitFlow\Params\ProductListParams;
use QBitFlow\Params\RefundListParams;
use QBitFlow\Params\SubscriptionListParams;
use QBitFlow\Params\SubscriptionTermsParams;
use QBitFlow\Params\TransactionListParams;
use QBitFlow\Params\UpdateCustomerParams;
use QBitFlow\Params\UpdateMemberParams;
use QBitFlow\Params\UpdateProductParams;
use QBitFlow\Params\UpdateSubscriptionParams;
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
			$this->check('name ' . $s, new CreateProductParams(name: $s, priceUsd: '1'));
		}
		foreach (['a', '  ', " \t ", "line\nbreak", "tab\there", '<b>', 'x{y}', 'a[1]', 'back`tick', 'back\\slash', 'pi|pe', 'semi;colon',
			'quo"te', 'til~de', 'car^et', "bidi\u{202E}name", "bidi\u{2066}x", "nul\x00x", str_repeat('é', 101), "\u{A0}\u{A0}\u{A0}"] as $s) {
			$this->check('name ' . $s, new CreateProductParams(name: $s, priceUsd: '1'), ['name']);
		}
		$this->check('lastName 1 char', new CreateCustomerParams(firstName: 'Ada', email: 'a@b.co', lastName: 'L'));
		$this->check('lastName newline', new CreateCustomerParams(firstName: 'Ada', email: 'a@b.co', lastName: "L\n"), ['lastName']);
	}

	#[Test]
	public function the_text_rule(): void
	{
		foreach (["Two\nlines\r\nand\ttab", 'Lifetime access (Pro) & more: 100%', 'ok'] as $s) {
			$this->check('description', new CreateProductParams(name: 'Pro', priceUsd: '1', description: $s));
		}
		foreach (['   ', "\n\n", '<script>', "x\x07bell", str_repeat('x', 501), 'a'] as $s) {
			$this->check('description', new CreateProductParams(name: 'Pro', priceUsd: '1', description: $s), ['description']);
		}
		$this->check('address 1 char', new CreateCustomerParams(firstName: 'Ada', email: 'a@b.co', address: 'x'));
		$this->check('address 501', new CreateCustomerParams(firstName: 'Ada', email: 'a@b.co', address: str_repeat('x', 501)), ['address']);
		$this->check('endpoint description 201', new CreateWebhookEndpointParams(url: 'https://x.io', description: str_repeat('x', 201)), ['description']);
		$this->check('refund reason', new CreateRefundParams(txUuid: 'pay@' . self::UUID, reason: 'Broken, can you refund?', merchantMessage: "Sorry\nTeam"));
		$this->check('refund reason bad', new CreateRefundParams(txUuid: 'pay@' . self::UUID, reason: '{x}', merchantMessage: str_repeat('m', 501)), ['reason', 'merchantMessage']);
	}

	#[Test]
	public function the_reference_rule(): void
	{
		foreach (['order-1042', 'INV_2026.10:01@shop', 'a', str_repeat('r', 100)] as $s) {
			$this->check('reference ' . $s, new CreateCustomerParams(firstName: 'Ada', email: 'a@b.co', reference: $s));
		}
		foreach (['has space', 'slash/x', 'é', '#1', str_repeat('r', 101)] as $s) {
			$this->check('reference ' . $s, new CreateCustomerParams(firstName: 'Ada', email: 'a@b.co', reference: $s), ['reference']);
		}
		$this->check('session refs', new CreatePaymentSessionParams(productReference: 'bad ref', reference: 'o/1', customerReference: 'c 1'),
			['reference', 'productReference', 'customerReference']);
		$this->check('subscription filter', new SubscriptionListParams(reference: 'bad ref'), ['reference']);
	}

	#[Test]
	public function the_phone_rule(): void
	{
		foreach (['+33 6 12 34 56 78', '555 (123) 4567', '06.12.34.56.78', '123456'] as $s) {
			$this->check('phone ' . $s, new CreateCustomerParams(firstName: 'Ada', email: 'a@b.co', phoneNumber: $s));
		}
		foreach (['12345', '+', 'phone', '+33 6 12 34 56 7x', '-123456', '123456-', '+' . str_repeat('1', 32)] as $s) {
			$this->check('phone ' . $s, new CreateCustomerParams(firstName: 'Ada', email: 'a@b.co', phoneNumber: $s), ['phoneNumber']);
		}
	}

	#[Test]
	public function the_email_rule(): void
	{
		foreach (['a@b.co', 'First.Last+tag@Example.COM', 'x@sub.domain.io'] as $s) {
			$this->check('email ' . $s, new CreateCustomerParams(firstName: 'Ada', email: $s));
		}
		foreach (['no-at', 'a@b', '@b.co', 'a@.b.co', 'a@b.co.', 'a b@c.io', 'a@@b.co', 'a@b@c.io', str_repeat('a', 250) . '@b.co'] as $s) {
			$this->check('email ' . $s, new CreateCustomerParams(firstName: 'Ada', email: $s), ['email']);
		}
		$this->check('email required', new CreateCustomerParams(firstName: 'Ada', email: ''), ['email']);
		$this->check('invitation email', new CreateInvitationParams(email: 'nope', holdFunds: false), ['email']);
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
		$this->check('redirectUrl', new CreateInvitationParams(email: 'a@b.co', holdFunds: false, redirectUrl: 'nope'), ['redirectUrl']);
		$this->check('endpoint url required', new CreateWebhookEndpointParams(url: ''), ['url']);
		$this->check('endpoint url update', new UpdateWebhookEndpointParams(url: 'x'), ['url']);
	}

	#[Test]
	public function the_usd_rule(): void
	{
		foreach (['0.01', '5', '1000000', '1000000.00', '9.99', '12.50', '007.50', '0.1'] as $p) {
			$this->check('price ' . $p, new CreateProductParams(name: 'Pro', priceUsd: $p));
		}
		foreach (['0', '0.00', '-1', '+1', '1e2', '1E2', ' 4.99', '4.99 ', '1.999', '.5', '5.', '1,5', '1000000.01', '1000001', 'NaN', 'Infinity', 'abc', '0x10'] as $p) {
			$this->check('price ' . var_export($p, true), new CreateProductParams(name: 'Pro', priceUsd: $p), ['priceUsd']);
		}
		$this->check('price required', new CreateProductParams(name: 'Pro', priceUsd: ''), ['priceUsd']);
		$this->check('update price', new UpdateProductParams(priceUsd: '0'), ['priceUsd']);
		$this->check('update price unset', new UpdateProductParams(priceUsd: ''));
		$this->check('update no price', new UpdateProductParams());

		try {
			(new CreateProductParams(name: 'Pro', priceUsd: '1e2'))->validate();
			$this->fail('want a ValidationException');
		} catch (\QBitFlow\Exceptions\ValidationException $e) {
			$this->assertSame('priceUsd must be an amount in USD as a decimal string (e.g. "12.50"): above 0, at most 1000000, with at most 2 decimals', $e->fieldErrors[0]->message);
		}
	}

	#[Test]
	public function the_percent_rule(): void
	{
		foreach (['0.01', '1.5', '33.33', '100', '100.00', '050'] as $p) {
			$this->check('refund ' . $p, new CreateRefundParams(txUuid: self::UUID, refundPercent: $p));
		}
		$this->check('refund unset', new CreateRefundParams(txUuid: self::UUID, refundPercent: ''));
		foreach (['0', '0.00', '-1', '100.01', '1.155', '101', '1e1', '.5', ' 5', '00000000000000050'] as $p) {
			$this->check('refund ' . var_export($p, true), new CreateRefundParams(txUuid: self::UUID, refundPercent: $p), ['refundPercent']);
		}
		foreach (['0', '0.05', '1.5', '50', '50.00'] as $p) {
			$this->check('member fee ' . $p, new UpdateMemberParams($p));
			$this->check('invitation fee ' . $p, new CreateInvitationParams(email: 'a@b.co', holdFunds: true, organizationFeePercent: $p));
		}
		foreach (['-0.01', '50.01', '1.234', '51', 'ten'] as $p) {
			$this->check('member fee ' . $p, new UpdateMemberParams($p), ['organizationFeePercent']);
		}
		$this->check('member fee required', new UpdateMemberParams(''), ['organizationFeePercent']);
	}

	#[Test]
	public function the_metadata_rule(): void
	{
		$ok = ['orderId' => 'order-1042', 'crm:account' => 'A-1', 'a.b_c-d' => str_repeat('é', 500), 'empty' => '', str_repeat('k', 40) => "accent \u{00E9}"];
		$this->check('fine', new CreateProductParams(name: 'Pro', priceUsd: '1', metadata: $ok));
		$keys = static fn (int $n): array => array_fill_keys(array_map(static fn (int $i): string => 'k' . $i, range(1, $n)), 'v');
		$this->check('50 keys', new CreateCustomerParams(firstName: 'Ada', email: 'a@b.co', metadata: $keys(50)));
		$this->check('51 keys', new CreateCustomerParams(firstName: 'Ada', email: 'a@b.co', metadata: $keys(51)), ['metadata']);
		foreach (['has space', 'slash/x', 'é', '', str_repeat('k', 41)] as $key) {
			$this->check('key ' . var_export($key, true), new UpdateSubscriptionParams(metadata: [$key => 'v']), ['metadata']);
		}
		foreach (["tab\there", "new\nline", "nul\x00", "bidi\u{202E}x", "c1\u{0085}", str_repeat('v', 501)] as $value) {
			$this->check('value ' . var_export($value, true), new UpdateCustomerParams(metadata: ['note' => $value]), ['metadata.note']);
		}
		$this->check('a bad key is not echoed', new UpdateProductParams(metadata: ['bad key' => "tab\t", 'ok' => "tab\t"]), ['metadata.ok', 'metadata']);
		$this->check('a value that is not a string', new CreatePaymentSessionParams(productUuid: self::UUID, metadata: ['n' => 1]), ['metadata.n']);
		$this->check('session metadata', new CreateSubscriptionSessionParams(productUuid: self::UUID, metadata: ['x y' => 'z']), ['metadata']);
		$this->check('update unset', new UpdateSubscriptionParams());

		// A metadata map is always sent as a JSON object, even with integer-like keys.
		$this->assertSame('{"metadata":{"0":"a","1":"b"}}', Transport::encodeBody((new UpdateSubscriptionParams(['a', 'b']))->toArray()));
		$this->assertSame('{}', Transport::encodeBody((new UpdateSubscriptionParams([]))->toArray()), 'an empty map is not sent');
	}

	#[Test]
	public function the_cursor_rule(): void
	{
		$this->check('200', new CustomerListParams(cursor: str_repeat('c', 200)));
		$this->check('201', new CustomerListParams(cursor: str_repeat('c', 201)), ['cursor']);
		$this->check('opaque', new PaymentListParams(cursor: 'djF8MDE5Y2FkZmQ=/+'));
		$this->check('refunds', new RefundListParams(cursor: str_repeat('c', 201)), ['cursor']);
		$this->check('transactions', new TransactionListParams(cursor: str_repeat('c', 201)), ['cursor']);
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
			$this->check($name, new CreateProductParams(name: 'Pro', priceUsd: '1', subscription: $terms), $want);
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
			'inline' => [new CreatePaymentSessionParams(productName: 'Pro', priceUsd: '10'), []],
			'inline + description' => [new CreatePaymentSessionParams(productName: 'Pro', description: 'Lifetime', priceUsd: '10'), []],
			'nothing' => [new CreatePaymentSessionParams(), ['productUuid']],
			'uuid + reference' => [new CreatePaymentSessionParams(productUuid: self::UUID, productReference: 'x'), ['productUuid']],
			'uuid + inline' => [new CreatePaymentSessionParams(productUuid: self::UUID, productName: 'Pro', priceUsd: '1'), ['productUuid']],
			'uuid + description' => [new CreatePaymentSessionParams(productUuid: self::UUID, description: 'x y'), ['productUuid']],
			'inline without price' => [new CreatePaymentSessionParams(productName: 'Pro'), ['priceUsd']],
			'inline without name' => [new CreatePaymentSessionParams(priceUsd: '3'), ['productName']],
			'inline negative price' => [new CreatePaymentSessionParams(productName: 'Pro', priceUsd: '-3'), ['priceUsd']],
			'inline price with 3 decimals' => [new CreatePaymentSessionParams(productName: 'Pro', priceUsd: '3.999'), ['priceUsd']],
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
		$this->check('subscription inline', new CreateSubscriptionSessionParams(productName: 'Pro', priceUsd: '9.99', frequency: Duration::months(1)));
		$this->check('subscription mixing', new CreateSubscriptionSessionParams(productReference: 'pro', priceUsd: '9.99'), ['productUuid']);
	}

	/** A payment checkout for the product with these fees. */
	private static function withFees(?bool $processingFee = null, FeeItem ...$items): CreatePaymentSessionParams
	{
		return new CreatePaymentSessionParams(productUuid: self::UUID, fees: new CheckoutFees($processingFee, $items));
	}

	#[Test]
	public function the_fees_rule(): void
	{
		$line = static fn (string $amount, string $label = 'Shipping', ?string $description = null): FeeItem => new FeeItem($label, $amount, $description);

		$this->check('no fees', new CreatePaymentSessionParams(productUuid: self::UUID));
		$this->check('empty fees', self::withFees());
		$this->check('processing fee only', self::withFees(true));
		$this->check('10 lines', self::withFees(null, ...array_fill(0, 10, $line('1'))));
		$this->check('11 lines', self::withFees(null, ...array_fill(0, 11, $line('1'))), ['fees.items']);

		foreach (['4.99', '0.01', '19.9', '0.29', '1', '1000000', '1000000.00', '007.50'] as $amount) {
			$this->check('amountUsd ' . var_export($amount, true), self::withFees(null, $line($amount)));
		}
		foreach (['0', '0.00', '-1', '-0.01', '0.001', '+1', '1e2', '1E2', 'abc', '', ' 4.99', '4.99 ', '1.999', '.5', '5.', '1,5', '1000000.01',
			'1000001', '0x10', 'NaN', 'Infinity', '0.30000000000000004'] as $amount) {
			$this->check('amountUsd ' . var_export($amount, true), self::withFees(null, $line($amount)), ['fees.items[0].amountUsd']);
		}

		foreach (['X', str_repeat('é', 40), 'VAT (20%)', 'Service fee & handling'] as $label) {
			$this->check('label ' . $label, self::withFees(null, $line('1', $label)));
		}
		foreach (['', '   ', str_repeat('é', 41), "two\nlines", "tab\there", '<b>', 'semi;colon', "bidi\u{202E}x"] as $label) {
			$this->check('label ' . var_export($label, true), self::withFees(null, $line('1', $label)), ['fees.items[0].label']);
		}

		foreach (['x', str_repeat('d', 200), "Standard,\n3 to 5 days", ''] as $description) {
			$this->check('description ' . strlen($description), self::withFees(null, $line('1', 'Shipping', $description)));
		}
		foreach ([str_repeat('d', 201), '   ', '<script>', "bell\x07"] as $description) {
			$this->check('description ' . var_export($description, true), self::withFees(null, $line('1', 'Shipping', $description)), ['fees.items[0].description']);
		}

		$this->check('every line is checked, by its index', self::withFees(false, $line('1'), $line('0', '<b>', ' '), $line('1e2')),
			['fees.items[1].label', 'fees.items[1].description', 'fees.items[1].amountUsd', 'fees.items[2].amountUsd']);
		$this->check('with the session checks', new CreatePaymentSessionParams(productReference: 'bad ref', fees: new CheckoutFees(items: [$line('-1')])),
			['productReference', 'fees.items[0].amountUsd']);
		$this->check('a line that is not a FeeItem', new CreatePaymentSessionParams(productUuid: self::UUID, fees: new CheckoutFees(items: [['label' => 'x', 'amountUsd' => '1']])),
			['fees.items[0]']);

		try {
			self::withFees(null, $line('1e2'))->validate();
			$this->fail('want a ValidationException');
		} catch (\QBitFlow\Exceptions\ValidationException $e) {
			$this->assertSame('fees.items[0].amountUsd must be an amount in USD as a decimal string (e.g. "12.50"): above 0, at most 1000000, with at most 2 decimals', $e->fieldErrors[0]->message);
		}

		// A subscription checkout's fees follow the same rules, reported with (after) the terms.
		$sub = static fn (?CheckoutFees $fees): CreateSubscriptionSessionParams => new CreateSubscriptionSessionParams(productUuid: self::UUID, fees: $fees);
		$this->check('subscription no fees', $sub(null));
		$this->check('subscription processing fee only', $sub(new CheckoutFees(true)));
		$this->check('subscription 10 lines', $sub(new CheckoutFees(false, array_fill(0, 10, $line('1')))));
		$this->check('subscription 11 lines', $sub(new CheckoutFees(items: array_fill(0, 11, $line('1')))), ['fees.items']);
		$this->check('subscription bad line', $sub(new CheckoutFees(items: [$line('0.001', 'a|b', ' ')])),
			['fees.items[0].label', 'fees.items[0].description', 'fees.items[0].amountUsd']);
		$this->check('subscription fees and terms', new CreateSubscriptionSessionParams(productUuid: self::UUID, frequency: new Duration(1),
			fees: new CheckoutFees(items: [$line('-1', 'Tax')])), ['frequency.unit', 'fees.items[0].amountUsd']);
	}

	#[Test]
	public function the_fees_body(): void
	{
		$base = '{"productUuid":"' . self::UUID . '"';
		$cases = [
			'no fees: omitted' => [new CreatePaymentSessionParams(productUuid: self::UUID), $base . '}'],
			'empty fees: an empty object' => [self::withFees(), $base . ',"fees":{}}'],
			'processing fee true' => [self::withFees(true), $base . ',"fees":{"processingFee":true}}'],
			'processing fee false' => [self::withFees(false), $base . ',"fees":{"processingFee":false}}'],
			'a string stays a string, as typed' => [self::withFees(null, new FeeItem('VAT', '4.90')), $base . ',"fees":{"items":[{"label":"VAT","amountUsd":"4.90"}]}}'],
			'trailing zeros kept' => [self::withFees(null, new FeeItem('Shipping', '0.50')), $base . ',"fees":{"items":[{"label":"Shipping","amountUsd":"0.50"}]}}'],
			'description sent when given' => [self::withFees(true, new FeeItem('Shipping', '0.75', 'Standard, 3 to 5 days'), new FeeItem('Gift wrap', '2')),
				$base . ',"fees":{"processingFee":true,"items":[{"label":"Shipping","description":"Standard, 3 to 5 days","amountUsd":"0.75"},{"label":"Gift wrap","amountUsd":"2"}]}}'],
			'empty description omitted' => [self::withFees(null, new FeeItem('Shipping', '1', '')), $base . ',"fees":{"items":[{"label":"Shipping","amountUsd":"1"}]}}'],
		];
		foreach ($cases as $name => [$params, $want]) {
			$this->assertSame($want, Transport::encodeBody($params->toArray()), $name);
		}

		// A subscription checkout sends the same fees object, after the terms.
		$this->assertSame('{"productName":"Pro","priceUsd":"9.99","frequency":{"value":1,"unit":"months"},'
			. '"fees":{"processingFee":true,"items":[{"label":"VAT (20%)","description":"French VAT","amountUsd":"2"}]}}',
			Transport::encodeBody((new CreateSubscriptionSessionParams(productName: 'Pro', priceUsd: '9.99', frequency: Duration::months(1),
				fees: new CheckoutFees(true, [new FeeItem('VAT (20%)', '2', 'French VAT')])))->toArray()), 'subscription with fees');
		$this->assertSame($base . ',"fees":{"processingFee":false}}',
			Transport::encodeBody((new CreateSubscriptionSessionParams(productUuid: self::UUID, fees: new CheckoutFees(false)))->toArray()), 'subscription processing fee off');
		$this->assertSame($base . '}', Transport::encodeBody((new CreateSubscriptionSessionParams(productUuid: self::UUID))->toArray()), 'subscription without fees: omitted');
	}

	#[Test]
	public function ids(): void
	{
		foreach ([self::UUID, '019ECA82-5680-7B00-8000-0000000000B1', 'pay@' . self::UUID, 'sub@' . self::UUID, 'payg@' . self::UUID,
			'sub-hist@' . self::UUID, 'refund@' . self::UUID, 'transfer@' . self::UUID, 'release@' . self::UUID] as $id) {
			$this->assertTrue(Validator::isTxId($id), $id);
		}
		foreach (['', 'pay@', 'pay@x', 'evt@' . self::UUID, '@' . self::UUID, 'PAY@' . self::UUID, self::UUID . '0', '42'] as $id) {
			$this->assertFalse(Validator::isTxId($id), $id);
		}
		$this->check('txUuid required', new CreateRefundParams(txUuid: ''), ['txUuid']);
		$this->check('txUuid bad', new CreateRefundParams(txUuid: 'order-1'), ['txUuid']);
		$this->check('subscriptionUuid filter', new FailureListParams(subscriptionUuid: 'sub@nope'), ['subscriptionUuid']);

		Validator::pathUuid('uuid', self::UUID);
		Validator::pathUuid('uuid', '00000000-0000-0000-0000-000000000000'); // the API answers 404
		$this->assertSame(['uuid'], $this->failingFields(static fn () => Validator::pathUuid('uuid', 'pay@' . self::UUID)));
		$this->assertSame(['uuid'], $this->failingFields(static fn () => Validator::pathTxId('uuid', 'x')));
		Validator::pathRequired('id', 'evt_a/b');
		$this->assertSame(['id'], $this->failingFields(static fn () => Validator::pathRequired('id', ' ')));
		$this->assertSame(['id'], $this->failingFields(static fn () => Validator::pathRequired('id', '..')));
		$this->assertSame(['onBehalfOf'], $this->failingFields(static fn () => Transport::checkOnBehalfOf('00000000-0000-0000-0000-000000000000')));
	}

	#[Test]
	public function filters(): void
	{
		$this->check('exclusive', new PaymentListParams(includeMembers: true, userUuid: self::UUID), ['userUuid']);
		$this->check('user only', new PaymentListParams(userUuid: self::UUID));
		$this->check('bad uuids', new SubscriptionListParams(customerUuid: 'x', productUuid: 'y', userUuid: 'z'), ['customerUuid', 'productUuid', 'userUuid']);
		$this->check('a new subscription status is sent as is', new SubscriptionListParams(status: 'hibernating'));
		$this->check('status ok', new SubscriptionListParams(status: SubscriptionStatus::CANCELLED));
		$this->check('transactions type', new TransactionListParams(type: 'subscriptionHistory'), ['type']);
		$this->check('transactions type ok', new TransactionListParams(type: TransactionKind::BILL));
		$this->check('transactions subscriptionUuid', new TransactionListParams(subscriptionUuid: 'sub@x'), ['subscriptionUuid']);
		$this->check('kind/category', new FailureListParams(kind: 'refund', category: 'boom'), ['kind', 'category']);
		$this->check('invitation status', new InvitationListParams(status: 'open'), ['status']);
		$this->check('a new event type is sent as is (the API judges)', new EventListParams(type: 'payment.done'));
		$this->check('event type ok', new EventListParams(type: EventType::WEBHOOK_TEST));
		$this->check('refund exclusive', new RefundListParams(includeMembers: true, userUuid: self::UUID), ['userUuid']);
		$this->check('refund includeMembers false + user', new RefundListParams(includeMembers: false, userUuid: self::UUID));
		$this->check('accepted currencies', new AcceptedCurrenciesParams(userUuid: 'x'), ['userUuid']);
		$this->check('refund statuses', new RefundListParams(status: [RefundStatus::PENDING, RefundStatus::CANCELLED, RefundStatus::FAILED]));
		$this->check('refund status unknown', new RefundListParams(status: ['approved', 'denied', '']), ['status[1]', 'status[2]']);
		$this->check('refund 6 statuses', new RefundListParams(status: [...RefundStatus::values(), 'pending']), ['status']);
		$this->check('refund txUuid', new RefundListParams(txUuid: 'order-1'), ['txUuid']);
		$this->check('product reference filter', new ProductListParams(reference: 'a b'), ['reference']);
		$this->check('customer reference filter', new CustomerListParams(reference: 'a/b'), ['reference']);
		$this->check('payment reference filter', new PaymentListParams(reference: str_repeat('r', 101)), ['reference']);
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
		$this->check('21 events', new CreateWebhookEndpointParams(url: 'https://x.io', events: [...$twenty, EventType::REFUND_REJECTED]), ['events']);
		$this->check('webhook.test', new CreateWebhookEndpointParams(url: 'https://x.io', events: [EventType::REFUND_REJECTED, EventType::WEBHOOK_TEST]), ['events[1]']);
		$this->check('empty entry', new CreateWebhookEndpointParams(url: 'https://x.io', events: ['']), ['events[0]']);
		$this->check('update webhook.test', new UpdateWebhookEndpointParams(events: [EventType::WEBHOOK_TEST]), ['events[0]']);
		$this->check('unknown type passes (the API judges)', new CreateWebhookEndpointParams(url: 'https://x.io', events: ['future.event']));
		$this->check('payloadVersion v1', new UpdateWebhookEndpointParams(payloadVersion: 'v1'), ['payloadVersion']);
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
			[new UpdateCustomerParams(lastName: '', phoneNumber: '', address: ''), '{"lastName":"","phoneNumber":"","address":""}'],
			[new UpdateCustomerParams(firstName: 'Ada', phoneNumber: '+33 6 12 34 56 78'), '{"firstName":"Ada","phoneNumber":"+33 6 12 34 56 78"}'],
			[new UpdateCustomerParams(firstName: '', email: ''), '{}'],
			[new UpdateCustomerParams(metadata: ['oldKey' => '', 'tier' => 'gold']), '{"metadata":{"oldKey":"","tier":"gold"}}'],
			[new UpdateProductParams(), '{}'],
			[new UpdateProductParams(description: ''), '{"description":""}'],
			[new UpdateProductParams(priceUsd: '9.90', isActive: false, removeSubscription: true), '{"priceUsd":"9.90","isActive":false,"removeSubscription":true}'],
			[new UpdateProductParams(priceUsd: ''), '{}'],
			[new UpdateProductParams(subscription: new SubscriptionTermsParams()), '{"subscription":{}}'],
			[new UpdateWebhookEndpointParams(), '{}'],
			[new UpdateWebhookEndpointParams(events: [], description: ''), '{"events":[],"description":""}'],
			[new UpdateWebhookEndpointParams(payloadVersion: WebhookPayloadVersion::V2, enabled: false), '{"payloadVersion":"v2","enabled":false}'],
			[new UpdateSubscriptionParams(), '{}'],
			[new UpdateSubscriptionParams(['coupon' => '', 'plan' => 'pro-annual']), '{"metadata":{"coupon":"","plan":"pro-annual"}}'],
			[new UpdateMemberParams('0'), '{"organizationFeePercent":"0"}'],
			[new CreateInvitationParams(email: 'a@b.co', holdFunds: false), '{"email":"a@b.co","role":"user","holdFunds":false}'],
			[new CreateInvitationParams(email: 'a@b.co', holdFunds: true, organizationFeePercent: '2.50', redirectUrl: 'https://x.io'),
				'{"email":"a@b.co","role":"user","holdFunds":true,"organizationFeePercent":"2.50","redirectUrl":"https://x.io"}'],
			[new CreateProductParams(name: 'Pro', priceUsd: '10'), '{"name":"Pro","priceUsd":"10"}'],
			[new CreateProductParams(name: 'Pro', priceUsd: '10', subscription: new SubscriptionTermsParams(Duration::months(1), new Duration(), 0)),
				'{"name":"Pro","priceUsd":"10","subscription":{"frequency":{"value":1,"unit":"months"},"trialPeriod":{"value":0},"minPeriods":0}}'],
			[new CreatePaymentSessionParams(productUuid: self::UUID), '{"productUuid":"' . self::UUID . '"}'],
			[new CreateSubscriptionSessionParams(productName: 'Pro', priceUsd: '9.99', expiresInMinutes: 30, frequency: Duration::weeks(1)),
				'{"productName":"Pro","priceUsd":"9.99","expiresInMinutes":30,"frequency":{"value":1,"unit":"weeks"}}'],
			[new CreateRefundParams(txUuid: 'pay@x'), '{"txUuid":"pay@x"}'],
			[new CreateRefundParams(txUuid: 'pay@x', refundPercent: '50.00'), '{"txUuid":"pay@x","refundPercent":"50.00"}'],
			[new CreateWebhookEndpointParams(url: 'https://x.io', includeMembers: false), '{"url":"https://x.io","includeMembers":false}'],
			[new CreateWebhookEndpointParams(url: 'https://x.io', events: []), '{"url":"https://x.io"}'],
		];
		foreach ($cases as [$params, $want]) {
			$this->assertSame($want, Transport::encodeBody($params->toArray()), $params::class);
		}

		$this->check('clear lastName/phone/address', new UpdateCustomerParams(lastName: '', phoneNumber: '', address: ''));
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
		$params = new CreateCustomerParams(firstName: 'x', email: 'nope', lastName: '<', phoneNumber: '1', address: ' ', reference: 'a b', metadata: ['k' => "\t"]);
		$this->check('all', $params, ['firstName', 'lastName', 'email', 'phoneNumber', 'address', 'reference', 'metadata.k']);
		try {
			$params->validate();
		} catch (\QBitFlow\Exceptions\ValidationException $e) {
			$this->assertStringStartsWith('validation failed; firstName: firstName must be 2 to 100 characters', $e->getMessage());
			$this->assertSame('validation failed', $e->errorMessage);
		}
	}
}
