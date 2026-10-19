<?php

declare(strict_types=1);

namespace QBitFlow\Params;

use QBitFlow\Enums\EventType;
use QBitFlow\Support\Query;
use QBitFlow\Support\Validator;

/**
 * Filters `webhooks->events->list()` / `iterate()`.
 */
final readonly class EventListParams
{
	use WithCursor;

	public function __construct(
		/** The page size (server default 20, max 100). */
		public ?int $limit = null,
		/** The previous page's `nextCursor`, verbatim (opaque: never an event id); null for the first page. */
		public ?string $cursor = null,
		/**
		 * Keeps only the events of this type ({@see EventType}). Not checked against the SDK's known
		 * types (newer ones appear over time); an unknown type is the API's 400.
		 */
		public ?string $type = null,
		/** Adds the members' events (organization space only; 400 from a member's). */
		public bool $includeMembers = false,
	) {
	}

	/** @throws \QBitFlow\Exceptions\ValidationException */
	public function validate(): void
	{
		$v = new Validator();
		$v->cursor($this->cursor);
		$v->throwIfAny();
	}

	/** @return array<string,string|list<string>> */
	public function toQuery(): array
	{
		return (new Query())->page($this->limit, $this->cursor)->string('type', $this->type)->flag('includeMembers', $this->includeMembers)->toArray();
	}
}
