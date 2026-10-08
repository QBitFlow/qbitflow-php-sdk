<?php

declare(strict_types=1);

namespace QBitFlow\Requests;

use QBitFlow\Dto\AccountingEvent;
use QBitFlow\Enums\ExportFormat;
use QBitFlow\Exceptions\ValidationException;
use QBitFlow\Support\Cast;

/**
 * Export payment data for bookkeeping.
 */
final class AccountingRequests extends Request
{
	private const BASE_ROUTE = '/accounting';

	/**
	 * Export accounting data for a date range.
	 *
	 * Returns a list of {@see AccountingEvent} for {@see ExportFormat::JSON}, or the raw
	 * CSV document as a string for {@see ExportFormat::CSV}. Prefer
	 * {@see AccountingRequests::exportJson()} or {@see AccountingRequests::exportCsv()}
	 * when you want a single, definite return type.
	 *
	 * @param string $from Start date, inclusive, as `YYYY-MM-DD`.
	 * @param string $to   End date, inclusive, as `YYYY-MM-DD`.
	 *
	 * @return list<AccountingEvent>|string
	 *
	 * @throws ValidationException If either date is missing.
	 */
	public function export(string $from, string $to, ExportFormat|string $format): array|string
	{
		$format = is_string($format)
			? (ExportFormat::tryFrom($format) ?? throw new ValidationException(
				sprintf('Invalid export format "%s". Expected "json" or "csv".', $format),
			))
			: $format;

		if (trim($from) === '' || trim($to) === '') {
			throw new ValidationException('from and to dates are required (YYYY-MM-DD)');
		}

		$params = ['from' => $from, 'to' => $to, 'format' => $format->value];

		if ($format === ExportFormat::CSV) {
			return $this->transport->raw('GET', self::BASE_ROUTE . '/export', $params);
		}

		return Cast::listOf(
			$this->transport->get(self::BASE_ROUTE . '/export', $params),
			AccountingEvent::fromArray(...),
		);
	}

	/**
	 * Export accounting data as typed events.
	 *
	 * ```php
	 * $events = $client->accounting->exportJson('2026-01-01', '2026-01-31');
	 *
	 * $total = array_sum(array_map(fn ($e) => $e->netAmountUsd, $events));
	 * ```
	 *
	 * @return list<AccountingEvent>
	 */
	public function exportJson(string $from, string $to): array
	{
		/** @var list<AccountingEvent> */
		return $this->export($from, $to, ExportFormat::JSON);
	}

	/**
	 * Export accounting data as a raw CSV document.
	 *
	 * ```php
	 * file_put_contents('export.csv', $client->accounting->exportCsv('2026-01-01', '2026-01-31'));
	 * ```
	 */
	public function exportCsv(string $from, string $to): string
	{
		/** @var string */
		return $this->export($from, $to, ExportFormat::CSV);
	}
}
