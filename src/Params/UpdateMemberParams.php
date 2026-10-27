<?php

declare(strict_types=1);

namespace QBitFlow\Params;

use QBitFlow\Support\Validator;

/**
 * Changes a member's terms (`members->update()`).
 */
final readonly class UpdateMemberParams
{
	/**
	 * Pass the arguments by name; an optional one left null is not sent. Nothing is checked
	 * here: the service method checks the values before sending (a
	 * {@see \QBitFlow\Exceptions\ValidationException} naming each bad one, nothing sent).
	 */
	public function __construct(
		/**
		 * Required: the organization's fee on the member's payments from now on, a percent string
		 * from `"0"` to `"50"` with at most 2 decimals. A checkout already created keeps its fee; a
		 * running subscription's next bills take the new one.
		 */
		public string $organizationFeePercent,
	) {
	}

	/** @throws \QBitFlow\Exceptions\ValidationException */
	public function validate(): void
	{
		$v = new Validator();
		if ($v->required('organizationFeePercent', $this->organizationFeePercent)) {
			$v->percent('organizationFeePercent', $this->organizationFeePercent, 50);
		}
		$v->throwIfAny();
	}

	/** @return array<string,mixed> */
	public function toArray(): array
	{
		return ['organizationFeePercent' => $this->organizationFeePercent];
	}
}
