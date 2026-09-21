<?php

declare(strict_types=1);

namespace QBitFlow\Laravel\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * Facade for the QBitFlow client.
 *
 * ```php
 * use QBitFlow\Laravel\Facades\QBitFlow;
 *
 * $products = QBitFlow::products()->getAll();
 * ```
 *
 * @method static \QBitFlow\Requests\CustomerRequests          customers()
 * @method static \QBitFlow\Requests\ProductRequests           products()
 * @method static \QBitFlow\Requests\UserRequests              users()
 * @method static \QBitFlow\Requests\ApiKeyRequests            apiKeys()
 * @method static \QBitFlow\Requests\WebhookRequests           webhooks()
 * @method static \QBitFlow\Requests\PaymentRequests           oneTimePayments()
 * @method static \QBitFlow\Requests\SubscriptionRequests      subscriptions()
 * @method static \QBitFlow\Requests\TransactionStatusRequests transactionStatus()
 * @method static \QBitFlow\Requests\RefundRequests            refunds()
 * @method static \QBitFlow\Requests\AccountingRequests        accounting()
 * @method static \QBitFlow\Requests\ClaimRequests             claims()
 * @method static \QBitFlow\Requests\CurrencyRequests          currencies()
 * @method static string                                       getApiKey()
 * @method static string                                       getBaseUrl()
 *
 * @see \QBitFlow\QBitFlow
 */
final class QBitFlow extends Facade
{
	protected static function getFacadeAccessor(): string
	{
		return \QBitFlow\QBitFlow::class;
	}
}
