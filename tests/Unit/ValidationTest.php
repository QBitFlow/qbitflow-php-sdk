<?php

declare(strict_types=1);

namespace QBitFlow\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use QBitFlow\Dto\CreateProductDto;
use QBitFlow\Dto\CreateUserDto;
use QBitFlow\Dto\Session\CreatePaymentSessionDto;
use QBitFlow\Dto\Session\CreateSubscriptionSessionDto;
use QBitFlow\Dto\UpdateProductDto;
use QBitFlow\Dto\UpdateUserDto;
use QBitFlow\Enums\TransactionType;
use QBitFlow\Exceptions\ValidationException;
use QBitFlow\QBitFlow;
use QBitFlow\Support\Duration;
use QBitFlow\Tests\Support\TestCase;

/**
 * Local validation: mistakes should surface as a clear error before a request is sent.
 */
final class ValidationTest extends TestCase
{
	#[Test]
	public function it_requires_an_api_key(): void
	{
		$this->expectException(ValidationException::class);
		$this->expectExceptionMessage('API key is required');

		new QBitFlow('   ');
	}

	#[Test]
	public function a_payment_session_needs_a_product_in_one_of_the_three_forms(): void
	{
		(new CreatePaymentSessionDto(productId: 1))->validate();
		(new CreatePaymentSessionDto(productReference: 'PROD-1'))->validate();
		(new CreatePaymentSessionDto(productName: 'Widget', description: 'A widget', price: 1.0))->validate();

		$this->expectException(ValidationException::class);
		$this->expectExceptionMessage('Either productId, productReference, or all of');

		(new CreatePaymentSessionDto(reference: 'order-1'))->validate();
	}

	#[Test]
	public function an_inline_product_name_must_satisfy_producttext(): void
	{
		// Mirrors the API's producttext rule: markup characters are refused locally so the
		// request never leaves the process.
		$this->expectException(ValidationException::class);
		$this->expectExceptionMessage('productName must not contain the character <');

		(new CreatePaymentSessionDto(
			productName: '<script>alert(1)</script>',
			description: 'A fine description',
			price: 1.0,
		))->validate();
	}

	#[Test]
	public function script_looking_text_without_markup_is_accepted(): void
	{
		// producttext blocks markup characters, not script-like wording: the server renders
		// these fields as escaped text, so this must NOT be rejected.
		(new CreatePaymentSessionDto(
			productName: 'javascript:alert(1)',
			description: 'A fine description',
			price: 1.0,
		))->validate();

		$this->addToAssertionCount(1);
	}

	#[Test]
	public function an_empty_optional_field_counts_as_not_provided(): void
	{
		// The API's omitempty skips validation for an empty value, so the SDK must not
		// reject getenv('SUCCESS_URL') when the variable is unset.
		(new CreatePaymentSessionDto(
			productId: 1,
			productName: '',
			description: '',
			successUrl: '',
		))->validate();

		$this->addToAssertionCount(1);
	}

	#[Test]
	public function a_redirect_url_must_be_absolute_http(): void
	{
		// A redirect target is attacker-visible, so non-web schemes and relative paths are
		// refused. Note the API's own `uri` rule is more permissive than this.
		foreach (['javascript:alert(1)', '/relative/path', 'ftp://example.com'] as $bad) {
			try {
				(new CreatePaymentSessionDto(productId: 1, successUrl: $bad))->validate();
				$this->fail("expected {$bad} to be rejected");
			} catch (ValidationException $e) {
				$this->assertStringContainsString('successUrl must be an absolute', $e->getMessage());
			}
		}

		(new CreatePaymentSessionDto(productId: 1, successUrl: 'https://ok.test/done'))->validate();
		$this->addToAssertionCount(1);
	}

	#[Test]
	public function a_partial_inline_product_is_rejected(): void
	{
		$this->expectException(ValidationException::class);

		// Missing the price.
		(new CreatePaymentSessionDto(productName: 'Widget', description: 'A widget'))->validate();
	}

	#[Test]
	public function a_payment_session_rejects_a_negative_price(): void
	{
		$this->expectException(ValidationException::class);
		$this->expectExceptionMessage('Price must be a non-negative value');

		(new CreatePaymentSessionDto(productName: 'Widget', description: 'A widget', price: -1.0))->validate();
	}

	#[Test]
	public function a_subscription_session_requires_a_stored_product(): void
	{
		(new CreateSubscriptionSessionDto(frequency: Duration::months(1), productId: 1))->validate();
		(new CreateSubscriptionSessionDto(frequency: Duration::months(1), productReference: 'P'))->validate();

		// The REST reference selects the product by productId or productReference for a
		// subscription; an inline product is not accepted, unlike a one-time payment.
		$this->expectException(ValidationException::class);
		$this->expectExceptionMessage('Either productId or productReference must be provided for a subscription');

		(new CreateSubscriptionSessionDto(
			frequency: Duration::months(1),
			productName: 'X',
			description: 'Y',
			price: 10.0,
		))->validate();
	}

	#[Test]
	public function a_subscription_session_rejects_non_positive_minimum_periods(): void
	{
		$this->expectException(ValidationException::class);
		$this->expectExceptionMessage('Minimum periods must be positive');

		(new CreateSubscriptionSessionDto(
			frequency: Duration::months(1),
			productId: 1,
			minPeriods: 0,
		))->validate();
	}

	#[Test]
	public function a_subscription_session_built_from_an_array_requires_a_frequency(): void
	{
		$this->expectException(ValidationException::class);
		$this->expectExceptionMessage('Frequency is required');

		CreateSubscriptionSessionDto::fromArray(['productId' => 1]);
	}

	#[Test]
	public function a_duration_must_be_positive_and_use_a_known_unit(): void
	{
		$this->assertSame('3 days', (string) new Duration(3, 'days'));
		$this->assertSame('1 month', (string) Duration::months(1));

		try {
			new Duration(0, 'days');
			$this->fail('Expected a ValidationException.');
		} catch (ValidationException $e) {
			$this->assertSame('Duration value must be positive', $e->getMessage());
		}

		$this->expectException(ValidationException::class);
		$this->expectExceptionMessage('Invalid duration unit "fortnights"');

		new Duration(1, 'fortnights');
	}

	#[Test]
	public function a_product_price_must_be_non_negative(): void
	{
		$this->expectException(ValidationException::class);

		new CreateProductDto('Widget', 'A widget', -0.01);
	}

	#[Test]
	public function an_organization_fee_must_stay_within_range(): void
	{
		new CreateUserDto('A', 'B', 'a@b.com', organizationFeeBps: 5000);

		$this->expectException(ValidationException::class);
		$this->expectExceptionMessage('organizationFeeBps must be between 0 and 5000');

		new CreateUserDto('A', 'B', 'a@b.com', organizationFeeBps: 5001);
	}

	#[Test]
	public function an_update_user_organization_fee_must_be_within_range(): void
	{
		$this->expectException(ValidationException::class);
		$this->expectExceptionMessage('organizationFeeBps must be between 0 and 5000');

		new UpdateUserDto(organizationFeeBps: 5001);
	}

	#[Test]
	public function an_empty_update_user_payload_is_allowed(): void
	{
		// Every field is optional: an empty payload is a valid no-op and must not throw.
		$this->assertSame([], (new UpdateUserDto())->toArray());
	}

	#[Test]
	public function an_update_product_price_must_be_positive(): void
	{
		$this->expectException(ValidationException::class);
		$this->expectExceptionMessage('Price must be greater than 0');

		new UpdateProductDto(price: 0.0);
	}

	#[Test]
	public function an_update_product_sends_only_the_fields_that_are_set(): void
	{
		// Partial update: unset fields must not reach the wire.
		$this->assertSame(['price' => 39.99], (new UpdateProductDto(price: 39.99))->toArray());
	}

	#[Test]
	public function empty_identifiers_are_rejected_before_a_request_is_sent(): void
	{
		$client = $this->client();

		$cases = [
			'Customer UUID is required' => fn () => $client->customers->get(''),
			'Customer reference is required' => fn () => $client->customers->getByReference(' '),
			'Payment UUID is required' => fn () => $client->oneTimePayments->get(''),
			'Subscription UUID is required' => fn () => $client->subscriptions->get(''),
			'Transaction UUID is required' => fn () => $client->transactionStatus->get('', TransactionType::REFUND),
			'Session UUID is required' => fn () => $client->oneTimePayments->getSession(''),
		];

		foreach ($cases as $message => $call) {
			try {
				$call();
				$this->fail('Expected "' . $message . '".');
			} catch (ValidationException $e) {
				$this->assertSame($message, $e->getMessage());
			}
		}

		$this->assertSame(0, $this->http->requestCount(), 'Nothing should reach the network.');
	}

	#[Test]
	public function invalid_emails_are_rejected_before_a_request_is_sent(): void
	{
		$client = $this->client();

		foreach ([fn () => $client->customers->getByEmail('nope'), fn () => $client->users->getByEmail('nope')] as $call) {
			try {
				$call();
				$this->fail('Expected a ValidationException.');
			} catch (ValidationException $e) {
				$this->assertSame('Valid email is required', $e->getMessage());
			}
		}

		$this->assertSame(0, $this->http->requestCount());
	}

	#[Test]
	public function non_positive_ids_are_rejected(): void
	{
		$client = $this->client();

		$this->expectException(ValidationException::class);
		$this->expectExceptionMessage('Product ID must be positive');

		$client->products->get(0);
	}

	#[Test]
	public function a_pagination_limit_must_be_positive(): void
	{
		$this->expectException(ValidationException::class);
		$this->expectExceptionMessage('Limit must be greater than 0');

		$this->client()->customers->getAll(limit: 0);
	}

	#[Test]
	public function an_unknown_export_format_is_rejected(): void
	{
		$this->expectException(ValidationException::class);
		$this->expectExceptionMessage('Invalid export format "xml"');

		$this->client()->accounting->export('2026-01-01', '2026-01-31', 'xml');
	}

	#[Test]
	public function accounting_export_requires_both_dates(): void
	{
		$this->expectException(ValidationException::class);
		$this->expectExceptionMessage('from and to dates are required');

		$this->client()->accounting->exportJson('', '2026-01-31');
	}
}
