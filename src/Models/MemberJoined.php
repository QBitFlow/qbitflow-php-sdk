<?php

declare(strict_types=1);

namespace QBitFlow\Models;

use QBitFlow\Support\Cast;

/**
 * `member.joined`'s data: the member, and the invitation they accepted.
 */
final readonly class MemberJoined extends Member
{
	/**
	 * The invitation they accepted (`invitations->create()`'s).
	 */
	public string $invitationUuid;

	/**
	 * @param array<string,mixed> $data
	 */
	protected function __construct(array $data)
	{
		parent::__construct($data);
		$this->invitationUuid = Cast::string($data, 'invitationUuid');
	}
}
