<?php

declare(strict_types=1);

namespace QBitFlow\Models;

use QBitFlow\Support\Cast;

/**
 * `invitations->create()`'s answer.
 */
final readonly class InvitationCreated extends Model
{
	/**
	 * The invitation.
	 */
	public Invitation $invitation;

	/**
	 * The link to accept it, also emailed to the person.
	 */
	public string $link;

	/**
	 * @param array<string,mixed> $data
	 */
	protected function __construct(array $data)
	{
		$this->invitation = Cast::object($data, 'invitation', Invitation::fromArray(...));
		$this->link = Cast::string($data, 'link');
	}
}
