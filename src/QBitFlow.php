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
 *     customerUUID: 'customer-uuid',
 * ));
 *
 * return redirect($payment->link);
 * ```
 *
 * @see https://qbitflow.app/docs/api The REST API reference.
 */
final class QBitFlow
{
	/** Version of this SDK. */
	public const VERSION = '2.0.0';

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

	// Pay-as-you-go subscriptions are disabled on the API and are not exposed here.
	// They will return once the new PAYG infrastructure ships.

	/**
	 * @param string    $apiKey     Your QBitFlow API key, from the dashboard. A test key keeps
	 *                              every action on testnets, with data fully separate from live.
	 * @param string|null $baseUrl  API base URL. Rarely worth changing; defaults to the value of
	 *                              `QBITFLOW_BASE_URL` or the production endpoint.
	 * @param float|null  $timeout  Request timeout in **seconds** (default 30). Note the
	 *                              JavaScript SDK uses milliseconds here; PHP clients use seconds.
	 * @param int|null    $maxRetries Retry attempts for server and network failures (default 3).
	 * @param ClientInterface|null $httpClient Your own PSR-18 client. When given, the SDK does not
	 *                              apply `$timeout` — configure it on your client instead.
	 *
	 * @throws ValidationException If the API key is empty.
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

		$this->transport = new Transport(
			$apiKey,
			$baseUrl !== null ? rtrim($baseUrl, '/') : Config::baseUrl(),
			$timeout ?? (float) Config::DEFAULT_TIMEOUT,
			$maxRetries ?? Config::DEFAULT_MAX_RETRIES,
			httpClient: $httpClient,
			requestFactory: $requestFactory,
			streamFactory: $streamFactory,
		);

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
	 * } $config
	 */
	public static function fromArray(array $config): self
	{
		return new self(
			(string) ($config['apiKey'] ?? ''),
			isset($config['baseUrl']) ? (string) $config['baseUrl'] : null,
			isset($config['timeout']) ? (float) $config['timeout'] : null,
			isset($config['maxRetries']) ? (int) $config['maxRetries'] : null,
			$config['httpClient'] ?? null,
		);
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
