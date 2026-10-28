<?php

declare(strict_types=1);

namespace QBitFlow\Params;

use QBitFlow\Support\Validator;
use stdClass;

/**
 * What a checkout adds to its product's price: a one-time payment's
 * (`CreatePaymentSessionParams::$fees`) or a subscription's (`CreateSubscriptionSessionParams::$fees`,
 * each of its bills then charging the price and the same lines). Your own lines and QBitFlow's
 * processing fee: the customer sees them line by line and pays them with the price. The payment's
 * `amountUsd` (a subscription's: each bill's) is the price plus every line, and
 * QBitFlow's fee (and a marketplace's organization fee) is taken on that amount. The network fee
 * comes on top, once the customer picks a currency. Test mode caps the amount, fees included, at
 * `"5"` USD (the API answers 400 `validation_failed` on `fees`).
 */
final readonly class CheckoutFees
{
	/** At most this many lines of your own. */
	public const MAX_ITEMS = 10;

	/** The most a line can be, in USD. */
	public const MAX_AMOUNT_USD = Validator::MAX_USD;

	/**
	 * Pass the arguments by name. Nothing is checked here: the values are checked with the
	 * params that hold them, by the service method, before sending (a
	 * {@see \QBitFlow\Exceptions\ValidationException} naming each bad one, nothing sent).
	 *
	 * @param list<FeeItem> $items
	 */
	public function __construct(
		/**
		 * Whether the customer pays QBitFlow's processing fee, as a last line ("Processing fee").
		 * QBitFlow computes it on the price and your lines at your platform fee `p` (never below
		 * 0.5 %) and grosses it up, `base × p / (1 − p)` rounded up to the cent, since its fee is
		 * also taken on the line itself: you keep at least the price and your lines, as if no fee
		 * were taken ($100 + $20 VAT at 0.99 % → a $1.20 processing fee, $121.20 charged, $120.00
		 * kept). `p` is the checkout's rate: QBitFlow's fee for your space when the checkout is
		 * created (a volume schedule, unless QBitFlow set you a custom rate). Null: the space's
		 * `checkout.customerPaysProcessingFee` setting decides (off by default; set by the
		 * organization, for its own space and for each member's: a member can't change it);
		 * `false` turns it off for this checkout.
		 */
		public ?bool $processingFee = null,
		/** Up to 10 lines of your own, shown in this order before the processing fee. */
		public array $items = [],
	) {
	}

	/**
	 * Checks the lines: at most 10; each label the name rule, 1 to 40; each
	 * description the text rule, at most 200; each amount the usd rule. Field errors on
	 * `fees.items[<i>].<field>` (`fees.items` for the count).
	 */
	public function check(Validator $v): void
	{
		$items = array_values($this->items);
		if (count($items) > self::MAX_ITEMS) {
			$v->add('fees.items', sprintf('must have at most %d lines', self::MAX_ITEMS));
		}
		foreach ($items as $i => $item) {
			$path = sprintf('fees.items[%d]', $i);
			if (! $item instanceof FeeItem) {
				$v->add($path, 'must be a ' . FeeItem::class);

				continue;
			}
			if ($v->required($path . '.label', $item->label)) {
				$v->name($path . '.label', $item->label, 1, 40);
			}
			$v->text($path . '.description', $item->description, 0, 200);
			if ($v->required($path . '.amountUsd', $item->amountUsd)) {
				$v->usd($path . '.amountUsd', $item->amountUsd, self::MAX_AMOUNT_USD);
			}
		}
	}

	/** The `fees` object as sent (`processingFee` and `items` only when set). */
	public function toObject(): stdClass
	{
		$out = new stdClass();
		if ($this->processingFee !== null) {
			$out->processingFee = $this->processingFee;
		}
		if ($this->items !== []) {
			$out->items = array_map(static fn (FeeItem $item): array => $item->toArray(), array_values($this->items));
		}

		return $out;
	}
}
