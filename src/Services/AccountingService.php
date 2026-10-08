<?php

declare(strict_types=1);

namespace QBitFlow\Services;

use QBitFlow\Http\Requester;
use QBitFlow\Models\AccountingEvent;
use QBitFlow\RequestOptions;
use QBitFlow\Support\Validator;

/**
 * Exports the accounting events (`/accounting/export`).
 */
final class AccountingService extends Service
{
	/**
	 * The accounting events between two dates, both `YYYY-MM-DD` and included
	 * (`GET /accounting/export?format=json`). The API allows at most 95 days (400 beyond); the
	 * SDK checks the dates and `from <= to` before sending.
	 *
	 * @return list<AccountingEvent>
	 */
	public function exportJson(string $from, string $to, ?RequestOptions $options = null): array
	{
		return $this->requester->call('GET', '/accounting/export', Requester::list(AccountingEvent::fromArray(...)), self::query($from, $to, 'json'), options: $options);
	}

	/**
	 * The same export as CSV text (`GET /accounting/export?format=csv`). Errors are still JSON,
	 * and typed as usual.
	 */
	public function exportCsv(string $from, string $to, ?RequestOptions $options = null): string
	{
		return $this->requester->text('/accounting/export', self::query($from, $to, 'csv'), $options);
	}

	/**
	 * Checks an export window and encodes it with the format.
	 *
	 * @return array<string,string>
	 */
	private static function query(string $from, string $to, string $format): array
	{
		$v = new Validator();
		$v->dateRange('from', $from, 'to', $to);
		$v->throwIfAny();

		return ['from' => $from, 'to' => $to, 'format' => $format];
	}
}
