<?php

declare(strict_types=1);

namespace QBitFlow\Models;

use QBitFlow\Support\Cast;

/**
 * `member.joined`'s data: the member, in the mode they joined, and the invitation they
 * accepted. Store `userUuid` for On-Behalf-Of.
 */
final readonly class MemberJoined extends Member
{
	/**
	 * The invitation they accepted (`invitations->create()`'s `uuid`): match the event to it.
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
