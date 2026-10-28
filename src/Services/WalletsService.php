<?php

declare(strict_types=1);

namespace QBitFlow\Services;

use QBitFlow\Http\Requester;
use QBitFlow\Models\Currency;
use QBitFlow\Models\Wallet;
use QBitFlow\Params\AcceptedCurrenciesParams;
use QBitFlow\Params\WalletListParams;
use QBitFlow\RequestOptions;
use QBitFlow\Support\Validator;

/**
 * Reads the wallets and the currencies a space accepts (`/wallets…`). Wallets are added and
 * removed in the dashboard.
 */
final class WalletsService extends Service
{
	/**
	 * The request's space's wallets (`GET /wallets`; a bounded list), each with the currencies it
	 * accepts. `withBalances` adds each token wallet's balance, read on-chain.
	 *
	 * @return list<Wallet>
	 */
	public function list(?WalletListParams $params = null, ?RequestOptions $options = null): array
	{
		$params?->validate();

		return $this->requester->items('/wallets', Wallet::fromArray(...), $params?->toQuery() ?? [], $options);
	}

	/**
	 * A member's wallets in the key's mode (`GET /members/:userUuid/wallets`; a bounded list).
	 * Organization key only (403 otherwise, also with On-Behalf-Of); someone who is not a member
	 * is a {@see \QBitFlow\Exceptions\NotFoundException}.
	 *
	 * @return list<Wallet>
	 */
	public function listForMember(string $userUuid, ?RequestOptions $options = null): array
	{
		Validator::pathUuid('userUuid', $userUuid);

		return $this->requester->items(Requester::path('/members/%s/wallets', $userUuid), Wallet::fromArray(...), options: $options);
	}

	/**
	 * The currencies the space's checkouts accept now (`GET /wallets/accepted-currencies`; a
	 * bounded list): check a seller is ready before opening their checkouts (none: their
	 * checkouts answer 409 `merchant_not_ready`). `userUuid` reads a member's (organization key;
	 * 403 otherwise).
	 *
	 * @return list<Currency>
	 */
	public function listAcceptedCurrencies(?AcceptedCurrenciesParams $params = null, ?RequestOptions $options = null): array
	{
		$params?->validate();

		return $this->requester->items('/wallets/accepted-currencies', Currency::fromArray(...), $params?->toQuery() ?? [], $options);
	}
}
