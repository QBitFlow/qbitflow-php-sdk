<?php

declare(strict_types=1);

namespace QBitFlow\Services;

use DateTimeImmutable;
use DateTimeZone;
use QBitFlow\Http\Requester;
use QBitFlow\Models\AccountingEvent;
use QBitFlow\RequestOptions;
use QBitFlow\Support\Validator;

/**
 * Exports the accounting events (`/accounting/export`).
 */
final class AccountingService extends Service
{
	/** The longest export window the API accepts: `to` at most this many days after `from`. */
	public const MAX_WINDOW_DAYS = 95;

	/**
	 * The accounting events between two dates, both `YYYY-MM-DD` and included
	 * (`GET /accounting/export?format=json`). The API allows at most 95 days (400 beyond); the
	 * SDK checks the dates and `from <= to` before sending. Longer ranges: {@see exportJsonRange()}.
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
	 * The accounting events between two dates (`YYYY-MM-DD`, both included) over any range:
	 * split into consecutive windows of at most 95 days (`[from, from+95d]`, the next starting
	 * the day after), requested in order and concatenated. A range of 95 days or less is one
	 * request. Checked like {@see exportJson()} before sending.
	 *
	 * @return list<AccountingEvent>
	 */
	public function exportJsonRange(string $from, string $to, ?RequestOptions $options = null): array
	{
		$events = [];
		foreach (self::windows($from, $to) as [$start, $end]) {
			array_push($events, ...$this->exportJson($start, $end, $options));
		}

		return $events;
	}

	/**
	 * {@see exportJsonRange()} as CSV: the first window's header line once, then every window's
	 * data rows (a window with only a header adds nothing), line endings as the server sends them.
	 */
	public function exportCsvRange(string $from, string $to, ?RequestOptions $options = null): string
	{
		$csv = '';
		$headerSeen = false;
		foreach (self::windows($from, $to) as [$start, $end]) {
			$text = $this->exportCsv($start, $end, $options);
			if (! $headerSeen) {
				if ($text !== '') {
					$csv = $text;
					$headerSeen = true;
				}
				continue;
			}
			$newline = strpos($text, "\n");
			$rows = $newline === false ? '' : substr($text, $newline + 1);
			if ($rows === '') {
				continue;
			}
			if (! str_ends_with($csv, "\n")) {
				// The previous window ended without a line break: use the header's.
				$csv .= str_contains($csv, "\r\n") ? "\r\n" : "\n";
			}
			$csv .= $rows;
		}

		return $csv;
	}

	/**
	 * Splits a checked date range into the export windows (calendar dates, UTC).
	 *
	 * @return list<array{0: string, 1: string}>
	 */
	private static function windows(string $from, string $to): array
	{
		self::query($from, $to, 'json'); // the same checks as one export
		$utc = new DateTimeZone('UTC');
		$end = new DateTimeImmutable($to, $utc);
		$start = new DateTimeImmutable($from, $utc);
		$windows = [];
		while ($start <= $end) {
			$windowEnd = $start->modify('+' . self::MAX_WINDOW_DAYS . ' days');
			if ($windowEnd > $end) {
				$windowEnd = $end;
			}
			$windows[] = [$start->format('Y-m-d'), $windowEnd->format('Y-m-d')];
			$start = $windowEnd->modify('+1 day');
		}

		return $windows;
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
