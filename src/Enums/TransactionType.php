<?php

declare(strict_types=1);

namespace QBitFlow\Enums;

/**
 * Transaction types accepted by the status endpoints and reported on webhooks.
 */
enum TransactionType: string
{
	/** One-time payment transaction (native currency). */
	case ONE_TIME_PAYMENT = 'payment';

	/** Transfer transaction (native currency). */
	case TRANSFER = 'transfer';

	/** Transfer transaction (token). */
	case TOKEN_TRANSFER = 'tokenTransfer';

	/** Create-subscription transaction. */
	case CREATE_SUBSCRIPTION = 'createSubscription';

	/** Cancel-subscription transaction. */
	case CANCEL_SUBSCRIPTION = 'cancelSubscription';

	/** Execute a scheduled subscription payment. */
	case EXECUTE_SUBSCRIPTION_PAYMENT = 'executeSubscription';

	/** Create a pay-as-you-go subscription. */
	case CREATE_PAYG_SUBSCRIPTION = 'createPAYGSubscription';

	/** Cancel a pay-as-you-go subscription. */
	case CANCEL_PAYG_SUBSCRIPTION = 'cancelPAYGSubscription';

	/** Increase the on-chain allowance of a subscription. */
	case INCREASE_ALLOWANCE = 'increaseAllowance';

	/** Update the maximum amount of a subscription. */
	case UPDATE_MAX_AMOUNT = 'updateMaxAmount';

	/** Refund transaction. */
	case REFUND = 'refund';

	/** Faucet transaction (test networks only). */
	case FAUCET = 'faucet';

	/** Claim-funds transaction. */
	case CLAIM_FUNDS = 'claimFunds';
}
