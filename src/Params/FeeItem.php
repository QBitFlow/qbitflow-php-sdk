<?php

declare(strict_types=1);

namespace QBitFlow\Params;

/**
 * A line of your own on a checkout (a tax, shipping, a service fee), shown to the customer in
 * the order given and paid with the price, a subscription's with every bill ({@see CheckoutFees}).
 */
final readonly class FeeItem
{
	public function __construct(
		/** The line's name, as the checkout shows it: one line, 1 to 40 characters ("VAT (20%)", "Shipping"). */
		public string $label,
		/**
		 * The line's amount in USD, a decimal string (`"4.99"`): above 0, at most 1,000,000, with
		 * at most 2 decimals. Sent as typed, so no float rounds it.
		 */
		public string $amountUsd,
		/** More about the line, at most 200 characters (optional). */
		public ?string $description = null,
	) {
	}

	/**
	 * The line's wire fields.
	 *
	 * @return array<string,mixed>
	 */
	public function toArray(): array
	{
		$out = ['label' => $this->label];
		if ($this->description !== null && $this->description !== '') {
			$out['description'] = $this->description;
		}
		$out['amountUsd'] = $this->amountUsd;

		return $out;
	}
}
