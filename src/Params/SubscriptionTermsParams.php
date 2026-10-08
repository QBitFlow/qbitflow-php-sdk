<?php

declare(strict_types=1);

namespace QBitFlow\Params;

use QBitFlow\Models\Duration;
use QBitFlow\Support\Validator;
use stdClass;

/**
 * A subscription product's terms (each optional on an update).
 */
final readonly class SubscriptionTermsParams
{
	public function __construct(
		/** How often it bills: at least 1 unit, at most 1 year (the API also requires at least 1 hour in live mode, 5 minutes in test mode). */
		public ?Duration $frequency = null,
		/** A free trial before the first bill (`new Duration()`: none, removing one). */
		public ?Duration $trialPeriod = null,
		/** The periods the customer commits to before cancelling, at most 1000 (0: none). */
		public ?int $minPeriods = null,
	) {
	}

	/** Checks the terms; `$prefix` is the wire path (`subscription.` on products). */
	public function check(Validator $v, string $prefix, bool $frequencyRequired): void
	{
		if ($this->frequency !== null) {
			$v->duration($prefix . 'frequency', $this->frequency, true);
		} elseif ($frequencyRequired) {
			$v->add($prefix . 'frequency', 'is required');
		}
		if ($this->trialPeriod !== null) {
			$v->duration($prefix . 'trialPeriod', $this->trialPeriod, false);
		}
		if ($this->minPeriods !== null) {
			$v->intRange($prefix . 'minPeriods', $this->minPeriods, 0, 1000);
		}
	}

	/**
	 * The terms' wire fields (merged into a session's body, or nested on a product's).
	 *
	 * @return array<string,mixed>
	 */
	public function toArray(): array
	{
		$out = [];
		if ($this->frequency !== null) {
			$out['frequency'] = $this->frequency->toArray();
		}
		if ($this->trialPeriod !== null) {
			$out['trialPeriod'] = $this->trialPeriod->toArray();
		}
		if ($this->minPeriods !== null) {
			$out['minPeriods'] = $this->minPeriods;
		}

		return $out;
	}

	/** The terms as a JSON object (`{}` when empty). */
	public function toObject(): stdClass
	{
		return (object) $this->toArray();
	}
}
