<?php

declare(strict_types=1);

namespace QBitFlow\Enums;

/**
 * A transaction's kind (`checkout.expired`'s `txType`: `payment` or `createSubscription`).
 *
 * An open enum: the API's value is kept as a plain `string`, and a value this SDK does not
 * know yet is kept as is, never rejected. Compare against these constants.
 */
final class TransactionType
{
	public const PAYMENT = 'payment';

	public const TRANSFER = 'transfer';

	public const TOKEN_TRANSFER = 'tokenTransfer';

	public const CREATE_SUBSCRIPTION = 'createSubscription';

	public const CANCEL_SUBSCRIPTION = 'cancelSubscription';

	public const FORCE_CANCEL_SUBSCRIPTION = 'forceCancelSubscription';

	public const EXECUTE_SUBSCRIPTION = 'executeSubscription';

	public const CREATE_PAYG_SUBSCRIPTION = 'createPaygSubscription';

	public const CANCEL_PAYG_SUBSCRIPTION = 'cancelPaygSubscription';

	public const INCREASE_ALLOWANCE = 'increaseAllowance';

	public const UPDATE_MAX_AMOUNT = 'updateMaxAmount';

	public const REFUND = 'refund';

	public const FAUCET = 'faucet';

	public const CLAIM_FUNDS = 'claimFunds';

	public const RELEASE_HELD_FUNDS = 'releaseHeldFunds';

	/**
	 * Every value this SDK knows.
	 *
	 * @return list<string>
	 */
	public static function values(): array
	{
		return [
			self::PAYMENT,
			self::TRANSFER,
			self::TOKEN_TRANSFER,
			self::CREATE_SUBSCRIPTION,
			self::CANCEL_SUBSCRIPTION,
			self::FORCE_CANCEL_SUBSCRIPTION,
			self::EXECUTE_SUBSCRIPTION,
			self::CREATE_PAYG_SUBSCRIPTION,
			self::CANCEL_PAYG_SUBSCRIPTION,
			self::INCREASE_ALLOWANCE,
			self::UPDATE_MAX_AMOUNT,
			self::REFUND,
			self::FAUCET,
			self::CLAIM_FUNDS,
			self::RELEASE_HELD_FUNDS,
		];
	}

	private function __construct()
	{
	}
}
