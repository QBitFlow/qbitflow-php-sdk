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
		/** Keeps only the customers with this email, whatever its casing (several may share one). */
		public ?string $email = null,
		/**
		 * Keeps only the customer with this reference (a page of 0 or 1 customer;
		 * `customers->getByReference()` reads it).
		 */
		public ?string $reference = null,
	) {
	}

	/** @throws \QBitFlow\Exceptions\ValidationException */
	public function validate(): void
	{
		$v = new Validator();
		$v->cursor($this->cursor);
		$v->email('email', $this->email);
		$v->reference('reference', $this->reference);
		$v->throwIfAny();
	}

	/** @return array<string,string|list<string>> */
	public function toQuery(): array
	{
		return (new Query())->page($this->limit, $this->cursor)
			->string('email', $this->email)
			->string('reference', $this->reference)
			->toArray();
	}
}
