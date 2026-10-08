<?php

declare(strict_types=1);

namespace QBitFlow\Models;

use DateTimeImmutable;
use QBitFlow\Support\Cast;

/**
 * A member of the organization (a seller of a marketplace), in one mode.
 */
readonly class Member extends Model
{
	/**
	 * The member's id: what On-Behalf-Of takes.
	 */
	public string $userUuid;

	/**
	 * The first name.
	 */
	public string $name;

	/**
	 * The last name.
	 */
	public string $lastName;

	/**
	 * The email.
	 */
	public string $email;

	/**
	 * The mode.
	 */
	public bool $test;

	/**
	 * The organization's fee on the member's payments, in percent.
	 */
	public float $organizationFeePercent;

	/**
	 * When the organization started paying them directly; null while it holds their funds (trust layer).
	 */
	public ?DateTimeImmutable $trustedAt;

	/**
	 * When they joined in this mode.
	 */
	public DateTimeImmutable $joinedAt;

	/**
	 * The currencies their wallets accept.
	 *
	 * @var list<int>
	 */
	public array $acceptedCurrencyIds;

	/**
	 * Their personal space in this mode.
	 */
	public string $spaceUuid;

	/**
	 * @param array<string,mixed> $data
	 */
	protected function __construct(array $data)
	{
		$this->userUuid = Cast::string($data, 'userUuid');
		$this->name = Cast::string($data, 'name');
		$this->lastName = Cast::string($data, 'lastName');
		$this->email = Cast::string($data, 'email');
		$this->test = Cast::bool($data, 'test');
		$this->organizationFeePercent = Cast::float($data, 'organizationFeePercent');
		$this->trustedAt = Cast::nullableDate($data, 'trustedAt');
		$this->joinedAt = Cast::date($data, 'joinedAt');
		$this->acceptedCurrencyIds = Cast::uintList($data, 'acceptedCurrencyIds');
		$this->spaceUuid = Cast::string($data, 'spaceUuid');
	}
}
