<?php

declare(strict_types=1);

namespace QBitFlow\Events;

use QBitFlow\Models\MemberJoined;
use QBitFlow\Support\Cast;

/**
 * `member.joined`: Someone accepted an invitation (`data->invitationUuid`) and is now a member (`data->userUuid`).
 */
final readonly class MemberJoinedEvent extends Event
{
	/** The event's data. */
	public MemberJoined $data;

	/**
	 * @param array<string,mixed> $data
	 */
	protected function __construct(array $data)
	{
		parent::__construct($data);
		$this->data = Cast::object($data, 'data', MemberJoined::fromArray(...));
	}
}
