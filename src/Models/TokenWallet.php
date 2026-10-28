<?php

declare(strict_types=1);

namespace QBitFlow\Models;

use DateTimeImmutable;
use QBitFlow\Support\Cast;

/**
 * A currency a wallet accepts on its chain: a token (USDC on Base) or the chain's native coin.
 */
final readonly class TokenWallet extends Model
{
	/**
	 * The token wallet's id.
	 */
	public string $uuid;

	/**
	 * When the wallet started accepting it.
	 */
	public DateTimeImmutable $createdAt;

	/**
	 * The currency accepted.
	 */
	public Currency $currency;

	/**
	 * The wallet it belongs to.
	 */
	public string $walletUuid;

	/**
	 * What the wallet holds of the currency, read on its chain now: set only by
	 * `wallets->list()` with `withBalances`, and null when the chain couldn't be read.
	 */
	public ?Balance $balance;

	/**
	 * @param array<string,mixed> $data
	 */
	protected function __construct(array $data)
	{
		$this->uuid = Cast::string($data, 'uuid');
		$this->createdAt = Cast::date($data, 'createdAt');
		$this->currency = Cast::object($data, 'currency', Currency::fromArray(...));
		$this->walletUuid = Cast::string($data, 'walletUuid');
		$this->balance = Cast::nullableObject($data, 'balance', Balance::fromArray(...));
	}
}
