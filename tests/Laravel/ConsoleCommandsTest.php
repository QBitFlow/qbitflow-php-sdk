<?php

declare(strict_types=1);

namespace QBitFlow\Tests\Laravel;

use Illuminate\Config\Repository as ConfigRepository;
use Illuminate\Console\Application as Artisan;
use Illuminate\Events\Dispatcher;
use PHPUnit\Framework\Attributes\Test;
use QBitFlow\Exceptions\ValidationException;
use QBitFlow\Laravel\Console\VerifyCommand;
use QBitFlow\QBitFlow;
use QBitFlow\Tests\Support\FakeApplication;
use QBitFlow\Tests\Support\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Covers `php artisan qbitflow:verify`, the command an integrator runs to confirm their
 * key works. It resolves the client from the container, so the mock transport stands in.
 */
final class ConsoleCommandsTest extends TestCase
{
	private FakeApplication $app;

	protected function setUp(): void
	{
		parent::setUp();

		$this->app = new FakeApplication();
		FakeApplication::setInstance($this->app);
		$this->app->instance('config', new ConfigRepository());
		$this->app->instance('events', new Dispatcher($this->app));
	}

	protected function tearDown(): void
	{
		FakeApplication::setInstance(null);

		parent::tearDown();
	}

	private function tester(?QBitFlow $client = null): CommandTester
	{
		if ($client !== null) {
			$this->app->instance(QBitFlow::class, $client);
		}

		$command = new VerifyCommand();
		$command->setLaravel($this->app);
		$command->setApplication(new Artisan($this->app, $this->app->make('events'), '12.x'));

		return new CommandTester($command);
	}

	#[Test]
	public function it_reports_the_user_the_key_belongs_to(): void
	{
		$this->http->push([
			'id' => 7,
			'name' => 'Jane',
			'lastName' => 'Smith',
			'email' => 'jane@example.com',
			'role' => 'admin',
			'organizationId' => 3,
			'organizationFeeBps' => 150,
			'createdAt' => '2026-01-01T00:00:00Z',
			'updatedAt' => '2026-01-01T00:00:00Z',
		]);

		$tester = $this->tester($this->client());
		$exit = $tester->execute([]);
		$output = $tester->getDisplay();

		$this->assertSame(0, $exit);
		$this->assertStringContainsString('API key is valid', $output);
		$this->assertStringContainsString('Jane Smith', $output);
		$this->assertStringContainsString('jane@example.com', $output);
		$this->assertStringContainsString('admin', $output);
		$this->assertStringContainsString('150 bps (1.50%)', $output);
		$this->assertSame('/v1/user/', $this->http->lastPath());
	}

	#[Test]
	public function it_warns_that_a_user_level_key_cannot_act_on_behalf_of_others(): void
	{
		$this->http->push([
			'id' => 7, 'name' => 'Bob', 'lastName' => 'Jones', 'email' => 'b@e.com',
			'role' => 'user', 'organizationId' => 3, 'organizationFeeBps' => 0,
			'createdAt' => '2026-01-01T00:00:00Z', 'updatedAt' => '2026-01-01T00:00:00Z',
		]);

		$tester = $this->tester($this->client());
		$tester->execute([]);

		$this->assertStringContainsString('onBehalfOf() will fail with a 403', $tester->getDisplay());
	}

	#[Test]
	public function it_says_plainly_when_the_key_is_rejected(): void
	{
		$this->http->push(['error' => 'Invalid API key'], 401);

		$tester = $this->tester($this->client());
		$exit = $tester->execute([]);

		$this->assertSame(1, $exit);
		$this->assertStringContainsString('QBITFLOW_API_KEY', $tester->getDisplay());
	}

	#[Test]
	public function it_distinguishes_an_unreachable_api_from_a_bad_key(): void
	{
		$this->http->pushFailure('Connection refused');

		$tester = $this->tester($this->client());
		$exit = $tester->execute([]);
		$output = $tester->getDisplay();

		$this->assertSame(1, $exit);
		$this->assertStringContainsString('Could not reach QBitFlow', $output);
		$this->assertStringContainsString('The key may still be fine', $output);
	}

	#[Test]
	public function it_explains_a_missing_api_key_instead_of_crashing(): void
	{
		// Mirrors the service provider refusing to build a client with no key configured.
		$this->app->bind(QBitFlow::class, static function (): QBitFlow {
			throw new ValidationException(
				'QBitFlow API key is not configured. Set QBITFLOW_API_KEY in your .env file.',
			);
		});

		$tester = $this->tester();
		$exit = $tester->execute([]);

		$this->assertSame(1, $exit);
		$this->assertStringContainsString('Set QBITFLOW_API_KEY in your .env file', $tester->getDisplay());
	}
}
