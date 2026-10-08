<?php

declare(strict_types=1);

namespace QBitFlow\Params;

use QBitFlow\Support\Query;
use QBitFlow\Support\Validator;

/**
 * Filters `refunds->list()` and `refunds->listInactive()` / `iterateInactive()` (`limit` and
 * `cursor` page the latter; `refunds->list()` does not send them).
 */
final readonly class RefundListParams
{
	use WithCursor;

	public function __construct(
		/** `listInactive()`'s page size (server default 10, max 50). */
		public ?int $limit = null,
		/** `listInactive()`'s previous page's `nextCursor`; null for the first page. */
		public ?string $cursor = null,
		/**
		 * Adds the members' refunds: the API's default is true from the organization's space (false
		 * from a member's, where true is refused). Not with `userUuid`.
		 */
		public ?bool $includeMembers = null,
		/** Reads one member's refunds (organization space only). */
		public ?string $userUuid = null,
		/** Keeps only the refunds of held (true) or not held (false) transactions. */
		public ?bool $held = null,
	) {
	}

	/** @throws \QBitFlow\Exceptions\ValidationException */
	public function validate(): void
	{
		$v = new Validator();
		$v->uuid('userUuid', $this->userUuid);
		$v->exclusive('includeMembers', $this->includeMembers === true, 'userUuid', $this->userUuid !== null && $this->userUuid !== '');
		$v->throwIfAny();
	}

	/**
	 * @param bool $paged Adds `limit` and `cursor` (`listInactive()`).
	 *
	 * @return array<string,string>
	 */
	public function toQuery(bool $paged = true): array
	{
		$q = new Query();
		if ($paged) {
			$q->page($this->limit, $this->cursor);
		}

		return $q->bool('includeMembers', $this->includeMembers)->string('userUuid', $this->userUuid)->bool('held', $this->held)->toArray();
	}
}
