<?php

declare(strict_types=1);

namespace QBitFlow\Models;

use DateTimeImmutable;
use QBitFlow\Support\Cast;

/**
 * A member of the organization (a seller of a marketplace), in one mode: their personal space there.
 */
readonly class Member extends Model
{
	/**
	 * The member's id: what On-Behalf-Of (`$client->onBehalfOf()`, `RequestOptions::$onBehalfOf`) takes.
	 */
	public string $userUuid;

	/**
	 * The first name.
	 */
	public string $firstName;

	/**
	 * The last name; null (absent) when the account has none.
	 */
	public ?string $lastName;

	/**
	 * The email.
	 */
	public string $email;

	/**
	 * The mode.
	 */
	public bool $test;

	/**
	 * The organization's fee on the member's payments in this mode, a percent string (`"0"`: none).
	 */
	public string $organizationFeePercent;

	/**
	 * When the organization started paying them directly; null (absent) while it holds their
	 * funds (held funds).
	 */
	public ?DateTimeImmutable $trustedAt;

	/**
	 * When they joined in this mode.
	 */
	public DateTimeImmutable $joinedAt;

	/**
	 * The currencies a checkout of the member can be paid in now: those their wallets accept,
	 * always within the organization's (the organization's own while it holds their funds).
	 * Empty: a checkout of theirs answers 409 `merchant_not_ready`.
	 *
	 * @var list<Currency>
	 */
	public array $acceptedCurrencies;

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
		$this->firstName = Cast::string($data, 'firstName');
		$this->lastName = Cast::nullableString($data, 'lastName');
		$this->email = Cast::string($data, 'email');
		$this->test = Cast::bool($data, 'test');
		$this->organizationFeePercent = Cast::string($data, 'organizationFeePercent');
		$this->trustedAt = Cast::nullableDate($data, 'trustedAt');
		$this->joinedAt = Cast::date($data, 'joinedAt');
		$this->acceptedCurrencies = Cast::listOf($data, 'acceptedCurrencies', Currency::fromArray(...));
		$this->spaceUuid = Cast::string($data, 'spaceUuid');
	}
}
