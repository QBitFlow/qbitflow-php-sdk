<?php

declare(strict_types=1);

namespace QBitFlow\Models;

use DateTimeImmutable;
use QBitFlow\Support\Cast;

/**
 * A wallet of a space on one chain, with its token wallets.
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
	 * The wallet's address.
	 */
	public string $publicKey;

	/**
	 * True in test mode.
	 */
	public bool $test;

	/**
	 * The member whose wallet it is; null for the organization's own.
	 */
	public ?string $userUuid;

	/**
	 * The tokens enabled on it.
	 *
	 * @var list<TokenWallet>
	 */
	public array $tokenWallets;

	/**
	 * The chain's native coin.
	 */
	public int $currencyId;

	/**
	 * That native coin.
	 */
	public Currency $currency;

	/**
	 * @param array<string,mixed> $data
	 */
	protected function __construct(array $data)
	{
		$this->uuid = Cast::string($data, 'uuid');
		$this->createdAt = Cast::date($data, 'createdAt');
		$this->publicKey = Cast::string($data, 'publicKey');
		$this->test = Cast::bool($data, 'test');
		$this->userUuid = Cast::nullableString($data, 'userUuid');
		$this->tokenWallets = Cast::listOf($data, 'tokenWallets', TokenWallet::fromArray(...));
		$this->currencyId = Cast::uint($data, 'currencyId');
		$this->currency = Cast::object($data, 'currency', Currency::fromArray(...));
	}
}
