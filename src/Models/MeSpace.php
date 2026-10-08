<?php

declare(strict_types=1);

namespace QBitFlow\Models;

use QBitFlow\Support\Cast;

/**
 * The space a request acts in.
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
	 * Names that member; null for the organization's own space.
	 */
	public ?MeMember $member;

	/**
	 * The space's mode: true for test, false for live.
	 */
	public bool $test;

	/**
	 * @param array<string,mixed> $data
	 */
	protected function __construct(array $data)
	{
		$this->uuid = Cast::string($data, 'uuid');
		$this->organizationUuid = Cast::string($data, 'organizationUuid');
		$this->organizationName = Cast::string($data, 'organizationName');
		$this->userUuid = Cast::nullableString($data, 'userUuid');
		$this->member = Cast::nullableObject($data, 'member', MeMember::fromArray(...));
		$this->test = Cast::bool($data, 'test');
	}
}
