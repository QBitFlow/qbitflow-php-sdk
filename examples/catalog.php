<?php

/**
 * The catalog: create a product (or reuse it), list every product and one page of customers, and
 * open a checkout for the product by its reference (expired at once, so the example can run again).
 *
 *     QBITFLOW_API_KEY=sk_… php examples/catalog.php
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use QBitFlow\Exceptions\ConflictException;
use QBitFlow\QBitFlow;

$client = QBitFlow::fromEnv();

try {
	// docs:start products-create
	$product = $client->products->create(new \QBitFlow\Params\CreateProductParams(
		name: 'T-shirt',
		priceUsd: '4.99', // a decimal string, never a float
		description: 'Blue, size M',
		reference: 'tshirt-blue-m', // your own reference, unique per space
	));
	echo "Created {$product->uuid}, payment link {$product->paymentLink}\n";
	// docs:end products-create
} catch (ConflictException $e) {
	if ($e->apiCode !== 'unique_violation') {
		throw $e;
	}
	// Created by an earlier run: runs GET /products?reference= and returns its one product.
	$product = $client->products->getByReference('tshirt-blue-m');
}

// docs:start products-list
// iterate() walks every page (list() returns one); hidden products only with includeHidden.
foreach ($client->products->iterate() as $product) {
	echo "{$product->reference} {$product->name}: {$product->priceUsd} USD\n";
}
// docs:end products-list

// docs:start customers-list
$page = $client->customers->list(new \QBitFlow\Params\CustomerListParams(limit: 20));
foreach ($page->items as $customer) {
	echo "{$customer->uuid} {$customer->email}\n";
}
if ($page->hasMore) {
	$cursor = $page->nextCursor; // pass it back, verbatim, as `cursor` for the next page
}
// docs:end customers-list

try {
	// docs:start checkout-create-payment-product
	$session = $client->checkoutSessions->createPayment(new \QBitFlow\Params\CreatePaymentSessionParams(
		productReference: 'tshirt-blue-m', // or productUuid: $product->uuid
		reference: 'order-1042',
		successUrl: 'https://shop.example.com/orders/success?uuid=' . \QBitFlow\Placeholders::UUID,
		cancelUrl: 'https://shop.example.com/orders/cancel',
	));
	echo "Pay at {$session->link}\n";
	// docs:end checkout-create-payment-product
} catch (ConflictException $e) {
	exit("No checkout ({$e->apiCode}): unique_violation when order-1042 has a payment or an open checkout.\n");
}

$client->checkoutSessions->expire($session->uuid); // frees order-1042 for the other examples
