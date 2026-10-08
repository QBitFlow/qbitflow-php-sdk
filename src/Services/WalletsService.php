<?php

declare(strict_types=1);

namespace QBitFlow\Services;

use QBitFlow\Http\Requester;
use QBitFlow\Models\Currency;
use QBitFlow\Models\Wallet;
use QBitFlow\Params\SupportedCurrenciesParams;
use QBitFlow\Params\WalletListParams;
use QBitFlow\RequestOptions;
use QBitFlow\Support\Validator;

/**
 * Reads the wallets and the currencies a space accepts (`/wallet…`). Wallets are added and
 * removed in the dashboard.
 */
final class WalletsService extends Service
{
	/**
	 * The request's space's wallets (`GET /wallet/user`); `withBalances` adds each token
	 * wallet's balance.
	 *
	 * @return list<Wallet>
	 */
	public function list(?WalletListParams $params = null, ?RequestOptions $options = null): array
	{
		$params?->validate();

		return $this->requester->call('GET', '/wallet/user', Requester::list(Wallet::fromArray(...)), $params?->toQuery() ?? [], options: $options);
	}

	/**
	 * A member's wallets in the key's mode (`GET /wallet/user/:userUuid`; organization key).
	 *
	 * @return list<Wallet>
	 */
	public function listForMember(string $userUuid, ?RequestOptions $options = null): array
	{
		Validator::pathUuid('userUuid', $userUuid);

		return $this->requester->call('GET', Requester::path('/wallet/user/%s', $userUuid), Requester::list(Wallet::fromArray(...)), options: $options);
	}

	/**
	 * The currencies the space's checkouts accept (`GET /wallet/supported-currencies`): none
	 * means its checkouts answer 409 `merchant_not_ready`. `userUuid` reads a member's.
	 *
	 * @return list<Currency>
	 */
	public function listSupportedCurrencies(?SupportedCurrenciesParams $params = null, ?RequestOptions $options = null): array
	{
		$params?->validate();

		return $this->requester->call('GET', '/wallet/supported-currencies', Requester::list(Currency::fromArray(...)), $params?->toQuery() ?? [], options: $options);
	}
}
