<?php

declare(strict_types=1);

namespace QBitFlow\Params;

use QBitFlow\Support\Validator;

/**
 * Changes a member's terms (`members->update()`).
 */
final readonly class UpdateMemberParams
{
	public function __construct(
		/** The organization's fee on the member's payments from now on: 0 to 50, at most 2 decimals. Always sent. */
		public float $organizationFeePercent,
	) {
	}

	/** @throws \QBitFlow\Exceptions\ValidationException */
	public function validate(): void
	{
		$v = new Validator();
		$v->percent('organizationFeePercent', $this->organizationFeePercent, 50, false);
		$v->throwIfAny();
	}

	/** @return array<string,mixed> */
	public function toArray(): array
	{
		return ['organizationFeePercent' => $this->organizationFeePercent];
	}
}
