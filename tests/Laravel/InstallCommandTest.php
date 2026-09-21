<?php

declare(strict_types=1);

namespace QBitFlow\Tests\Laravel;

use Illuminate\Console\Application as Artisan;
use Illuminate\Console\Command;
use Illuminate\Events\Dispatcher;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use QBitFlow\Laravel\Console\InstallCommand;
use QBitFlow\Tests\Support\FakeApplication;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Covers `php artisan qbitflow:install`, which touches the application's own files — so
 * the important guarantee is that it never destroys anything already there.
 */
final class InstallCommandTest extends TestCase
{
	private FakeApplication $app;

	private string $base;

	protected function setUp(): void
	{
		parent::setUp();

		$this->base = sys_get_temp_dir() . '/qbitflow-install-' . bin2hex(random_bytes(4));
		mkdir($this->base . '/config', 0o777, true);

		$this->app = new FakeApplication($this->base);
		FakeApplication::setInstance($this->app);
		$this->app->instance('events', new Dispatcher($this->app));
	}

	protected function tearDown(): void
	{
		FakeApplication::setInstance(null);

		foreach (['/config/qbitflow.php', '/.env', '/.env.example'] as $file) {
			@unlink($this->base . $file);
		}
		@rmdir($this->base . '/config');
		@rmdir($this->base);

		parent::tearDown();
	}

	private function runInstall(array $input = []): CommandTester
	{
		$artisan = new Artisan($this->app, $this->app->make('events'), '12.x');
		// Stand in for the framework's publisher, which is not installed here.
		$artisan->add(new class extends Command {
			protected $signature = 'vendor:publish {--tag=} {--force}';

			public function handle(): int
			{
				return self::SUCCESS;
			}
		});

		$command = new InstallCommand();
		$command->setLaravel($this->app);
		$command->setApplication($artisan);

		$tester = new CommandTester($command);
		$tester->execute($input);

		return $tester;
	}

	#[Test]
	public function it_adds_the_api_key_placeholder_to_env_files(): void
	{
		file_put_contents($this->base . '/.env', "APP_NAME=Example\n");
		file_put_contents($this->base . '/.env.example', "APP_NAME=Example\n");

		$tester = $this->runInstall();

		$this->assertSame(0, $tester->getStatusCode());

		foreach (['/.env', '/.env.example'] as $file) {
			$contents = (string) file_get_contents($this->base . $file);

			$this->assertStringContainsString('APP_NAME=Example', $contents, 'Existing content survives.');
			$this->assertStringContainsString('QBITFLOW_API_KEY=', $contents);
		}
	}

	#[Test]
	public function it_never_overwrites_a_key_that_is_already_set(): void
	{
		// The command must be safe to re-run on a configured application.
		file_put_contents($this->base . '/.env', "APP_NAME=Example\nQBITFLOW_API_KEY=sk_live_existing\n");

		$this->runInstall();

		$contents = (string) file_get_contents($this->base . '/.env');

		$this->assertStringContainsString('QBITFLOW_API_KEY=sk_live_existing', $contents);
		$this->assertSame(1, substr_count($contents, 'QBITFLOW_API_KEY'), 'The key is not duplicated.');
	}

	#[Test]
	public function it_is_idempotent(): void
	{
		file_put_contents($this->base . '/.env', "APP_NAME=Example\n");

		$this->runInstall();
		$first = (string) file_get_contents($this->base . '/.env');

		$this->runInstall();
		$second = (string) file_get_contents($this->base . '/.env');

		$this->assertSame($first, $second, 'Running twice changes nothing the second time.');
	}

	#[Test]
	public function it_copes_with_an_application_that_has_no_env_file(): void
	{
		$tester = $this->runInstall();

		$this->assertSame(0, $tester->getStatusCode());
		$this->assertFileDoesNotExist($this->base . '/.env', 'No .env is created out of nowhere.');
	}

	#[Test]
	public function it_leaves_an_existing_published_config_alone(): void
	{
		file_put_contents($this->base . '/config/qbitflow.php', '<?php return ["api_key" => "mine"];');

		$tester = $this->runInstall();
		$output = $tester->getDisplay();

		$this->assertStringContainsString('leaving it alone', $output);
		$this->assertStringContainsString('"mine"', (string) file_get_contents($this->base . '/config/qbitflow.php'));
	}

	#[Test]
	public function it_tells_the_integrator_what_to_do_next(): void
	{
		$output = $this->runInstall()->getDisplay();

		$this->assertStringContainsString('QBITFLOW_API_KEY=your-api-key', $output);
		$this->assertStringContainsString('qbitflow:verify', $output);
		$this->assertStringContainsString('qbitflowTransactionWebhook', $output);
	}
}
