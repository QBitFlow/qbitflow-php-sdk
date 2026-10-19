<?php

declare(strict_types=1);

namespace QBitFlow\Models;

use DateTimeImmutable;
use QBitFlow\Support\Cast;

/**
 * The space a request acts in: one organization's data in one mode, the organization's shared
 * space or a member's personal space.
 */
final readonly class MeSpace extends Model
{
	/**
	 * The space's id.
	 */
	public string $uuid;

	/**
	 * Its organization.
	 */
	public string $organizationUuid;

	/**
	 * Its organization's name.
	 */
	public string $organizationName;

	/**
	 * The member whose space it is; null for the organization's own space.
	 */
	public ?string $userUuid;

	/**
	 * The space's mode: true for test, false for live.
	 */
	public bool $test;

	/**
	 * The caller's own role in the organization: `owner`, `admin`, `user` or `handle`
	 * ({@see \QBitFlow\Enums\Role}).
	 */
	public string $role;

	/**
	 * Names the member whose space it is; null for the organization's own space.
	 */
	public ?MeMember $member;

	/**
	 * The organization's fee on the member's payments in this space, a percent string (`"0"`:
	 * none); null (absent) for the organization's own space.
	 */
	public ?string $organizationFeePercent;

	/**
	 * When the organization started paying the member directly; null while it holds their funds
	 * (always set on the organization's own space).
	 */
	public ?DateTimeImmutable $trustedAt;

	/**
	 * When the space was created.
	 */
	public DateTimeImmutable $createdAt;

	/**
	 * @param array<string,mixed> $data
	 */
	protected function __construct(array $data)
	{
		$this->uuid = Cast::string($data, 'uuid');
		$this->organizationUuid = Cast::string($data, 'organizationUuid');
		$this->organizationName = Cast::string($data, 'organizationName');
		$this->userUuid = Cast::nullableString($data, 'userUuid');
		$this->test = Cast::bool($data, 'test');
		$this->role = Cast::string($data, 'role');
		$this->member = Cast::nullableObject($data, 'member', MeMember::fromArray(...));
		$this->organizationFeePercent = Cast::nullableString($data, 'organizationFeePercent');
		$this->trustedAt = Cast::nullableDate($data, 'trustedAt');
		$this->createdAt = Cast::date($data, 'createdAt');
	}
}
