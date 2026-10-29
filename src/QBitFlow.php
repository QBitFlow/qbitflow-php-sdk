<?php

declare(strict_types=1);

namespace QBitFlow;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use QBitFlow\Exceptions\ValidationException;
use QBitFlow\Http\Requester;
use QBitFlow\Http\Transport;
use QBitFlow\Models\Me;
use QBitFlow\Services\AccountingService;
use QBitFlow\Services\CheckoutSessionsService;
use QBitFlow\Services\CurrenciesService;
use QBitFlow\Services\CustomersService;
use QBitFlow\Services\FailuresService;
use QBitFlow\Services\InvitationsService;
use QBitFlow\Services\MembersService;
use QBitFlow\Services\PaymentsService;
use QBitFlow\Services\ProductsService;
use QBitFlow\Services\RefundsService;
use QBitFlow\Services\SubscriptionsService;
use QBitFlow\Services\TransactionsService;
use QBitFlow\Services\WalletsService;
use QBitFlow\Services\WebhooksService;
use QBitFlow\Support\Validator;

/**
 * The QBitFlow API client (API v2). Create one per API key and share it.
 *
 * ```php
 * $client = new QBitFlow(apiKey: getenv('QBITFLOW_API_KEY')); // or QBitFlow::fromEnv()
 *
 * $me = $client->me(); // the recommended start-up check
 * $session = $client->checkoutSessions->createPayment(new CreatePaymentSessionParams(
 *     productName: 'Premium access',
 *     priceUsd: '4.99', // money is a decimal string, never a float
 *     successUrl: 'https://shop.example.com/thanks?session=' . Placeholders::UUID,
 * ));
 * ```
 *
 * Each service is a property (`$client->products`, `$client->webhooks->endpoints`…), and also a
 * method of the same name for the Laravel facade (`QBitFlow::products()`): products, customers,
 * checkoutSessions, payments, transactions (payments and bills together), failures,
 * subscriptions, refunds, members, invitations, wallets, accounting, webhooks (with
 * `webhooks->endpoints` and `webhooks->events`) and currencies.
 *
 * Ids are strings: UUIDs, and prefixed transaction ids (`pay@…`, `sub@…`, `sub-hist@…`,
 * `refund@…`) passed back verbatim (the bare UUID works too); only currency ids are integers.
 * Money is a decimal string everywhere, inputs included: USD amounts (`priceUsd` `"4.99"`,
 * `amountUsd`), token amounts in the token's units (`amount` `"10"`, next to `amountMinUnits`
 * and the `currency` object) and fee rates in percent (`feePercent` `"0.99"`). An optional field
 * the API left out is null.
 */
final class QBitFlow
{
	/** This SDK's version, sent in the `User-Agent` header (`qbitflow-php/3.0.1`). */
	public const VERSION = '3.0.1';

	/** The API root used when no `baseUrl` is given. */
	public const DEFAULT_BASE_URL = 'https://api.qbitflow.app/v2';

	/** Seconds each HTTP attempt may take, by default. */
	public const DEFAULT_TIMEOUT = 30.0;

	/** Retries of a retryable call (a read, or one of the 6 idempotent creates), by default. */
	public const DEFAULT_MAX_RETRIES = 3;

	/** Manages the products. */
	public readonly ProductsService $products;

	/** Manages the customers. */
	public readonly CustomersService $customers;

	/** Creates checkout sessions and reads or expires them. */
	public readonly CheckoutSessionsService $checkoutSessions;

	/** Reads the one-time payments (`/payments…`). */
	public readonly PaymentsService $payments;

	/** Reads the feed of payments and bills together (`/transactions`). */
	public readonly TransactionsService $transactions;

	/** Reads the failed payment attempts (`/payment-failures`). */
	public readonly FailuresService $failures;

	/** Reads, updates, bills and cancels subscriptions (`/subscriptions…`, `/bills…`). */
	public readonly SubscriptionsService $subscriptions;

	/** Lists and reads refunds (`/refunds…`). */
	public readonly RefundsService $refunds;

	/** Manages the organization's members and their held funds (organization key). */
	public readonly MembersService $members;

	/** Invites members (organization key). */
	public readonly InvitationsService $invitations;

	/** Reads the wallets and the currencies they accept (`/wallets…`). */
	public readonly WalletsService $wallets;

	/** Exports the accounting events. */
	public readonly AccountingService $accounting;

	/** Verifies webhooks, and manages the endpoints (`->endpoints`) and the event log (`->events`). */
	public readonly WebhooksService $webhooks;

	/** Reads the currency catalog (`/currencies…`, public). */
	public readonly CurrenciesService $currencies;

	private readonly Requester $requester;

	/**
	 * @param string                       $apiKey         Your API key (sent as `X-API-Key`): non-blank, starting with `sk_`, printable ASCII without spaces (surrounding blanks are trimmed).
	 * @param string|null                  $baseUrl        The API root (default `https://api.qbitflow.app/v2`): an absolute http(s) URL without credentials, query or fragment; a trailing `/` is stripped.
	 * @param float|null                   $timeout        Seconds per HTTP attempt (default 30), the whole attempt through the last byte of the body, applied to the HTTP client the SDK builds. A retried call may take longer in total.
	 * @param int|null                     $maxRetries     Retries of a retryable call (default 3); 0 disables them.
	 * @param string|null                  $onBehalfOf     Acts in a member's space on every request (`On-Behalf-Of`: the member's userUuid).
	 * @param ClientInterface|null         $httpClient     Your own PSR-18 client (proxies, instrumentation). It must not follow redirects, and its own timeout applies.
	 * @param RequestFactoryInterface|null $requestFactory Your own PSR-17 request factory.
	 * @param StreamFactoryInterface|null  $streamFactory  Your own PSR-17 stream factory.
	 *
	 * @throws ValidationException For a bad key or option. Nothing is sent: call {@see QBitFlow::me()} to check the key online.
	 */
	public function __construct(
		#[\SensitiveParameter]
		string $apiKey,
		?string $baseUrl = null,
		?float $timeout = null,
		?int $maxRetries = null,
		?string $onBehalfOf = null,
		?ClientInterface $httpClient = null,
		?RequestFactoryInterface $requestFactory = null,
		?StreamFactoryInterface $streamFactory = null,
	) {
		$apiKey = trim($apiKey);
		if ($apiKey === '') {
			throw Validator::fieldError('apiKey', 'is required');
		}
		if (! str_starts_with($apiKey, 'sk_')) {
			throw Validator::fieldError('apiKey', 'must be a QBitFlow API key (sk_…)');
		}
		// A value no header can carry (the PSR-7 implementations would refuse it at the first
		// request, quoting the key in their error): refused here, never echoed.
		if (preg_match('/^[\x21-\x7E]+$/D', $apiKey) !== 1) {
			throw Validator::fieldError('apiKey', 'must be printable ASCII without spaces (a QBitFlow API key, sk_…)');
		}

		$base = self::DEFAULT_BASE_URL;
		if ($baseUrl !== null) {
			$base = rtrim(trim($baseUrl), '/');
			if (! Validator::isHttpUrl($base) || ! in_array(strtolower((string) parse_url($base, PHP_URL_SCHEME)), ['http', 'https'], true)) {
				throw Validator::fieldError('baseUrl', 'must be an absolute http or https URL');
			}
			// A query or a fragment would swallow every route's path, and credentials would travel
			// with every request (and into HTTP clients' error messages).
			$parts = (array) parse_url($base);
			if (str_contains($base, '?') || str_contains($base, '#') || isset($parts['user']) || isset($parts['pass'])) {
				throw Validator::fieldError('baseUrl', 'must not contain credentials, a query or a fragment');
			}
		}
		if ($timeout !== null && (! is_finite($timeout) || $timeout <= 0)) {
			throw Validator::fieldError('timeout', 'must be positive');
		}
		if ($maxRetries !== null && $maxRetries < 0) {
			throw Validator::fieldError('maxRetries', 'must not be negative');
		}
		if ($onBehalfOf !== null) {
			Transport::checkOnBehalfOf($onBehalfOf);
		}

		$this->init(new Requester(new Transport(
			$apiKey,
			$base,
			$timeout ?? self::DEFAULT_TIMEOUT,
			$maxRetries ?? self::DEFAULT_MAX_RETRIES,
			$httpClient,
			$requestFactory,
			$streamFactory,
		), $onBehalfOf));
	}

	/** The environment variable {@see fromEnv()} reads the API key from (required). */
	public const ENV_API_KEY = 'QBITFLOW_API_KEY';

	/** The environment variable {@see fromEnv()} reads the base URL from (optional). */
	public const ENV_BASE_URL = 'QBITFLOW_BASE_URL';

	/** The environment variable {@see fromEnv()} reads the default `On-Behalf-Of` from (optional). */
	public const ENV_ON_BEHALF_OF = 'QBITFLOW_ON_BEHALF_OF';

	/**
	 * A client configured from the environment (`getenv()`, then `$_ENV`, then `$_SERVER`, so
	 * a `.env` loaded by phpdotenv works too): the API key from `QBITFLOW_API_KEY` (required),
	 * the base URL from `QBITFLOW_BASE_URL` when set (else the default), the default
	 * `On-Behalf-Of` from `QBITFLOW_ON_BEHALF_OF` when set. A named argument overrides its
	 * variable; the others are the constructor's.
	 *
	 * ```php
	 * $client = QBitFlow::fromEnv();
	 * $client = QBitFlow::fromEnv(timeout: 10.0, maxRetries: 0);
	 * ```
	 *
	 * @throws ValidationException When `QBITFLOW_API_KEY` is not set (and no `apiKey:`), or as the constructor.
	 */
	public static function fromEnv(
		#[\SensitiveParameter]
		?string $apiKey = null,
		?string $baseUrl = null,
		?float $timeout = null,
		?int $maxRetries = null,
		?string $onBehalfOf = null,
		?ClientInterface $httpClient = null,
		?RequestFactoryInterface $requestFactory = null,
		?StreamFactoryInterface $streamFactory = null,
	): self {
		$apiKey ??= self::env(self::ENV_API_KEY);
		if ($apiKey === null) {
			throw Validator::fieldError(self::ENV_API_KEY, 'is not set: set it in the environment (your sk_… API key), or pass apiKey:');
		}

		return new self(
			apiKey: $apiKey,
			baseUrl: $baseUrl ?? self::env(self::ENV_BASE_URL),
			timeout: $timeout,
			maxRetries: $maxRetries,
			onBehalfOf: $onBehalfOf ?? self::env(self::ENV_ON_BEHALF_OF),
			httpClient: $httpClient,
			requestFactory: $requestFactory,
			streamFactory: $streamFactory,
		);
	}

	/**
	 * A client that acts in a member's space: every request sends `On-Behalf-Of: <userUuid>`
	 * (a member's userUuid; organization key only). It shares this client's transport and
	 * configuration; `''` returns one at the organization level (the header is then omitted: it
	 * is never sent blank). Acting for a member, the role is `user`: the admin methods (members,
	 * invitations, `wallets->listForMember()`) answer 403. This client is unchanged.
	 *
	 * ```php
	 * $seller = $client->onBehalfOf($member->userUuid);
	 * $seller->products->list();
	 * ```
	 *
	 * @throws ValidationException When `$userUuid` is not a UUID, or is the nil UUID.
	 */
	public function onBehalfOf(string $userUuid): self
	{
		return self::fromTransport($this->requester->transport, Transport::checkOnBehalfOf($userUuid));
	}

	/**
	 * What the API key is (`GET /me`): its role, its space (organization or member, test or
	 * live mode) and the member it acts for. The recommended start-up check, e.g. assert
	 * `$me->space->test` in a test environment.
	 */
	public function me(?RequestOptions $options = null): Me
	{
		return $this->requester->call('GET', '/me', Requester::one(Me::fromArray(...)), options: $options);
	}

	/** The API root this client calls. */
	public function getBaseUrl(): string
	{
		return $this->requester->transport->baseUrl();
	}

	/** The client's default `On-Behalf-Of` (a member's userUuid); null at the organization level. */
	public function getOnBehalfOf(): ?string
	{
		$value = $this->requester->onBehalfOf;

		return $value === '' ? null : $value;
	}

	/**
	 * Builds a client on an existing transport.
	 *
	 * @internal For the SDK's tests (a transport with an injected sleep and clock).
	 */
	public static function fromTransport(Transport $transport, ?string $onBehalfOf = null): self
	{
		$client = (new \ReflectionClass(self::class))->newInstanceWithoutConstructor();
		$client->init(new Requester($transport, $onBehalfOf));

		return $client;
	}

	/** The `$products` service, as a method (for the Laravel facade: `QBitFlow::products()`). */
	public function products(): ProductsService
	{
		return $this->products;
	}

	/** The `$customers` service, as a method (for the Laravel facade: `QBitFlow::customers()`). */
	public function customers(): CustomersService
	{
		return $this->customers;
	}

	/** The `$checkoutSessions` service, as a method (for the Laravel facade: `QBitFlow::checkoutSessions()`). */
	public function checkoutSessions(): CheckoutSessionsService
	{
		return $this->checkoutSessions;
	}

	/** The `$payments` service, as a method (for the Laravel facade: `QBitFlow::payments()`). */
	public function payments(): PaymentsService
	{
		return $this->payments;
	}

	/** The `$transactions` service, as a method (for the Laravel facade: `QBitFlow::transactions()`). */
	public function transactions(): TransactionsService
	{
		return $this->transactions;
	}

	/** The `$failures` service, as a method (for the Laravel facade: `QBitFlow::failures()`). */
	public function failures(): FailuresService
	{
		return $this->failures;
	}

	/** The `$subscriptions` service, as a method (for the Laravel facade: `QBitFlow::subscriptions()`). */
	public function subscriptions(): SubscriptionsService
	{
		return $this->subscriptions;
	}

	/** The `$refunds` service, as a method (for the Laravel facade: `QBitFlow::refunds()`). */
	public function refunds(): RefundsService
	{
		return $this->refunds;
	}

	/** The `$members` service, as a method (for the Laravel facade: `QBitFlow::members()`). */
	public function members(): MembersService
	{
		return $this->members;
	}

	/** The `$invitations` service, as a method (for the Laravel facade: `QBitFlow::invitations()`). */
	public function invitations(): InvitationsService
	{
		return $this->invitations;
	}

	/** The `$wallets` service, as a method (for the Laravel facade: `QBitFlow::wallets()`). */
	public function wallets(): WalletsService
	{
		return $this->wallets;
	}

	/** The `$accounting` service, as a method (for the Laravel facade: `QBitFlow::accounting()`). */
	public function accounting(): AccountingService
	{
		return $this->accounting;
	}

	/** The `$webhooks` service, as a method (for the Laravel facade: `QBitFlow::webhooks()`). */
	public function webhooks(): WebhooksService
	{
		return $this->webhooks;
	}

	/** The `$currencies` service, as a method (for the Laravel facade: `QBitFlow::currencies()`). */
	public function currencies(): CurrenciesService
	{
		return $this->currencies;
	}

	/**
	 * Never exposes the API key in debug output.
	 *
	 * @return array<string,mixed>
	 */
	public function __debugInfo(): array
	{
		return [
			'apiKey' => '***' . substr($this->requester->transport->apiKey(), -4),
			'baseUrl' => $this->getBaseUrl(),
			'onBehalfOf' => $this->getOnBehalfOf(),
		];
	}

	/** An environment variable's value (`getenv()`, `$_ENV`, `$_SERVER`); null when unset or blank. */
	private static function env(string $name): ?string
	{
		foreach ([getenv($name), $_ENV[$name] ?? null, $_SERVER[$name] ?? null] as $value) {
			if (is_string($value) && trim($value) !== '') {
				return trim($value);
			}
		}

		return null;
	}

	/**
	 * Wires the client's services on `$requester`: from the constructor, and for the clients
	 * that share a transport (fromTransport(), which skips the constructor).
	 */
	private function init(Requester $requester): void
	{
		$this->requester = $requester;
		$this->products = new ProductsService($requester);
		$this->customers = new CustomersService($requester);
		$this->checkoutSessions = new CheckoutSessionsService($requester);
		$this->payments = new PaymentsService($requester);
		$this->transactions = new TransactionsService($requester);
		$this->failures = new FailuresService($requester);
		$this->subscriptions = new SubscriptionsService($requester);
		$this->refunds = new RefundsService($requester);
		$this->members = new MembersService($requester);
		$this->invitations = new InvitationsService($requester);
		$this->wallets = new WalletsService($requester);
		$this->accounting = new AccountingService($requester);
		$this->webhooks = new WebhooksService($requester);
		$this->currencies = new CurrenciesService($requester);
	}
}
