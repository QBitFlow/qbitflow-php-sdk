<?php

declare(strict_types=1);

namespace QBitFlow\Tests\Integration;

use GuzzleHttp\Client as GuzzleClient;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use QBitFlow\Dto\Customer;
use QBitFlow\Dto\Product;
use QBitFlow\Dto\User;
use QBitFlow\Enums\UserRole;
use QBitFlow\Exceptions\NotFoundException;
use QBitFlow\QBitFlow;
use QBitFlow\Support\CursorData;
use QBitFlow\Support\Enums;

/**
 * Opt-in smoke test against a running QBitFlow API.
 *
 * Both variables come from the environment (for this workspace, `sdk2/.local.env`); the
 * suite never falls back to a default server:
 *
 * - `QBITFLOW_API_KEY` not set → every test is skipped (offline runs stay green);
 * - `QBITFLOW_API_KEY` set but `QBITFLOW_BASE_URL` missing → every test FAILS, so a
 *   misconfigured run is never mistaken for a green one;
 * - an unreachable or unhealthy server → FAILS rather than skipping.
 *
 * ```bash
 * set -a; . ../.local.env; set +a
 * vendor/bin/phpunit --testsuite Integration
 * ```
 *
 * The provided key is live-scope against local chains, so nothing here creates anything:
 * a health check, a handful of reads, and one end-to-end signature rejection.
 */
#[Group('integration')]
final class LiveApiTest extends TestCase
{
	private QBitFlow $client;

	private string $baseUrl;

	protected function setUp(): void
	{
		parent::setUp();

		$apiKey = getenv('QBITFLOW_API_KEY');
		$baseUrl = getenv('QBITFLOW_BASE_URL');
		$hasKey = is_string($apiKey) && trim($apiKey) !== '';
		$hasBaseUrl = is_string($baseUrl) && trim($baseUrl) !== '';

		if (! $hasKey) {
			$this->markTestSkipped('QBITFLOW_API_KEY is not set; live tests are skipped.');
		}

		if (! $hasBaseUrl) {
			$this->fail('QBITFLOW_API_KEY is set but QBITFLOW_BASE_URL is not. Set QBITFLOW_BASE_URL (see sdk2/.local.env); '
				. 'the live suite never falls back to a default server.');
		}

		$this->baseUrl = rtrim(trim($baseUrl), '/');
		$healthPath = getenv('QBITFLOW_HEALTH_ENDPOINT');
		$healthPath = is_string($healthPath) && $healthPath !== '' ? $healthPath : '/healthz';

		// The health endpoint lives at the server root and needs no key.
		try {
			$health = (new GuzzleClient(['timeout' => 5, 'http_errors' => false]))
				->get($this->baseUrl . '/' . ltrim($healthPath, '/'));
		} catch (\Throwable $e) {
			$this->fail('QBitFlow API is not reachable at ' . $this->baseUrl . ': ' . $e->getMessage());
		}

		if ($health->getStatusCode() !== 200) {
			$this->fail('QBitFlow API health check returned ' . $health->getStatusCode());
		}

		$this->client = new QBitFlow($apiKey, $this->baseUrl, timeout: 10.0);
	}

	#[Test]
	public function the_key_identifies_a_user(): void
	{
		$user = $this->client->users->get();

		$this->assertInstanceOf(User::class, $user);
		$this->assertGreaterThan(0, $user->id);
		$this->assertNotSame('', $user->email);
		$this->assertContains(Enums::value($user->role), array_column(UserRole::cases(), 'value'));
	}

	#[Test]
	public function main_currencies_are_listed_publicly(): void
	{
		$currencies = $this->client->currencies->getAllMain();

		$this->assertNotEmpty($currencies);

		foreach ($currencies as $currency) {
			$this->assertNull($currency->mainCurrency, 'A main currency has no parent.');
			$this->assertFalse($currency->isToken());
		}
	}

	#[Test]
	public function products_and_customers_can_be_read(): void
	{
		foreach ($this->client->products->getAll() as $product) {
			$this->assertInstanceOf(Product::class, $product);
		}

		$page = $this->client->customers->getAll(limit: 5);

		$this->assertInstanceOf(CursorData::class, $page);
		$this->assertLessThanOrEqual(5, count($page));
		$this->assertContainsOnlyInstancesOf(Customer::class, $page->items);
	}

	#[Test]
	public function payments_carry_their_currency_object(): void
	{
		$page = $this->client->oneTimePayments->getAll(limit: 5);

		foreach ($page as $payment) {
			$this->assertSame($payment->currencyId, $payment->currency->id, 'The nested currency matches currencyId.');
			$this->assertNotSame('', $payment->currency->symbol);
		}

		foreach ($this->client->oneTimePayments->getAllCombined(limit: 5) as $entry) {
			$this->assertSame($entry->currencyId, $entry->currency->id);
		}
	}

	#[Test]
	public function an_unknown_product_is_a_not_found_error(): void
	{
		$this->expectException(NotFoundException::class);

		$this->client->products->get(2147483647);
	}

	#[Test]
	public function a_forged_webhook_signature_is_rejected_as_false_not_thrown(): void
	{
		// End to end: the API answers 400 for a signature mismatch, which verify() must
		// report as `false` — and only that status.
		$this->assertFalse(
			$this->client->webhooks->verify(['probe' => true], 'sha256=' . str_repeat('0', 64), (string) time()),
		);
	}
}
