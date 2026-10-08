<?php

declare(strict_types=1);

namespace QBitFlow\Laravel\Console;

use Illuminate\Console\Command;
use QBitFlow\Enums\Role;
use QBitFlow\Exceptions\AuthenticationException;
use QBitFlow\Exceptions\NetworkException;
use QBitFlow\Exceptions\QBitFlowException;
use QBitFlow\Exceptions\ValidationException;
use QBitFlow\QBitFlow;

/**
 * `php artisan qbitflow:verify`: checks the configured API key with `GET /me` and says what it
 * is (role, organization, space, mode). Useful after installing, after rotating a key, and in CI.
 */
final class VerifyCommand extends Command
{
	protected $signature = 'qbitflow:verify';

	protected $description = 'Check that the configured QBitFlow API key works, and show what it is';

	public function handle(): int
	{
		try {
			$client = $this->laravel->make(QBitFlow::class);
		} catch (ValidationException $e) {
			$this->error($e->getErrorMessage());

			return self::FAILURE;
		}

		$this->line('Endpoint: <comment>' . $client->getBaseUrl() . '</comment>');

		try {
			$me = $client->me();
		} catch (AuthenticationException) {
			$this->error('The API key was rejected. Check QBITFLOW_API_KEY against your dashboard.');

			return self::FAILURE;
		} catch (NetworkException $e) {
			$this->error('Could not reach QBitFlow: ' . $e->getMessage());
			$this->line('The key may still be fine: check the connectivity and any outbound proxy.');

			return self::FAILURE;
		} catch (QBitFlowException $e) {
			$this->error('QBitFlow returned an error: ' . $e->getMessage());

			return self::FAILURE;
		}

		$this->info('API key is valid.');
		$this->newLine();

		$space = $me->space;
		$this->table(['Field', 'Value'], [
			['Credential', $me->credential],
			['Role', $me->role ?? ''],
			['Organization', $space?->organizationName ?? ''],
			['Space', $space?->uuid ?? ''],
			['Mode', $space === null ? '' : ($space->test ? 'test' : 'live')],
			['Member', $space?->member !== null ? trim($space->member->name . ' ' . $space->member->lastName) . ' (' . $space->member->userUuid . ')' : '—'],
		]);

		if ($me->role === Role::USER) {
			$this->newLine();
			$this->warn('This is a member\'s key: onBehalfOf() and the members and invitations services need an organization key.');
		}

		return self::SUCCESS;
	}
}
