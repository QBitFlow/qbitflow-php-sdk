<?php

declare(strict_types=1);

namespace QBitFlow\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use QBitFlow\Dto\CreateCustomerDto;
use QBitFlow\Dto\CreateProductDto;
use QBitFlow\Dto\CreateUserDto;
use QBitFlow\Dto\Session\CreatePaymentSessionDto;
use QBitFlow\Dto\Session\CreateSubscriptionSessionDto;
use QBitFlow\Dto\UpdateCustomerDto;
use QBitFlow\Dto\UpdateProductDto;
use QBitFlow\Dto\UpdateUserDto;
use QBitFlow\Enums\TransactionType;
use QBitFlow\Enums\UserRole;
use QBitFlow\Exceptions\ValidationException;
use QBitFlow\QBitFlow;
use QBitFlow\Support\Duration;
use QBitFlow\Support\Validate;
use QBitFlow\Tests\Support\TestCase;

/**
 * Local validation: mistakes should surface as a clear error before a request is sent.
 * The rules mirror the API's `binding` tags and are identical across the QBitFlow SDKs.
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

	// -------------------------------------------------------------------------
	// Sessions
	// -------------------------------------------------------------------------

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
		// refused, mirroring the API's `http_url` rule.
		foreach (['javascript:alert(1)', '/relative/path', 'ftp://example.com'] as $bad) {
			try {
				(new CreatePaymentSessionDto(productId: 1, successUrl: $bad))->validate();
				$this->fail("expected {$bad} to be rejected");
			} catch (ValidationException $e) {
				$this->assertStringContainsString('successUrl must be an absolute', $e->getMessage());
			}
		}

		(new CreatePaymentSessionDto(productId: 1, successUrl: 'https://ok.test/done'))->validate();
		(new CreatePaymentSessionDto(productId: 1, successUrl: 'https://checkout-web/success'))->validate();
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
	public function a_session_price_must_be_finite_and_greater_than_zero(): void
	{
		// Live-verified: an inline price of 0 is refused with a 400 (omitempty drops it, so the
		// ghost product is incomplete), like a negative or non-finite one.
		(new CreatePaymentSessionDto(productName: 'Widget', description: 'A widget', price: 0.01))->validate();

		foreach ([0.0, -1.0, INF, NAN] as $price) {
			try {
				(new CreatePaymentSessionDto(productName: 'Widget', description: 'A widget', price: $price))->validate();
				$this->fail("Expected price {$price} to be rejected.");
			} catch (ValidationException $e) {
				$this->assertSame('price must be a finite number greater than 0', $e->getMessage());
			}
		}
	}

	#[Test]
	public function a_customer_uuid_must_be_a_bare_uuid(): void
	{
		// Live-verified: a non-UUID or a prefixed id is a 400; '' means "not provided".
		(new CreatePaymentSessionDto(productId: 1, customerUUID: '01997C89-D0E9-7C9A-9886-FE7709919695'))->validate();

		$empty = new CreatePaymentSessionDto(productId: 1, customerUUID: '');
		$empty->validate();
		$this->assertArrayNotHasKey('customerUUID', $empty->toArray(), '"" is left off the request.');

		foreach (['customer-uuid', 'pay@01997c89-d0e9-7c9a-9886-fe7709919695', '01997c89d0e97c9a9886fe7709919695'] as $bad) {
			try {
				(new CreatePaymentSessionDto(productId: 1, customerUUID: $bad))->validate();
				$this->fail("Expected {$bad} to be rejected.");
			} catch (ValidationException $e) {
				$this->assertStringContainsString('customerUUID must be a bare UUID', $e->getMessage());
			}
		}
	}

	#[Test]
	public function empty_optional_session_strings_are_left_off_the_request(): void
	{
		$dto = new CreatePaymentSessionDto(
			reference: '',
			productId: 1,
			productReference: '',
			successUrl: '',
			cancelUrl: '',
			customerReference: '',
		);

		$this->assertSame(['productId' => 1], $dto->toArray());
	}

	#[Test]
	public function a_redirect_scheme_is_case_insensitive(): void
	{
		// Live-verified: the API accepts HTTPS:// as it lowercases the scheme.
		(new CreatePaymentSessionDto(productId: 1, successUrl: 'HTTPS://example.com/ok', cancelUrl: 'Http://x.test'))->validate();

		$this->addToAssertionCount(1);
	}

	#[Test]
	public function a_redirect_url_is_checked_as_written(): void
	{
		// Live-verified against the API (Go's url.Parse): a trailing space or a space in the
		// path is accepted; a leading space, a space in the host, a backslash authority or an
		// empty authority is a 400.
		foreach (['https://example.com/ok ', 'https://example.com/o k'] as $accepted) {
			(new CreatePaymentSessionDto(productId: 1, successUrl: $accepted))->validate();
		}

		foreach ([' https://example.com/ok', 'https://exa mple.com/ok', 'http:\\\\example.com', 'http:///x'] as $rejected) {
			try {
				(new CreatePaymentSessionDto(productId: 1, successUrl: $rejected))->validate();
				$this->fail("expected {$rejected} to be rejected");
			} catch (ValidationException) {
				$this->addToAssertionCount(1);
			}
		}
	}

	#[Test]
	public function a_subscription_session_accepts_any_product_form(): void
	{
		(new CreateSubscriptionSessionDto(frequency: Duration::months(1), productId: 1))->validate();
		(new CreateSubscriptionSessionDto(frequency: Duration::months(1), productReference: 'P'))->validate();

		// An inline ghost product is accepted for a subscription exactly as it is for a
		// one-time payment (the API returns 201 for it).
		(new CreateSubscriptionSessionDto(
			frequency: Duration::months(1),
			productName: 'Pro plan',
			description: 'Monthly Pro subscription',
			price: 29.0,
		))->validate();

		$this->expectNotToPerformAssertions();
	}

	#[Test]
	public function a_subscription_session_still_requires_some_product(): void
	{
		$this->expectException(ValidationException::class);
		$this->expectExceptionMessage(
			'Either productId, productReference, or all of productName, description and price must be provided',
		);

		(new CreateSubscriptionSessionDto(frequency: Duration::months(1)))->validate();
	}

	#[Test]
	public function a_subscription_session_applies_the_shared_product_rules(): void
	{
		$this->expectException(ValidationException::class);

		(new CreateSubscriptionSessionDto(
			frequency: Duration::months(1),
			productName: '<script>',
			description: 'Y',
			price: 10.0,
		))->validate();
	}

	#[Test]
	public function minimum_periods_accept_zero_and_omit_it(): void
	{
		// Live-verified: minPeriods 0 is accepted (it is `omitempty`, meaning "no minimum").
		$zero = new CreateSubscriptionSessionDto(frequency: Duration::months(1), productId: 1, minPeriods: 0);
		$zero->validate();
		$this->assertArrayNotHasKey('minPeriods', $zero->toArray());

		$three = new CreateSubscriptionSessionDto(frequency: Duration::months(1), productId: 1, minPeriods: 3);
		$this->assertSame(3, $three->toArray()['minPeriods']);

		foreach ([-1, Validate::UINT32_MAX + 1] as $bad) {
			try {
				(new CreateSubscriptionSessionDto(frequency: Duration::months(1), productId: 1, minPeriods: $bad))->validate();
				$this->fail("Expected minPeriods {$bad} to be rejected.");
			} catch (ValidationException $e) {
				$this->assertSame('minPeriods must be between 0 and 4294967295', $e->getMessage());
			}
		}
	}

	#[Test]
	public function a_billing_frequency_must_be_at_least_one_and_a_trial_may_be_zero(): void
	{
		// Live-verified: trialPeriod.value 0 is accepted.
		$session = new CreateSubscriptionSessionDto(
			frequency: Duration::days(30),
			productId: 1,
			trialPeriod: Duration::days(0),
		);
		$session->validate();
		$this->assertSame(['value' => 0, 'unit' => 'days'], $session->toArray()['trialPeriod']);

		$this->expectException(ValidationException::class);
		$this->expectExceptionMessage('Frequency value must be at least 1');

		(new CreateSubscriptionSessionDto(frequency: Duration::months(0), productId: 1))->validate();
	}

	#[Test]
	public function a_subscription_session_built_from_an_array_requires_a_frequency(): void
	{
		$this->expectException(ValidationException::class);
		$this->expectExceptionMessage('Frequency is required');

		CreateSubscriptionSessionDto::fromArray(['productId' => 1]);
	}

	#[Test]
	public function a_duration_fits_a_uint32_and_uses_a_known_unit(): void
	{
		$this->assertSame('3 days', (string) new Duration(3, 'days'));
		$this->assertSame('1 month', (string) Duration::months(1));
		$this->assertSame(Validate::UINT32_MAX, Duration::seconds(Validate::UINT32_MAX)->value);

		// Live-verified: a frequency beyond uint32 is a 400.
		foreach ([-1, Validate::UINT32_MAX + 1] as $bad) {
			try {
				new Duration($bad, 'days');
				$this->fail('Expected a ValidationException.');
			} catch (ValidationException $e) {
				$this->assertSame('Duration value must be between 0 and 4294967295', $e->getMessage());
			}
		}

		try {
			Duration::fromArray(['value' => 1]);
			$this->fail('A missing unit must be rejected, not defaulted.');
		} catch (ValidationException $e) {
			$this->assertSame('Duration unit is required', $e->getMessage());
		}

		$this->expectException(ValidationException::class);
		$this->expectExceptionMessage('Invalid duration unit "fortnights"');

		new Duration(1, 'fortnights');
	}

	// -------------------------------------------------------------------------
	// Products
	// -------------------------------------------------------------------------

	#[Test]
	public function a_product_price_must_be_strictly_positive(): void
	{
		// The API binds `required,min=0` but rejects 0 with a 400: a stored product must
		// cost something. Previously only negatives were caught locally.
		new CreateProductDto('Widget', 'A widget', 0.01);

		foreach ([0.0, -0.01, INF, NAN] as $price) {
			try {
				new CreateProductDto('Widget', 'A widget', $price);
				$this->fail("Expected {$price} to be rejected.");
			} catch (ValidationException $e) {
				$this->assertSame('price must be a finite number greater than 0', $e->getMessage());
			}
		}
	}

	#[Test]
	public function a_product_name_and_description_follow_producttext(): void
	{
		try {
			new CreateProductDto('<b>Widget</b>', 'A widget', 1.0);
			$this->fail('Markup must be rejected.');
		} catch (ValidationException $e) {
			$this->assertStringContainsString('name must not contain the character <', $e->getMessage());
		}

		try {
			new CreateProductDto('W', 'A widget', 1.0);
			$this->fail('A one-character name must be rejected.');
		} catch (ValidationException $e) {
			$this->assertSame('name must be between 2 and 100 characters', $e->getMessage());
		}

		try {
			new CreateProductDto('Widget', str_repeat('x', 501), 1.0);
			$this->fail('A 501-character description must be rejected.');
		} catch (ValidationException $e) {
			$this->assertSame('description must be between 2 and 500 characters', $e->getMessage());
		}

		try {
			new CreateProductDto('Widget', '', 1.0);
			$this->fail('An empty description must be rejected.');
		} catch (ValidationException $e) {
			$this->assertSame('description is required', $e->getMessage());
		}

		// Accented and CJK names are counted in characters, not bytes.
		new CreateProductDto(str_repeat('é', 100), str_repeat('日', 500), 1.0);
		$this->addToAssertionCount(1);
	}

	#[Test]
	public function producttext_rejects_control_characters_including_c1(): void
	{
		// Go's unicode.IsControl covers U+0000–U+001F and U+007F–U+009F; tab, newline and
		// carriage return are the allowed exceptions.
		Validate::productText('f', "Line one\nLine two\ttabbed\r", 2, 100);

		foreach (["a\x00b", "a\x7Fb", "a\u{0085}b", "a\u{009F}b"] as $bad) {
			try {
				Validate::productText('f', $bad, 2, 100);
				$this->fail('Expected a control character to be rejected.');
			} catch (ValidationException $e) {
				$this->assertSame('f must not contain control characters', $e->getMessage());
			}
		}
	}

	#[Test]
	public function producttext_treats_unicode_whitespace_as_blank(): void
	{
		// Go's strings.TrimSpace trims Unicode whitespace (NBSP, ideographic space, …).
		foreach (["   ", "\u{00A0}\u{00A0}", "\u{3000}\u{2028}\t"] as $blank) {
			try {
				Validate::productText('f', $blank, 2, 100);
				$this->fail('Expected blank text to be rejected.');
			} catch (ValidationException $e) {
				$this->assertSame('f must not be blank', $e->getMessage());
			}
		}
	}

	#[Test]
	public function an_update_product_price_must_be_positive(): void
	{
		$this->expectException(ValidationException::class);
		$this->expectExceptionMessage('price must be a finite number greater than 0');

		new UpdateProductDto(price: 0.0);
	}

	#[Test]
	public function an_update_product_validates_only_the_fields_that_are_set(): void
	{
		// Partial update: unset fields must not reach the wire.
		$this->assertSame(['price' => 39.99], (new UpdateProductDto(price: 39.99))->toArray());
		$this->assertSame([], (new UpdateProductDto())->toArray());

		$this->expectException(ValidationException::class);
		$this->expectExceptionMessage('name must not contain the character <');

		new UpdateProductDto(name: '<i>');
	}

	#[Test]
	public function an_update_product_rejects_an_empty_name_or_description(): void
	{
		// The API rejects an empty name or description on update, so '' is not "unset" here.
		foreach (['name', 'description'] as $field) {
			try {
				new UpdateProductDto(...[$field => '']);
				$this->fail("Expected an empty {$field} to be rejected.");
			} catch (ValidationException $e) {
				$this->assertSame("{$field} must not be blank", $e->getMessage());
			}
		}
	}

	// -------------------------------------------------------------------------
	// Customers and users
	// -------------------------------------------------------------------------

	/**
	 * @return iterable<string,array{string}>
	 */
	public static function badNameProvider(): iterable
	{
		yield 'markup' => ['<b>'];
		yield 'ampersand' => ['Tom & Jerry'];
		yield 'comma' => ['Doe, John'];
		yield 'parentheses' => ['John (Jack)'];
		yield 'at sign' => ['john@doe'];
	}

	#[Test]
	#[DataProvider('badNameProvider')]
	public function a_customer_name_follows_alphanumspace(string $bad): void
	{
		$this->expectException(ValidationException::class);
		$this->expectExceptionMessage('name may only contain letters, digits, spaces and');

		new CreateCustomerDto($bad, 'Doe', 'john@example.com');
	}

	#[Test]
	public function alphanumspace_allows_letters_digits_spaces_and_four_punctuation_marks(): void
	{
		new CreateCustomerDto("Jean-Luc O'Neil Jr.", 'Müller_2', 'jl@example.com');
		new CreateCustomerDto('山田', '太郎', 'yamada@example.jp');
		new CreateCustomerDto('Ana', 'Nº٣', 'ana@example.com'); // Arabic-Indic digit ٣ is Nd

		$this->addToAssertionCount(1);
	}

	#[Test]
	public function alphanumspace_accepts_only_decimal_digits(): void
	{
		// Live-verified: "Jo²" and "Ⅻ" are rejected — only Unicode Nd counts as a digit.
		foreach (['Jo²', 'ⅫⅫ', 'Half ½'] as $bad) {
			try {
				new CreateCustomerDto($bad, 'Doe', 'john@example.com');
				$this->fail("Expected {$bad} to be rejected.");
			} catch (ValidationException $e) {
				$this->assertStringContainsString('name may only contain letters, digits', $e->getMessage());
			}
		}
	}

	#[Test]
	public function a_whitespace_only_name_is_accepted(): void
	{
		// Live-verified: the API accepts "   " for a name.
		$dto = new CreateCustomerDto('   ', '   ', 'john@example.com');

		$this->assertSame('   ', $dto->name);

		$this->expectException(ValidationException::class);
		$this->expectExceptionMessage('name is required');

		new CreateCustomerDto('', 'Doe', 'john@example.com');
	}

	#[Test]
	public function a_customer_name_must_be_between_2_and_100_characters(): void
	{
		try {
			new CreateCustomerDto('J', 'Doe', 'john@example.com');
			$this->fail('Expected a one-character name to be rejected.');
		} catch (ValidationException $e) {
			$this->assertSame('name must be between 2 and 100 characters', $e->getMessage());
		}

		$this->expectException(ValidationException::class);
		$this->expectExceptionMessage('lastName must be between 2 and 100 characters');

		new CreateCustomerDto('John', str_repeat('D', 101), 'john@example.com');
	}

	/**
	 * @return iterable<string,array{string}>
	 */
	public static function badEmailProvider(): iterable
	{
		yield 'no at' => ['nope'];
		yield 'no domain' => ['john@'];
		yield 'no local part' => ['@example.com'];
		yield 'no dot in domain' => ['john@localhost'];
		yield 'two at signs' => ['john@@example.com'];
		yield 'whitespace' => ['john doe@example.com'];
		yield 'trailing dot' => ['john@example.'];
	}

	#[Test]
	#[DataProvider('badEmailProvider')]
	public function a_customer_email_must_be_valid(string $bad): void
	{
		$this->expectException(ValidationException::class);
		$this->expectExceptionMessage('email must be a valid email address');

		new CreateCustomerDto('John', 'Doe', $bad);
	}

	#[Test]
	public function a_customer_create_requires_its_three_fields(): void
	{
		$this->expectException(ValidationException::class);
		$this->expectExceptionMessage('email is required');

		new CreateCustomerDto('John', 'Doe', '');
	}

	#[Test]
	public function a_customer_update_validates_provided_fields_and_ignores_empty_ones(): void
	{
		$this->assertSame(['email' => 'new@example.com'], (new UpdateCustomerDto(email: 'new@example.com', name: ''))->toArray());

		$this->expectException(ValidationException::class);
		$this->expectExceptionMessage('email must be a valid email address');

		new UpdateCustomerDto(email: 'nope');
	}

	#[Test]
	public function a_user_create_applies_the_same_name_and_email_rules(): void
	{
		new CreateUserDto('Jane', 'Smith', 'jane@example.com', UserRole::ADMIN, 100);

		try {
			new CreateUserDto('Jane<', 'Smith', 'jane@example.com');
			$this->fail('Markup must be rejected.');
		} catch (ValidationException $e) {
			$this->assertStringContainsString('name may only contain', $e->getMessage());
		}

		$this->expectException(ValidationException::class);
		$this->expectExceptionMessage('email must be a valid email address');

		new CreateUserDto('Jane', 'Smith', 'jane@nowhere');
	}

	#[Test]
	public function an_organization_fee_must_stay_within_range(): void
	{
		new CreateUserDto('Jane', 'Smith', 'jane@example.com', organizationFeeBps: 5000);

		$this->expectException(ValidationException::class);
		$this->expectExceptionMessage('organizationFeeBps must be between 0 and 5000');

		new CreateUserDto('Jane', 'Smith', 'jane@example.com', organizationFeeBps: 5001);
	}

	#[Test]
	public function an_update_user_organization_fee_must_be_within_range(): void
	{
		$this->expectException(ValidationException::class);
		$this->expectExceptionMessage('organizationFeeBps must be between 0 and 5000');

		new UpdateUserDto(organizationFeeBps: 5001);
	}

	#[Test]
	public function an_update_user_validates_provided_names(): void
	{
		$this->expectException(ValidationException::class);
		$this->expectExceptionMessage('name may only contain letters');

		new UpdateUserDto(name: 'Jane & Co');
	}

	#[Test]
	public function an_empty_update_user_payload_is_allowed(): void
	{
		// Every field is optional: an empty payload is a valid no-op and must not throw.
		$this->assertSame([], (new UpdateUserDto())->toArray());
		$this->assertSame([], (new UpdateUserDto(name: '', email: ''))->toArray());
	}

	#[Test]
	public function it_rejects_a_read_only_role_when_creating_a_user(): void
	{
		// The API binds `oneof=admin user`; OWNER and HANDLE are readable but not
		// assignable, so sending either is a guaranteed 400.
		foreach ([UserRole::OWNER, UserRole::HANDLE] as $role) {
			try {
				new CreateUserDto(name: 'Jane', lastName: 'Smith', email: 'j@x.com', role: $role);
				$this->fail('Expected ' . $role->value . ' to be rejected.');
			} catch (ValidationException $e) {
				$this->assertStringContainsString("'admin' or 'user'", $e->getMessage());
			}
		}
	}

	#[Test]
	public function it_accepts_the_assignable_roles_when_creating_a_user(): void
	{
		foreach ([UserRole::ADMIN, UserRole::USER] as $role) {
			$dto = new CreateUserDto(name: 'Jane', lastName: 'Smith', email: 'j@x.com', role: $role);

			$this->assertSame($role, $dto->role);
		}
	}

	#[Test]
	public function an_unknown_role_string_in_an_array_payload_is_a_validation_error(): void
	{
		// Request DTOs stay strict: an unknown role is the caller's mistake, reported as
		// the SDK's own exception rather than a bare ValueError.
		$this->expectException(ValidationException::class);
		$this->expectExceptionMessage('Invalid role "god"');

		CreateUserDto::fromArray(['name' => 'Jane', 'lastName' => 'Smith', 'email' => 'j@x.com', 'role' => 'god']);
	}

	// -------------------------------------------------------------------------
	// Identifier and lookup guards
	// -------------------------------------------------------------------------

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
	public function email_lookups_apply_the_email_rule(): void
	{
		$client = $this->client();

		foreach ([fn () => $client->customers->getByEmail('nope@'), fn () => $client->users->getByEmail('john@localhost')] as $call) {
			try {
				$call();
				$this->fail('Expected a ValidationException.');
			} catch (ValidationException $e) {
				$this->assertSame('email must be a valid email address', $e->getMessage());
			}
		}

		try {
			$client->customers->getByEmail('');
			$this->fail('Expected a ValidationException.');
		} catch (ValidationException $e) {
			$this->assertSame('email is required', $e->getMessage());
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

	// -------------------------------------------------------------------------
	// Accounting export
	// -------------------------------------------------------------------------

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
		$this->expectExceptionMessage('from date is required (YYYY-MM-DD)');

		$this->client()->accounting->exportJson('', '2026-01-31');
	}

	/**
	 * @return iterable<string,array{string,string,string}>
	 */
	public static function badWindowProvider(): iterable
	{
		yield 'from not ISO' => ['01/01/2026', '2026-01-31', 'from must be a date formatted YYYY-MM-DD'];
		yield 'to not ISO' => ['2026-01-01', '2026-1-31', 'to must be a date formatted YYYY-MM-DD'];
		yield 'impossible date' => ['2026-02-30', '2026-03-01', 'from must be a date formatted YYYY-MM-DD'];
		yield 'from after to' => ['2026-02-01', '2026-01-01', "'from' date must not be after 'to' date"];
		yield 'missing to' => ['2026-01-01', '', 'to date is required (YYYY-MM-DD)'];
	}

	#[Test]
	#[DataProvider('badWindowProvider')]
	public function accounting_export_validates_the_window(string $from, string $to, string $message): void
	{
		$this->expectException(ValidationException::class);
		$this->expectExceptionMessage($message);

		try {
			$this->client()->accounting->exportJson($from, $to);
		} finally {
			$this->assertSame(0, $this->http->requestCount());
		}
	}

	#[Test]
	public function accounting_export_leaves_the_window_length_to_the_api(): void
	{
		// Live-verified: spans of 95 days are accepted, so no local length check applies.
		$this->http->push([])->push([]);

		$this->client()->accounting->exportJson('2026-01-01', '2026-04-06');
		$this->client()->accounting->exportJson('2026-01-01', '2026-01-01');

		$this->assertSame(2, $this->http->requestCount());
	}
}
