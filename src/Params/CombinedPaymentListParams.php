<?php

declare(strict_types=1);

namespace QBitFlow\Params;

use DateTimeInterface;
use QBitFlow\Enums\CombinedPaymentSource;
use QBitFlow\Support\Query;
use QBitFlow\Support\Validator;

/**
 * Filters `payments->listCombined()` / `iterateCombined()`.
 */
final readonly class CombinedPaymentListParams
{
	use WithCursor;

	public function __construct(
		/** The page size (server default 10, max 50). */
		public ?int $limit = null,
		/** The previous page's `nextCursor`; null for the first page. */
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
		/** Keeps only the payments (`payment`) or only the bills (`subscriptionHistory`), see {@see CombinedPaymentSource}. */
		public ?string $source = null,
		/** Keeps only this subscription's bills (`sub@…`). */
		public ?string $subscriptionUuid = null,
		/** Keeps only the rows with (true) or without (false) an approved refund. */
		public ?bool $refunded = null,
	) {
	}

	/** @throws \QBitFlow\Exceptions\ValidationException */
	public function validate(): void
	{
		$v = new Validator();
		ListFilters::validate($v, $this->customerUuid, $this->productUuid, $this->includeMembers, $this->userUuid);
		$v->oneOf('source', $this->source, CombinedPaymentSource::values());
		$v->txId('subscriptionUuid', $this->subscriptionUuid);
		$v->throwIfAny();
	}

	/** @return array<string,string> */
	public function toQuery(): array
	{
		$q = (new Query())->page($this->limit, $this->cursor);
		ListFilters::encode($q, $this->customerUuid, $this->productUuid, $this->createdAfter, $this->createdBefore, $this->includeMembers, $this->userUuid);

		return $q->string('source', $this->source)->string('subscriptionUuid', $this->subscriptionUuid)->bool('refunded', $this->refunded)->toArray();
	}
}
