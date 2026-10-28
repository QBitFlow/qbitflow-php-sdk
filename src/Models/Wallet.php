<?php

declare(strict_types=1);

namespace QBitFlow\Models;

use DateTimeImmutable;
use QBitFlow\Support\Cast;

/**
 * A space's receiving address on one chain (`currency`, its native coin), with the currencies
 * it accepts on that chain (`tokenWallets`). Its mode is the space's. Wallets are added and
 * removed in the dashboard.
 */
final readonly class Wallet extends Model
{
	/**
	 * The wallet's id.
	 */
	public string $uuid;

	/**
	 * When it was added.
	 */
	public DateTimeImmutable $createdAt;

	/**
	 * The wallet's address on its chain (EVM: `0x…`, Solana: base58).
	 */
	public string $address;

	/**
	 * True in test mode.
	 */
	public bool $test;

	/**
	 * The member whose wallet it is; null for the organization's own.
	 */
	public ?string $userUuid;

	/**
	 * The currencies it accepts on its chain; `[]` when none.
	 *
	 * @var list<TokenWallet>
	 */
	public array $tokenWallets;

	/**
	 * The chain, by its native coin.
	 */
	public Currency $currency;

	/**
	 * @param array<string,mixed> $data
	 */
	protected function __construct(array $data)
	{
		$this->uuid = Cast::string($data, 'uuid');
		$this->createdAt = Cast::date($data, 'createdAt');
		$this->address = Cast::string($data, 'address');
		$this->test = Cast::bool($data, 'test');
		$this->userUuid = Cast::nullableString($data, 'userUuid');
		$this->tokenWallets = Cast::listOf($data, 'tokenWallets', TokenWallet::fromArray(...));
		$this->currency = Cast::object($data, 'currency', Currency::fromArray(...));
	}
}
