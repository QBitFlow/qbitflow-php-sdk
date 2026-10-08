<?php

declare(strict_types=1);

namespace QBitFlow\Params;

use QBitFlow\Support\Query;
use QBitFlow\Support\Validator;

/**
 * Filters `customers->list()` / `iterate()`.
 */
final readonly class CustomerListParams
{
	use WithCursor;

	public function __construct(
		/** The page size (server default 10, max 100). */
		public ?int $limit = null,
		/** The previous page's `nextCursor`; null for the first page. */
		public ?string $cursor = null,
		/** Keeps only the customers with this email, whatever its casing. */
		public ?string $email = null,
		/** Keeps only the customers the merchant created (true) or those created at checkout (false). */
		public ?bool $verified = null,
	) {
	}

	/** @throws \QBitFlow\Exceptions\ValidationException */
	public function validate(): void
	{
		$v = new Validator();
		$v->email('email', $this->email);
		$v->throwIfAny();
	}

	/** @return array<string,string> */
	public function toQuery(): array
	{
		return (new Query())->page($this->limit, $this->cursor)->string('email', $this->email)->bool('verified', $this->verified)->toArray();
	}
}
