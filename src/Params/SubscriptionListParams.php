<?php

declare(strict_types=1);

namespace QBitFlow\Params;

use DateTimeInterface;
use QBitFlow\Enums\SubscriptionStatus;
use QBitFlow\Support\Query;
use QBitFlow\Support\Validator;

/**
 * Filters `subscriptions->list()` / `iterate()`.
 */
final readonly class SubscriptionListParams
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
		/** Keeps only this customer's. */
		public ?string $customerUuid = null,
		/** Keeps only this product's. */
		public ?string $productUuid = null,
		/** Keeps only those created after this instant (excluded). */
		public ?DateTimeInterface $createdAfter = null,
		/** Keeps only those created before this instant (excluded). */
		public ?DateTimeInterface $createdBefore = null,
		/** Adds the members' rows (organization space only); not with `userUuid`. */
		public bool $includeMembers = false,
		/** Reads one member's rows (organization space only); not with `includeMembers`. */
		public ?string $userUuid = null,
		/**
		 * Keeps only those in this status ({@see SubscriptionStatus}; cancelled ones are listed
		 * unless filtered out). Not checked against the known statuses: a newer one is sent as is.
		 */
		public ?string $status = null,
		/**
		 * Keeps only the one with this reference (a page of 0 or 1 subscription;
		 * `subscriptions->getByReference()` reads it).
		 */
		public ?string $reference = null,
	) {
	}

	/** @throws \QBitFlow\Exceptions\ValidationException */
	public function validate(): void
	{
		$v = new Validator();
		$v->cursor($this->cursor);
		ListFilters::validate($v, $this->customerUuid, $this->productUuid, $this->includeMembers, $this->userUuid);
		$v->reference('reference', $this->reference);
		$v->throwIfAny();
	}

	/** @return array<string,string|list<string>> */
	public function toQuery(): array
	{
		$q = (new Query())->page($this->limit, $this->cursor);
		ListFilters::encode($q, $this->customerUuid, $this->productUuid, $this->createdAfter, $this->createdBefore, $this->includeMembers, $this->userUuid);

		return $q->string('status', $this->status)->string('reference', $this->reference)->toArray();
	}
}
