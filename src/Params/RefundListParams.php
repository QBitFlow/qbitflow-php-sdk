<?php

declare(strict_types=1);

namespace QBitFlow\Params;

use QBitFlow\Enums\RefundStatus;
use QBitFlow\Support\Query;
use QBitFlow\Support\Validator;

/**
 * Filters `refunds->list()` / `iterate()`.
 */
final readonly class RefundListParams
{
	use WithCursor;

	/** The most statuses `status` may name. */
	public const MAX_STATUSES = 5;

	/**
	 * @param list<string> $status
	 */
	public function __construct(
		/** The page size (server default 20, max 100). */
		public ?int $limit = null,
		/** The previous page's `nextCursor`, verbatim; null for the first page. */
		public ?string $cursor = null,
		/**
		 * Keeps only the refunds in one of these statuses (at most 5 of `pending`, `approved`,
		 * `rejected`, `cancelled`, `failed`; {@see RefundStatus}), sent as a repeated `?status=`;
		 * `[]`: every status. `[RefundStatus::PENDING]` lists the ones awaiting an answer.
		 */
		public array $status = [],
		/** Keeps only the refund of this transaction (`pay@…`, `sub-hist@…`, or the bare UUID). */
		public ?string $txUuid = null,
		/**
		 * Keeps only the refunds of payments held for a member (true: the ones the organization
		 * decides and sends), or only the others (false).
		 */
		public ?bool $held = null,
		/**
		 * Adds the members' refunds, each naming its member: the API's default is true from the
		 * organization's space (false from a member's, where true is refused). Not with `userUuid`.
		 */
		public ?bool $includeMembers = null,
		/** Reads one member's refunds (organization space only). */
		public ?string $userUuid = null,
	) {
	}

	/** @throws \QBitFlow\Exceptions\ValidationException */
	public function validate(): void
	{
		$v = new Validator();
		$v->cursor($this->cursor);
		$statuses = array_values($this->status);
		if (count($statuses) > self::MAX_STATUSES) {
			$v->add('status', sprintf('must name at most %d statuses', self::MAX_STATUSES));
		}
		foreach ($statuses as $i => $status) {
			$field = sprintf('status[%d]', $i);
			if (! is_string($status) || $status === '') {
				$v->add($field, 'is required');

				continue;
			}
			$v->oneOf($field, $status, RefundStatus::values());
		}
		$v->txId('txUuid', $this->txUuid);
		$v->uuid('userUuid', $this->userUuid);
		$v->exclusive('includeMembers', $this->includeMembers === true, 'userUuid', $this->userUuid !== null && $this->userUuid !== '');
		$v->throwIfAny();
	}

	/** @return array<string,string|list<string>> */
	public function toQuery(): array
	{
		return (new Query())->page($this->limit, $this->cursor)
			->strings('status', array_values($this->status))
			->string('txUuid', $this->txUuid)
			->bool('held', $this->held)
			->bool('includeMembers', $this->includeMembers)
			->string('userUuid', $this->userUuid)
			->toArray();
	}
}
