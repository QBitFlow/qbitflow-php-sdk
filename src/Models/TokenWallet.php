<?php

declare(strict_types=1);

namespace QBitFlow\Models;

use DateTimeImmutable;
use QBitFlow\Support\Cast;

/**
 * A token enabled on a wallet.
 */
final readonly class TokenWallet extends Model
{
	/**
	 * The token wallet's id.
	 */
	public string $uuid;

	/**
	 * When it was enabled.
	 */
	public DateTimeImmutable $createdAt;

	/**
	 * The token's currency id.
	 */
	public int $tokenId;

	/**
	 * The token's currency.
	 */
	public Currency $token;

	/**
	 * The wallet it belongs to.
	 */
	public string $walletUuid;

	/**
	 * Its balance; set only by `wallets->list()` with withBalances.
	 */
	public ?Balance $balance;

	/**
	 * @param array<string,mixed> $data
	 */
	protected function __construct(array $data)
	{
		$this->uuid = Cast::string($data, 'uuid');
		$this->createdAt = Cast::date($data, 'createdAt');
		$this->tokenId = Cast::uint($data, 'tokenId');
		$this->token = Cast::object($data, 'token', Currency::fromArray(...));
		$this->walletUuid = Cast::string($data, 'walletUuid');
		$this->balance = Cast::nullableObject($data, 'balance', Balance::fromArray(...));
	}
}
