<?php

declare(strict_types=1);

namespace QBitFlow\Params;

use QBitFlow\Support\Query;
use QBitFlow\Support\Validator;

/**
 * `wallets->listSupportedCurrencies()`'s options.
 */
final readonly class SupportedCurrenciesParams
{
	public function __construct(
		/** Reads a member's (organization key); null for the request's space. */
		public ?string $userUuid = null,
	) {
	}

	/** @throws \QBitFlow\Exceptions\ValidationException */
	public function validate(): void
	{
		$v = new Validator();
		$v->uuid('userUuid', $this->userUuid);
		$v->throwIfAny();
	}

	/** @return array<string,string> */
	public function toQuery(): array
	{
		return (new Query())->string('userUuid', $this->userUuid)->toArray();
	}
}
