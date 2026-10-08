<?php

declare(strict_types=1);

namespace QBitFlow\Support;

use QBitFlow\Exceptions\ValidationException;

/**
 * Exact conversions between a token's min units (the integer strings the API sends:
 * `allowance`, `maxAmountPerPeriod`, `amountsMinUnits`…) and decimal amounts, by string
 * arithmetic: never through floats, and without any PHP extension.
 *
 * ```php
 * Amount::format('1500000', 6);   // "1.5"
 * Amount::parse('1.5', 6);        // "1500000"
 * $currency->formatAmount($subscription->allowance);
 * ```
 */
final class Amount
{
	/** The most decimals accepted (a uint256's digits). */
	public const MAX_DECIMALS = 77;

	private function __construct()
	{
	}

	/**
	 * Min units → decimal amount: `"1500000", 6` → `"1.5"`. Trailing zeros of the fraction and
	 * leading zeros are trimmed, never an exponent; `"-0"` is `"0"`.
	 *
	 * @param string $minUnits An integer (`-?[0-9]+`).
	 * @param int    $decimals The currency's decimals (0 to 77).
	 *
	 * @throws ValidationException For any other input.
	 */
	public static function format(string $minUnits, int $decimals): string
	{
		self::checkDecimals($decimals);
		if (preg_match('/^(-?)([0-9]+)$/D', $minUnits, $m) !== 1) {
			throw Validator::fieldError('minUnits', 'must be an integer (digits, optionally after a "-")');
		}
		$digits = ltrim($m[2], '0');
		if ($digits === '') {
			return '0';
		}
		$digits = str_pad($digits, $decimals + 1, '0', STR_PAD_LEFT);
		$whole = substr($digits, 0, strlen($digits) - $decimals);
		$fraction = rtrim(substr($digits, strlen($digits) - $decimals), '0');

		return $m[1] . $whole . ($fraction === '' ? '' : '.' . $fraction);
	}

	/**
	 * Decimal amount → min units: `"1.5", 6` → `"1500000"`. Accepts `-?[0-9]+(\.[0-9]+)?` only
	 * (no exponent, no grouping) with at most `$decimals` fractional digits; `"-0"` is `"0"`.
	 *
	 * @throws ValidationException For any other input, or more fractional digits than `$decimals`.
	 */
	public static function parse(string $amount, int $decimals): string
	{
		self::checkDecimals($decimals);
		if (preg_match('/^(-?)([0-9]+)(?:\.([0-9]+))?$/D', $amount, $m) !== 1) {
			throw Validator::fieldError('amount', 'must be a decimal number (digits, an optional "-" and "." part; no exponent)');
		}
		$fraction = $m[3] ?? '';
		if (strlen($fraction) > $decimals) {
			throw Validator::fieldError('amount', sprintf('has more fractional digits than the currency\'s %d decimals', $decimals));
		}
		$digits = ltrim($m[2] . str_pad($fraction, $decimals, '0'), '0');
		if ($digits === '') {
			return '0';
		}

		return $m[1] . $digits;
	}

	private static function checkDecimals(int $decimals): void
	{
		if ($decimals < 0 || $decimals > self::MAX_DECIMALS) {
			throw Validator::fieldError('decimals', 'must be between 0 and ' . self::MAX_DECIMALS);
		}
	}
}
