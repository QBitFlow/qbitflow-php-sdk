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
		/** The previous page's `nextCursor` (`evt_…`); null for the first page. */
		public ?string $cursor = null,
		/** Keeps only the events of this type ({@see EventType}). */
		public ?string $type = null,
		/** Adds the members' events (organization space only). */
		public bool $includeMembers = false,
	) {
	}

	/** @throws \QBitFlow\Exceptions\ValidationException */
	public function validate(): void
	{
		$v = new Validator();
		$v->oneOf('type', $this->type, EventType::values());
		$v->throwIfAny();
	}

	/** @return array<string,string> */
	public function toQuery(): array
	{
		return (new Query())->page($this->limit, $this->cursor)->string('type', $this->type)->flag('includeMembers', $this->includeMembers)->toArray();
	}
}
