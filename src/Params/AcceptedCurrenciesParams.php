<?php

declare(strict_types=1);

namespace QBitFlow\Params;

use QBitFlow\Support\Query;
use QBitFlow\Support\Validator;

/**
 * `wallets->listAcceptedCurrencies()`'s options.
 */
final readonly class AcceptedCurrenciesParams
{
	/**
	 * Every filter is optional: pass the ones you need by name; one left null is not sent.
	 * Nothing is checked here: the service method checks the values before sending (a
	 * {@see \QBitFlow\Exceptions\ValidationException} naming each bad one, nothing sent).
	 */
	public function __construct(
		/** Reads a member's (an organization key; 403 otherwise, also with On-Behalf-Of); null for the request's space. */
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

	/** @return array<string,string|list<string>> */
	public function toQuery(): array
	{
		return (new Query())->string('userUuid', $this->userUuid)->toArray();
	}
}
