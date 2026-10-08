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
use QBitFlow\Tests\Support\MockHttpClient;
use QBitFlow\Tests\Support\MockNetworkException;
use QBitFlow\Tests\Support\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * `php artisan qbitflow:verify`: checks the key with `me()`.
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
	public function it_reports_what_the_key_is(): void
	{
		$client = $this->client(MockHttpClient::static(200, '{"credential":"apiKey","role":"admin","space":{"uuid":"s-1","organizationUuid":"o-1","organizationName":"Example Shop","test":true}}'));
		$tester = $this->tester($client);
		$this->assertSame(0, $tester->execute([]));
		$output = $tester->getDisplay();
		foreach (['API key is valid', 'Example Shop', 'admin', 'test', 's-1'] as $text) {
			$this->assertStringContainsString($text, $output);
		}
		$this->assertSame('/me', self::pathOf($this->http->last()));
	}

	#[Test]
	public function it_warns_that_a_member_key_cannot_act_for_others(): void
	{
		$client = $this->client(MockHttpClient::static(200, '{"credential":"apiKey","role":"user","space":{"uuid":"s-2","userUuid":"m-1","member":{"userUuid":"m-1","name":"Ada","lastName":"L"},"test":false}}'));
		$tester = $this->tester($client);
		$tester->execute([]);
		$this->assertStringContainsString('organization key', $tester->getDisplay());
		$this->assertStringContainsString('live', $tester->getDisplay());
		$this->assertStringContainsString('Ada L', $tester->getDisplay());
	}

	#[Test]
	public function it_says_plainly_when_the_key_is_rejected(): void
	{
		$tester = $this->tester($this->client(MockHttpClient::static(401, '{"error":"invalid API key","code":"unauthorized"}')));
		$this->assertSame(1, $tester->execute([]));
		$this->assertStringContainsString('QBITFLOW_API_KEY', $tester->getDisplay());
	}

	#[Test]
	public function it_distinguishes_an_unreachable_api_from_a_bad_key(): void
	{
		$tester = $this->tester($this->client(new MockHttpClient(static fn () => new MockNetworkException('Connection refused')), 0));
		$this->assertSame(1, $tester->execute([]));
		$this->assertStringContainsString('Could not reach QBitFlow', $tester->getDisplay());
	}

	#[Test]
	public function it_explains_a_missing_api_key(): void
	{
		$this->app->bind(QBitFlow::class, static function (): QBitFlow {
			throw new ValidationException('QBitFlow API key is not configured. Set QBITFLOW_API_KEY in your .env file.');
		});
		$tester = $this->tester();
		$this->assertSame(1, $tester->execute([]));
		$this->assertStringContainsString('Set QBITFLOW_API_KEY in your .env file', $tester->getDisplay());
	}
}
