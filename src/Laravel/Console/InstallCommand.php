<?php

declare(strict_types=1);

namespace QBitFlow\Laravel\Console;

use Illuminate\Console\Command;

/**
 * One-shot setup for a Laravel application: publish the config, make sure the environment
 * variables exist, and say what to do next.
 *
 * Nothing here is mandatory — the SDK works with just `QBITFLOW_API_KEY` in `.env` — but
 * it saves a new integrator from hunting through the README.
 */
final class InstallCommand extends Command
{
	protected $signature = 'qbitflow:install {--force : Overwrite the published config file if it already exists}';

	protected $description = 'Publish the QBitFlow config and prepare your environment';

	public function handle(): int
	{
		$this->publishConfig();
		$this->ensureEnvironmentKeys();
		$this->printNextSteps();

		return self::SUCCESS;
	}

	private function publishConfig(): void
	{
		$target = $this->laravel->configPath('qbitflow.php');

		if (file_exists($target) && ! $this->option('force')) {
			$this->line('Config already published at <comment>config/qbitflow.php</comment>, leaving it alone.');

			return;
		}

		$this->callSilently('vendor:publish', [
			'--tag' => 'qbitflow-config',
			'--force' => (bool) $this->option('force'),
		]);

		$this->info('Published config/qbitflow.php');
	}

	/**
	 * Append the QBitFlow keys to `.env` and `.env.example` when they are absent.
	 *
	 * An existing value is never touched — this only ever adds a missing placeholder.
	 */
	private function ensureEnvironmentKeys(): void
	{
		foreach (['.env', '.env.example'] as $file) {
			$path = $this->laravel->basePath($file);

			if (! is_file($path) || ! is_writable($path)) {
				continue;
			}

			$contents = (string) file_get_contents($path);

			if (str_contains($contents, 'QBITFLOW_API_KEY')) {
				$this->line("QBITFLOW_API_KEY already present in <comment>{$file}</comment>.");

				continue;
			}

			$block = PHP_EOL . 'QBITFLOW_API_KEY=' . PHP_EOL;

			file_put_contents($path, rtrim($contents, PHP_EOL) . PHP_EOL . $block);

			$this->info("Added QBITFLOW_API_KEY to {$file}");
		}
	}

	private function printNextSteps(): void
	{
		$this->newLine();
		$this->line('<options=bold>Next steps</>');
		$this->newLine();

		$this->line('  1. Put your API key in <comment>.env</comment>:');
		$this->line('     <fg=gray>QBITFLOW_API_KEY=your-api-key</>');
		$this->line('     A test key keeps everything on testnets, separate from live data.');
		$this->newLine();

		$this->line('  2. Check it works:');
		$this->line('     <fg=gray>php artisan qbitflow:verify</>');
		$this->newLine();

		$this->line('  3. To receive webhooks, add the routes to <comment>routes/api.php</comment>:');
		$this->line('     <fg=gray>Route::qbitflowTransactionWebhook(\'/webhooks/qbitflow/transaction\');</>');
		$this->line('     <fg=gray>Route::qbitflowSubscriptionWebhook(\'/webhooks/qbitflow/subscription\');</>');
		$this->line('     Then set those URLs in the dashboard under Settings → Webhooks, and listen');
		$this->line('     for TransactionWebhookReceived, SubscriptionBilled and SubscriptionStatusChanged.');
		$this->newLine();

		$this->line('  Docs: <comment>https://qbitflow.app/docs</comment>');
	}
}
