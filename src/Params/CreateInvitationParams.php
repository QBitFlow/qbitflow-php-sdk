<?php

declare(strict_types=1);

namespace QBitFlow\Params;

use QBitFlow\Enums\Role;
use QBitFlow\Support\Validator;

/**
 * Invites a member (`invitations->create()`). The SDK always sends role `user`: team
 * invitations are made from the dashboard.
 */
final readonly class CreateInvitationParams
{
	public function __construct(
		/** Required: the person to invite, at most 254 characters. */
		public string $email,
		/**
		 * Required (always sent): true holds the member's payments until the organization trusts
		 * them (`members->trust()`); false pays them directly.
		 */
		public bool $holdFunds,
		/**
		 * The organization's fee on their payments, a percent string from `"0"` to `"50"` with at
		 * most 2 decimals (null or `''` = `"0"`).
		 */
		public ?string $organizationFeePercent = null,
		/**
		 * Where the person goes once they accepted (with `?invitationUuid=<uuid>`): a convenience,
		 * confirm with the `member.joined` event.
		 */
		public ?string $redirectUrl = null,
	) {
	}

	/** @throws \QBitFlow\Exceptions\ValidationException */
	public function validate(): void
	{
		$v = new Validator();
		if ($v->required('email', $this->email)) {
			$v->email('email', $this->email);
		}
		if ($this->organizationFeePercent !== null && $this->organizationFeePercent !== '') {
			$v->percent('organizationFeePercent', $this->organizationFeePercent, 50);
		}
		$v->url('redirectUrl', $this->redirectUrl);
		$v->throwIfAny();
	}

	/**
	 * The invitation's body, with role `user`.
	 *
	 * @return array<string,mixed>
	 */
	public function toArray(): array
	{
		$out = ['email' => $this->email, 'role' => Role::USER, 'holdFunds' => $this->holdFunds];
		if ($this->organizationFeePercent !== null && $this->organizationFeePercent !== '') {
			$out['organizationFeePercent'] = $this->organizationFeePercent;
		}
		if ($this->redirectUrl !== null && $this->redirectUrl !== '') {
			$out['redirectUrl'] = $this->redirectUrl;
		}

		return $out;
	}
}
