<?php

declare(strict_types=1);

namespace QBitFlow\Params;

use DateTimeInterface;
use QBitFlow\Support\Query;
use QBitFlow\Support\Validator;

/**
 * The filters every transaction list shares (payments, combined feed, failures,
 * subscriptions): encoding and checks.
 *
 * @internal
 */
final class ListFilters
{
	private function __construct()
	{
	}

	public static function encode(
		Query $query,
		?string $customerUuid,
		?string $productUuid,
		?DateTimeInterface $createdAfter,
		?DateTimeInterface $createdBefore,
		bool $includeMembers,
		?string $userUuid,
	): void {
		$query->string('customerUuid', $customerUuid)
			->string('productUuid', $productUuid)
			->time('createdAfter', $createdAfter)
			->time('createdBefore', $createdBefore)
			->flag('includeMembers', $includeMembers)
			->string('userUuid', $userUuid);
	}

	public static function validate(Validator $v, ?string $customerUuid, ?string $productUuid, bool $includeMembers, ?string $userUuid): void
	{
		$v->uuid('customerUuid', $customerUuid);
		$v->uuid('productUuid', $productUuid);
		$v->uuid('userUuid', $userUuid);
		$v->exclusive('includeMembers', $includeMembers, 'userUuid', $userUuid !== null && $userUuid !== '');
	}
}
