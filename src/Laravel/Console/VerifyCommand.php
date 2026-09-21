<?php

declare(strict_types=1);

namespace QBitFlow\Laravel\Console;

use Illuminate\Console\Command;
use QBitFlow\Enums\UserRole;
use QBitFlow\Exceptions\NetworkException;
use QBitFlow\Exceptions\QBitFlowException;
use QBitFlow\Exceptions\UnauthorizedException;
use QBitFlow\Exceptions\ValidationException;
use QBitFlow\QBitFlow;

/**
 * Answers "is my QBitFlow setup actually working?" in one command.
 *
 * It calls `GET /user`, which is the cheapest authenticated endpoint, and reports who the
 * key belongs to. Useful after first install, after rotating a key, and in CI.
 */
final class VerifyCommand extends Command
{
	protected $signature = 'qbitflow:verify';

	protected $description = 'Check that the configured QBitFlow API key works';

	public function handle(): int
	{
		try {
			$client = $this->laravel->make(QBitFlow::class);
		} catch (ValidationException $e) {
			// Thrown by the service provider when no key is configured.
			$this->error($e->getMessage());

			return self::FAILURE;
		}

		$this->line('Endpoint: <comment>' . $client->getBaseUrl() . '</comment>');

		try {
			$user = $client->users->get();
		} catch (UnauthorizedException) {
			$this->error('The API key was rejected. Check QBITFLOW_API_KEY against your dashboard.');

			return self::FAILURE;
		} catch (NetworkException $e) {
			$this->error('Could not reach QBitFlow: ' . $e->getMessage());
			$this->line('The key may still be fine — check connectivity and any outbound proxy.');

			return self::FAILURE;
		} catch (QBitFlowException $e) {
			$this->error('QBitFlow returned an error: ' . $e->getMessage());

			return self::FAILURE;
		}

		$this->info('API key is valid.');
		$this->newLine();

		$this->table(['Field', 'Value'], [
			['User', trim($user->name . ' ' . $user->lastName)],
			['Email', $user->email],
			['User ID', (string) $user->id],
			['Role', $user->role->value],
			['Organization ID', (string) $user->organizationId],
			['Organization fee', sprintf('%d bps (%.2f%%)', $user->organizationFeeBps, $user->organizationFeeBps / 100)],
		]);

		if ($user->role === UserRole::USER) {
			$this->newLine();
			$this->warn('This is a user-level key, so onBehalfOf() will fail with a 403.');
			$this->line('Use an admin or owner key if you need to act for other users in your organization.');
		}

		return self::SUCCESS;
	}
}
