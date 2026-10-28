<?php

declare(strict_types=1);

namespace QBitFlow\Events;

use QBitFlow\Models\Member;
use QBitFlow\Support\Cast;

/**
 * `member.removed`: A member was removed.
 */
final readonly class MemberRemovedEvent extends Event
{
	/** The event's data. */
	public Member $data;

	/**
	 * @param array<string,mixed> $data
	 */
	protected function __construct(array $data)
	{
		parent::__construct($data);
		$this->data = Cast::object($data, 'data', Member::fromArray(...));
	}
}
