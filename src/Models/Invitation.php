<?php

declare(strict_types=1);

namespace QBitFlow\Models;

use DateTimeImmutable;
use QBitFlow\Support\Cast;

/**
 * An invitation to the organization: to be a member in one mode (role `user`), or to its team
 * (role `admin`, from the dashboard: mode-free, so `test`, `holdFunds` and
 * `organizationFeePercent` are absent).
 */
readonly class Invitation extends Model
{
	/**
	 * The invitation's id.
	 */
	public string $uuid;

	/**
	 * A member invitation's mode; null (absent) for a team invitation.
	 */
	public ?bool $test;

	/**
	 * The invited address.
	 */
	public string $email;

	/**
	 * `admin` (the team) or `user` (a member); see {@see \QBitFlow\Enums\Role}.
	 */
	public string $role;

	/**
	 * For a member, true when the organization holds their payments until it trusts them
	 * (`members->trust()`), false when they are paid directly; null (absent) for the team.
	 */
	public ?bool $holdFunds;

	/**
	 * For a member, the organization's fee on their payments, a percent string (`"0"`: none);
	 * null (absent) for the team.
	 */
	public ?string $organizationFeePercent;

	/**
	 * Where the person goes once they accepted (with `?invitationUuid=<uuid>`); null when none.
	 */
	public ?string $redirectUrl;

	/**
	 * When it expires.
	 */
	public DateTimeImmutable $expiresAt;

	/**
	 * The account that accepted it; null until accepted.
	 */
	public ?string $acceptedByUserUuid;

	/**
	 * When it was accepted; null until then.
	 */
	public ?DateTimeImmutable $acceptedAt;

	/**
	 * When it was revoked (or replaced by a newer one); null unless revoked.
	 */
	public ?DateTimeImmutable $revokedAt;

	/**
	 * When it was created.
	 */
	public DateTimeImmutable $createdAt;

	/**
	 * `pending`, `accepted`, `revoked` or `expired`, computed when read
	 * ({@see \QBitFlow\Enums\InvitationStatus}).
	 */
	public string $status;

	/**
	 * @param array<string,mixed> $data
	 */
	protected function __construct(array $data)
	{
		$this->uuid = Cast::string($data, 'uuid');
		$this->test = Cast::nullableBool($data, 'test');
		$this->email = Cast::string($data, 'email');
		$this->role = Cast::string($data, 'role');
		$this->holdFunds = Cast::nullableBool($data, 'holdFunds');
		$this->organizationFeePercent = Cast::nullableString($data, 'organizationFeePercent');
		$this->redirectUrl = Cast::nullableString($data, 'redirectUrl');
		$this->expiresAt = Cast::date($data, 'expiresAt');
		$this->acceptedByUserUuid = Cast::nullableString($data, 'acceptedByUserUuid');
		$this->acceptedAt = Cast::nullableDate($data, 'acceptedAt');
		$this->revokedAt = Cast::nullableDate($data, 'revokedAt');
		$this->createdAt = Cast::date($data, 'createdAt');
		$this->status = Cast::string($data, 'status');
	}
}
