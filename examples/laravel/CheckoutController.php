<?php

/**
 * Turning an order into a QBitFlow checkout link.
 *
 * The client is resolved from the container — set QBITFLOW_API_KEY in .env and the service
 * provider does the rest.
 */

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\RedirectResponse;
use QBitFlow\Dto\Session\CreatePaymentSessionDto;
use QBitFlow\Dto\Session\CreateSubscriptionSessionDto;
use QBitFlow\Exceptions\QBitFlowException;
use QBitFlow\QBitFlow;
use QBitFlow\Support\Duration;

final class CheckoutController
{
    public function __construct(private readonly QBitFlow $qbitflow)
    {
    }

    /**
     * Start a one-time payment for an order.
     */
    public function pay(Order $order): RedirectResponse
    {
        try {
            $session = $this->qbitflow->oneTimePayments->createSession(new CreatePaymentSessionDto(
                // Your own order ID comes back on the payment and in the webhook, so you
                // never have to store QBitFlow's UUID.
                reference: (string) $order->id,
                productReference: $order->product->sku,
                customerReference: (string) $order->user_id,
                successUrl: route('orders.show', $order),
                cancelUrl: route('orders.cancelled', $order),
            ));
        } catch (QBitFlowException $e) {
            report($e);

            return back()->withErrors(['checkout' => 'Could not start the payment. Please try again.']);
        }

        return redirect()->away($session->link);
    }

    /**
     * Start a monthly subscription with a one-week trial.
     */
    public function subscribe(Order $order): RedirectResponse
    {
        $session = $this->qbitflow->subscriptions->createSession(new CreateSubscriptionSessionDto(
            frequency: Duration::months(1),
            productReference: $order->product->sku,
            trialPeriod: Duration::days(7),
            customerReference: (string) $order->user_id,
            reference: (string) $order->id,
        ));

        return redirect()->away($session->link);
    }

    /**
     * A marketplace variant: credit one of your own users with a single organization key.
     *
     * Funds route to that user's wallet and your platform fee is applied — no per-user API
     * key needed. Requires an admin or owner key.
     */
    public function payVendor(Order $order): RedirectResponse
    {
        $session = $this->qbitflow->oneTimePayments
            ->onBehalfOf($order->vendor->qbitflow_user_id)
            ->createSession(new CreatePaymentSessionDto(
                reference: (string) $order->id,
                productReference: $order->product->sku,
            ));

        return redirect()->away($session->link);
    }
}
