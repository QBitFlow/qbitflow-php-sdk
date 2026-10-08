<?php

declare(strict_types=1);

namespace QBitFlow\Params;

use QBitFlow\Enums\InvitationStatus;
use QBitFlow\Support\Query;
use QBitFlow\Support\Validator;

/**
 * Filters `invitations->list()` / `iterate()`.
 */
final readonly class InvitationListParams
{
	use WithCursor;

	public function __construct(
		/** The page size (server default 20, max 100). */
		public ?int $limit = null,
		/** The previous page's `nextCursor`; null for the first page. */
		public ?string $cursor = null,
		/** Keeps only the invitations with this status ({@see InvitationStatus}). */
		public ?string $status = null,
	) {
	}

	/** @throws \QBitFlow\Exceptions\ValidationException */
	public function validate(): void
	{
		$v = new Validator();
		$v->oneOf('status', $this->status, InvitationStatus::values());
		$v->throwIfAny();
	}

	/** @return array<string,string> */
	public function toQuery(): array
	{
		return (new Query())->page($this->limit, $this->cursor)->string('status', $this->status)->toArray();
	}
}
