<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use QBitFlow\Laravel\Facades\QBitFlow;
use QBitFlow\Params\CreatePaymentSessionParams;
use QBitFlow\Placeholders;
use QBitFlow\RequestOptions;

/**
 * Opens a hosted checkout for an order and sends the customer to it.
 */
final class CheckoutController
{
	public function store(string $order): RedirectResponse
	{
		$session = QBitFlow::checkoutSessions()->createPayment(
			new CreatePaymentSessionParams(
				productName: 'Order ' . $order,
				priceUsd: '19.99', // a decimal string, never a float
				reference: 'order-' . $order,
				successUrl: url('/orders/' . $order . '?session=' . Placeholders::UUID),
				cancelUrl: url('/cart'),
			),
			// Retrying the request (a double click, a queue job) returns the same session.
			new RequestOptions(idempotencyKey: 'checkout-order-' . $order),
		);

		// Fulfil on the PaymentCompleted event, never on the redirect to successUrl.
		return redirect()->away($session->link);
	}
}
