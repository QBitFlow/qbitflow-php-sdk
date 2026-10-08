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
use QBitFlow\Services\WalletsService;
use QBitFlow\Services\WebhooksService;
use QBitFlow\Support\Validator;

/**
 * The QBitFlow API client (API v2). Create one per API key and share it.
 *
 * ```php
 * $client = new QBitFlow(apiKey: getenv('QBITFLOW_API_KEY'));
 *
 * $me = $client->me(); // the recommended start-up check
 * $session = $client->checkoutSessions->createPayment(new CreatePaymentSessionParams(
 *     productName: 'Premium access',
 *     price: 4.99,
 *     successUrl: 'https://shop.example.com/thanks?session={{UUID}}',
 * ));
 * ```
 *
 * Each service is a property (`$client->products`, `$client->webhooks->endpoints`…), and also a
 * method of the same name for the Laravel facade (`QBitFlow::products()`).
 */
final class QBitFlow
{
	/** This SDK's version, sent in the `User-Agent` header (`qbitflow-php/3.0.0`). */
	public const VERSION = '3.0.0';

	/** The API root used when no `baseUrl` is given. */
	public const DEFAULT_BASE_URL = 'https://api.qbitflow.app/v2';

	/** Seconds each HTTP attempt may take, by default. */
	public const DEFAULT_TIMEOUT = 30.0;

	/** Retries of a retryable call (a read, or one of the 7 idempotent creates), by default. */
	public const DEFAULT_MAX_RETRIES = 3;

	/** Manages the products. */
	public readonly ProductsService $products;

	/** Manages the customers. */
	public readonly CustomersService $customers;

	/** Creates checkout sessions and reads or expires them. */
	public readonly CheckoutSessionsService $checkoutSessions;

	/** Reads the one-time payments and the combined payment feed. */
	public readonly PaymentsService $payments;

	/** Reads the failed payment attempts. */
	public readonly FailuresService $failures;

	/** Reads, bills and cancels subscriptions. */
	public readonly SubscriptionsService $subscriptions;

	/** Lists and initiates refunds. */
	public readonly RefundsService $refunds;

	/** Manages the organization's members and their held funds (organization key). */
	public readonly MembersService $members;

	/** Invites members (organization key). */
	public readonly InvitationsService $invitations;

	/** Reads the wallets and the currencies they accept. */
	public readonly WalletsService $wallets;

	/** Exports the accounting events. */
	public readonly AccountingService $accounting;

	/** Verifies webhooks, and manages the endpoints (`->endpoints`) and the event log (`->events`). */
	public readonly WebhooksService $webhooks;

	/** Reads the currency catalog (public). */
	public readonly CurrenciesService $currencies;

	private readonly Requester $requester;

	/**
	 * @param string                       $apiKey         Your API key (sent as `X-API-Key`): non-blank, starting with `sk_`.
	 * @param string|null                  $baseUrl        The API root (default `https://api.qbitflow.app/v2`): an absolute http(s) URL; a trailing `/` is stripped.
	 * @param float|null                   $timeout        Seconds per HTTP attempt (default 30), applied to the HTTP client the SDK builds. A retried call may take longer in total.
	 * @param int|null                     $maxRetries     Retries of a retryable call (default 3); 0 disables them.
	 * @param string|null                  $onBehalfOf     Acts in a member's space on every request (`On-Behalf-Of`: the member's userUuid).
	 * @param ClientInterface|null         $httpClient     Your own PSR-18 client (proxies, instrumentation). It must not follow redirects, and its own timeout applies.
	 * @param RequestFactoryInterface|null $requestFactory Your own PSR-17 request factory.
	 * @param StreamFactoryInterface|null  $streamFactory  Your own PSR-17 stream factory.
	 *
	 * @throws ValidationException For a bad key or option. Nothing is sent: call {@see QBitFlow::me()} to check the key online.
	 */
	public function __construct(
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

		$base = self::DEFAULT_BASE_URL;
		if ($baseUrl !== null) {
			$base = rtrim(trim($baseUrl), '/');
			if (! Validator::isHttpUrl($base) || ! in_array(strtolower((string) parse_url($base, PHP_URL_SCHEME)), ['http', 'https'], true)) {
				throw Validator::fieldError('baseUrl', 'must be an absolute http or https URL');
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

	/**
	 * A client that acts in a member's space: every request sends `On-Behalf-Of: <userUuid>`
	 * (a member's userUuid; organization key only). It shares this client's transport and
	 * configuration; `''` returns one at the organization level. This client is unchanged.
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

	public function products(): ProductsService
	{
		return $this->products;
	}

	public function customers(): CustomersService
	{
		return $this->customers;
	}

	public function checkoutSessions(): CheckoutSessionsService
	{
		return $this->checkoutSessions;
	}

	public function payments(): PaymentsService
	{
		return $this->payments;
	}

	public function failures(): FailuresService
	{
		return $this->failures;
	}

	public function subscriptions(): SubscriptionsService
	{
		return $this->subscriptions;
	}

	public function refunds(): RefundsService
	{
		return $this->refunds;
	}

	public function members(): MembersService
	{
		return $this->members;
	}

	public function invitations(): InvitationsService
	{
		return $this->invitations;
	}

	public function wallets(): WalletsService
	{
		return $this->wallets;
	}

	public function accounting(): AccountingService
	{
		return $this->accounting;
	}

	public function webhooks(): WebhooksService
	{
		return $this->webhooks;
	}

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

	private function init(Requester $requester): void
	{
		$this->requester = $requester;
		$this->products = new ProductsService($requester);
		$this->customers = new CustomersService($requester);
		$this->checkoutSessions = new CheckoutSessionsService($requester);
		$this->payments = new PaymentsService($requester);
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
