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
	/**
	 * The most days one export request covers, both ends included: `to` at most
	 * `MAX_WINDOW_DAYS - 1` days after `from`.
	 */
	public const MAX_WINDOW_DAYS = 92;

	/**
	 * The accounting events between two dates, both `YYYY-MM-DD` and included
	 * (`GET /accounting/export?format=json`; a bounded list). The dates are the transactions'
	 * (their block's day, in UTC), so consecutive exports never miss nor repeat an event. Events
	 * are ordered by `txTimeUtc`, then `type`, then `txUuid`. The API allows at most 92 days, both
	 * included (400 validation_failed on `to` beyond, `details.max` 92: {@see exportJsonRange()}
	 * splits longer ranges); the SDK checks the dates and `from <= to` before sending.
	 *
	 * @return list<AccountingEvent>
	 */
	public function exportJson(string $from, string $to, ?RequestOptions $options = null): array
	{
		return $this->requester->items('/accounting/export', AccountingEvent::fromArray(...), self::query($from, $to, 'json'), $options);
	}

	/**
	 * The same export as CSV text (`GET /accounting/export?format=csv`): the header row
	 * ({@see AccountingEvent}'s JSON keys, in its property order), then one row per event; nothing
	 * in range gives the header only. Amounts are plain numbers; an absent value is an empty
	 * cell; a cell starting with `= + - @`, a tab or a CR gets a leading `'`. Read the columns by
	 * header name, not position. Errors are still JSON, and typed as usual.
	 */
	public function exportCsv(string $from, string $to, ?RequestOptions $options = null): string
	{
		return $this->requester->text('/accounting/export', self::query($from, $to, 'csv'), $options);
	}

	/**
	 * The accounting events between two dates (`YYYY-MM-DD`, both included) over any range:
	 * split into consecutive windows of at most 92 days, both ends included (`[from, from+91d]`,
	 * the next starting the day after; on calendar dates, UTC), requested in order and
	 * concatenated. A range of 92 days or less is one request; 2026-01-01 to 2026-12-31 is four:
	 * `[01-01, 04-02] [04-03, 07-03] [07-04, 10-03] [10-04, 12-31]`. Checked like
	 * {@see exportJson()} before sending; the first error stops it.
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
			$windowEnd = $start->modify('+' . (self::MAX_WINDOW_DAYS - 1) . ' days');
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
	 * @return array<string,string|list<string>>
	 */
	private static function query(string $from, string $to, string $format): array
	{
		$v = new Validator();
		$v->dateRange('from', $from, 'to', $to);
		$v->throwIfAny();

		return ['from' => $from, 'to' => $to, 'format' => $format];
	}
}
