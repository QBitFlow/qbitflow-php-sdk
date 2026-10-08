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
 * $me = QBitFlow::me();
 * $session = QBitFlow::checkoutSessions()->createPayment($params);
 * ```
 *
 * @method static \QBitFlow\Models\Me                        me(?\QBitFlow\RequestOptions $options = null)
 * @method static \QBitFlow\QBitFlow                         onBehalfOf(string $userUuid)
 * @method static \QBitFlow\Services\ProductsService         products()
 * @method static \QBitFlow\Services\CustomersService        customers()
 * @method static \QBitFlow\Services\CheckoutSessionsService checkoutSessions()
 * @method static \QBitFlow\Services\PaymentsService         payments()
 * @method static \QBitFlow\Services\FailuresService         failures()
 * @method static \QBitFlow\Services\SubscriptionsService    subscriptions()
 * @method static \QBitFlow\Services\RefundsService          refunds()
 * @method static \QBitFlow\Services\MembersService          members()
 * @method static \QBitFlow\Services\InvitationsService      invitations()
 * @method static \QBitFlow\Services\WalletsService          wallets()
 * @method static \QBitFlow\Services\AccountingService       accounting()
 * @method static \QBitFlow\Services\WebhooksService         webhooks()
 * @method static \QBitFlow\Services\CurrenciesService       currencies()
 * @method static string                                     getBaseUrl()
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
