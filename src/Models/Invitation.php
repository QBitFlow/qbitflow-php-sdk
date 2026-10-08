<?php

declare(strict_types=1);

namespace QBitFlow\Models;

use DateTimeImmutable;
use QBitFlow\Support\Cast;

/**
 * An invitation to join the organization.
 */
final readonly class Invitation extends Model
{
	/**
	 * The invitation's id.
	 */
	public string $uuid;

	/**
	 * A member invitation's mode; null for a team invitation.
	 */
	public ?bool $test;

	/**
	 * The invited address.
	 */
	public string $email;

	/**
	 * `admin` (the team) or `user` (a member).
	 */
	public string $role;

	/**
	 * True when the organization holds the member's payments until it trusts them; null for the team.
	 */
	public ?bool $trustLayer;

	/**
	 * The organization's fee on the member's payments, in percent.
	 */
	public float $organizationFeePercent;

	/**
	 * Where the person goes once they accepted (with `?invitationUuid=<uuid>`).
	 */
	public ?string $redirectUrl;

	/**
	 * When it expires.
	 */
	public DateTimeImmutable $expiresAt;

	/**
	 * The account that accepted it.
	 */
	public ?string $acceptedByUserUuid;

	/**
	 * When it was accepted; null until then.
	 */
	public ?DateTimeImmutable $acceptedAt;

	/**
	 * When it was revoked; null unless revoked.
	 */
	public ?DateTimeImmutable $revokedAt;

	/**
	 * When it was created.
	 */
	public DateTimeImmutable $createdAt;

	/**
	 * `pending`, `accepted`, `revoked` or `expired` ({@see \QBitFlow\Enums\InvitationStatus}).
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
		$this->trustLayer = Cast::nullableBool($data, 'trustLayer');
		$this->organizationFeePercent = Cast::float($data, 'organizationFeePercent');
		$this->redirectUrl = Cast::nullableString($data, 'redirectUrl');
		$this->expiresAt = Cast::date($data, 'expiresAt');
		$this->acceptedByUserUuid = Cast::nullableString($data, 'acceptedByUserUuid');
		$this->acceptedAt = Cast::nullableDate($data, 'acceptedAt');
		$this->revokedAt = Cast::nullableDate($data, 'revokedAt');
		$this->createdAt = Cast::date($data, 'createdAt');
		$this->status = Cast::string($data, 'status');
	}
}
