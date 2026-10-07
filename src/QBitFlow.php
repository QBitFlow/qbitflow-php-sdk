<?php

declare(strict_types=1);

namespace QBitFlow;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use QBitFlow\Exceptions\ValidationException;
use QBitFlow\Http\Transport;
use QBitFlow\Requests\AccountingRequests;
use QBitFlow\Requests\ApiKeyRequests;
use QBitFlow\Requests\ClaimRequests;
use QBitFlow\Requests\CurrencyRequests;
use QBitFlow\Requests\CustomerRequests;
use QBitFlow\Requests\PaymentRequests;
use QBitFlow\Requests\ProductRequests;
use QBitFlow\Requests\RefundRequests;
use QBitFlow\Requests\SubscriptionRequests;
use QBitFlow\Requests\TransactionStatusRequests;
use QBitFlow\Requests\UserRequests;
use QBitFlow\Requests\WebhookRequests;

/**
 * The QBitFlow API client.
 *
 * Every area of the API hangs off this object as a service. Each service is reachable
 * both as a property and as a method — the property reads best in application code, the
 * method is what the Laravel facade forwards to:
 *
 * ```php
 * $client = new QBitFlow('your-api-key');
 *
 * $client->products->getAll();     // direct
 * QBitFlow::products()->getAll();  // through the facade
 * ```
 *
 * Creating a payment link:
 *
 * ```php
 * $payment = $client->oneTimePayments->createSession(new CreatePaymentSessionDto(
 *     productId: 1,
 *     successUrl: 'https://example.com/success',
 * ));
 *
 * return redirect($payment->link);
 * ```
 *
 * Acting for one of your users (organization admin/owner keys):
 *
 * ```php
 * $asUser = $client->onBehalfOf(123);   // every service on $asUser sends On-Behalf-Of: 123
 * $asUser->products->getAll();
 * ```
 *
 * @see https://qbitflow.app/docs/api The REST API reference.
 */
final class QBitFlow
{
	/** Version of this SDK. */
	public const VERSION = '2.5.0';

	private readonly Transport $transport;

	/** Customers who pay through your platform. */
	public readonly CustomerRequests $customers;

	/** Products customers buy or subscribe to. */
	public readonly ProductRequests $products;

	/** Users in your organization. */
	public readonly UserRequests $users;

	/** Read access to API keys. */
	public readonly ApiKeyRequests $apiKeys;

	/** Webhook verification. */
	public readonly WebhookRequests $webhooks;

	/** One-time payments. */
	public readonly PaymentRequests $oneTimePayments;

	/** Recurring subscriptions. */
	public readonly SubscriptionRequests $subscriptions;

	/** Transaction status lookups. */
	public readonly TransactionStatusRequests $transactionStatus;

	/** Refunds raised against your transactions. */
	public readonly RefundRequests $refunds;

	/** Accounting exports. */
	public readonly AccountingRequests $accounting;

	/** Account claims and fund transfers. */
	public readonly ClaimRequests $claims;

	/** Supported-currency lookups. */
	public readonly CurrencyRequests $currencies;

	/**
	 * @param string    $apiKey     Your QBitFlow API key, from the dashboard. A test key keeps
	 *                              every action on testnets, with data fully separate from live.
	 * @param string|null $baseUrl  API base URL. Rarely worth changing; defaults to the value of
	 *                              `QBITFLOW_BASE_URL` or the production endpoint.
	 * @param float|null  $timeout  Request timeout in **seconds** (default 30). Note the
	 *                              JavaScript SDK uses milliseconds here; PHP clients use seconds.
	 * @param int|null    $maxRetries Retry attempts for GET requests that hit a server or
	 *                              network failure (default 3; `0` disables retries).
	 * @param ClientInterface|null $httpClient Your own PSR-18 client. When given, the SDK does not
	 *                              apply `$timeout` — configure it on your client instead.
	 *
	 * @throws ValidationException If the API key is empty or only whitespace, or `$maxRetries` is negative.
	 */
	public function __construct(
		string $apiKey,
		?string $baseUrl = null,
		?float $timeout = null,
		?int $maxRetries = null,
		?ClientInterface $httpClient = null,
		?RequestFactoryInterface $requestFactory = null,
		?StreamFactoryInterface $streamFactory = null,
	) {
		if (trim($apiKey) === '') {
			throw new ValidationException('API key is required');
		}

		$baseUrl = $baseUrl === null ? '' : rtrim(trim($baseUrl), '/');

		$this->init(new Transport(
			$apiKey,
			$baseUrl !== '' ? $baseUrl : Config::baseUrl(),
			$timeout ?? (float) Config::DEFAULT_TIMEOUT,
			$maxRetries ?? Config::DEFAULT_MAX_RETRIES,
			httpClient: $httpClient,
			requestFactory: $requestFactory,
			streamFactory: $streamFactory,
		));
	}

	/**
	 * Wire every service to one transport.
	 */
	private function init(Transport $transport): void
	{
		$this->transport = $transport;
		$this->customers = new CustomerRequests($this->transport);
		$this->products = new ProductRequests($this->transport);
		$this->users = new UserRequests($this->transport);
		$this->apiKeys = new ApiKeyRequests($this->transport);
		$this->webhooks = new WebhookRequests($this->transport);
		$this->oneTimePayments = new PaymentRequests($this->transport);
		$this->subscriptions = new SubscriptionRequests($this->transport);
		$this->transactionStatus = new TransactionStatusRequests($this->transport);
		$this->refunds = new RefundRequests($this->transport);
		$this->accounting = new AccountingRequests($this->transport);
		$this->claims = new ClaimRequests($this->transport);
		$this->currencies = new CurrencyRequests($this->transport);
	}

	/**
	 * Build a client from an array of options.
	 *
	 * Mirrors the object form the JavaScript SDK accepts, and is what the Laravel service
	 * provider uses to build the client from config.
	 *
	 * @param array{
	 *     apiKey?: string,
	 *     baseUrl?: string|null,
	 *     timeout?: float|int|null,
	 *     maxRetries?: int|null,
	 *     httpClient?: ClientInterface|null,
	 *     requestFactory?: RequestFactoryInterface|null,
	 *     streamFactory?: StreamFactoryInterface|null,
	 * } $config An empty `baseUrl` means "use the default".
	 */
	public static function fromArray(array $config): self
	{
		return new self(
			(string) ($config['apiKey'] ?? ''),
			isset($config['baseUrl']) ? (string) $config['baseUrl'] : null,
			isset($config['timeout']) ? (float) $config['timeout'] : null,
			isset($config['maxRetries']) ? (int) $config['maxRetries'] : null,
			$config['httpClient'] ?? null,
			$config['requestFactory'] ?? null,
			$config['streamFactory'] ?? null,
		);
	}

	/**
	 * A copy of this client whose every service acts on behalf of one of your users.
	 *
	 * Every request made through the returned client sends `On-Behalf-Of: <userId>`, so it
	 * reads and writes that user's resources with that user's role. The copy shares this
	 * client's configuration and HTTP client; this client is left untouched. Passing `0`
	 * returns an organization-level copy (the header is omitted).
	 *
	 * ```php
	 * $vendor = $client->onBehalfOf(123);
	 *
	 * $vendor->products->getAll();                  // user 123's products
	 * $vendor->oneTimePayments->createSession(...); // credited to user 123
	 * $client->products->getAll();                  // still organization level
	 * ```
	 *
	 * Requires an organization-level admin or owner API key. A user outside your
	 * organization gets a 404 ({@see \QBitFlow\Exceptions\NotFoundException}). Each service
	 * also has its own `onBehalfOf()`.
	 *
	 * @param int $userId ID of the user to act as, or 0 for the organization itself.
	 *
	 * @throws ValidationException If the ID is negative.
	 */
	public function onBehalfOf(int $userId): static
	{
		if ($userId < 0) {
			throw new ValidationException('User ID must be zero or positive');
		}

		$copy = (new \ReflectionClass(static::class))->newInstanceWithoutConstructor();
		$copy->init($userId === 0
			? $this->transport->withoutHeader(Transport::ON_BEHALF_OF)
			: $this->transport->withHeader(Transport::ON_BEHALF_OF, (string) $userId));

		return $copy;
	}

	public function getApiKey(): string
	{
		return $this->transport->getApiKey();
	}

	public function getBaseUrl(): string
	{
		return $this->transport->getBaseUrl();
	}

	/** Customers who pay through your platform. */
	public function customers(): CustomerRequests
	{
		return $this->customers;
	}

	/** Products customers buy or subscribe to. */
	public function products(): ProductRequests
	{
		return $this->products;
	}

	/** Users in your organization. */
	public function users(): UserRequests
	{
		return $this->users;
	}

	/** Read access to API keys. */
	public function apiKeys(): ApiKeyRequests
	{
		return $this->apiKeys;
	}

	/** Webhook verification. */
	public function webhooks(): WebhookRequests
	{
		return $this->webhooks;
	}

	/** One-time payments. */
	public function oneTimePayments(): PaymentRequests
	{
		return $this->oneTimePayments;
	}

	/** Recurring subscriptions. */
	public function subscriptions(): SubscriptionRequests
	{
		return $this->subscriptions;
	}

	/** Transaction status lookups. */
	public function transactionStatus(): TransactionStatusRequests
	{
		return $this->transactionStatus;
	}

	/** Refunds raised against your transactions. */
	public function refunds(): RefundRequests
	{
		return $this->refunds;
	}

	/** Accounting exports. */
	public function accounting(): AccountingRequests
	{
		return $this->accounting;
	}

	/** Account claims and fund transfers. */
	public function claims(): ClaimRequests
	{
		return $this->claims;
	}

	/** Supported-currency lookups. */
	public function currencies(): CurrencyRequests
	{
		return $this->currencies;
	}

	/**
	 * Never expose the API key through debug output.
	 *
	 * @return array<string,string>
	 */
	public function __debugInfo(): array
	{
		$key = $this->transport->getApiKey();

		return [
			'apiKey' => '***' . substr($key, -4),
			'baseUrl' => $this->transport->getBaseUrl(),
		];
	}
}
