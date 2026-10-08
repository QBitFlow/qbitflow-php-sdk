<?php

declare(strict_types=1);

namespace QBitFlow\Models;

use QBitFlow\Support\Cast;

/**
 * Names the member whose space a request acts in.
 */
final readonly class MeMember extends Model
{
	/**
	 * The member (as `members->list()` lists them).
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
	 * @param array<string,mixed> $data
	 */
	protected function __construct(array $data)
	{
		$this->userUuid = Cast::string($data, 'userUuid');
		$this->name = Cast::string($data, 'name');
		$this->lastName = Cast::string($data, 'lastName');
		$this->email = Cast::string($data, 'email');
	}
}
