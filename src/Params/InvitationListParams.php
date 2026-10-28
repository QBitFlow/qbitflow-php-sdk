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

	/**
	 * Every filter is optional: pass the ones you need by name; one left null is not sent.
	 * Nothing is checked here: the service method checks the values before sending (a
	 * {@see \QBitFlow\Exceptions\ValidationException} naming each bad one, nothing sent).
	 */
	public function __construct(
		/** The page size (server default 20, max 100). */
		public ?int $limit = null,
		/** The previous page's `nextCursor`, verbatim; null for the first page. */
		public ?string $cursor = null,
		/** Keeps only the invitations with this status: `pending`, `accepted`, `revoked` or `expired` ({@see InvitationStatus}). */
		public ?string $status = null,
	) {
	}

	/** @throws \QBitFlow\Exceptions\ValidationException */
	public function validate(): void
	{
		$v = new Validator();
		$v->cursor($this->cursor);
		$v->oneOf('status', $this->status, InvitationStatus::values());
		$v->throwIfAny();
	}

	/** @return array<string,string|list<string>> */
	public function toQuery(): array
	{
		return (new Query())->page($this->limit, $this->cursor)->string('status', $this->status)->toArray();
	}
}
