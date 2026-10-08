<?php

/**
 * The catalog: create a product (or reuse it), list the products and one page of customers, and
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
		price: 4.99,
		description: 'Blue, size M',
		reference: 'tshirt-blue-m', // your own reference, unique per space
	));
	echo "Created {$product->uuid}, payment link {$product->paymentLink}\n";
	// docs:end products-create
} catch (ConflictException $e) {
	if ($e->apiCode !== 'unique_violation') {
		throw $e;
	}
	$product = $client->products->getByReference('tshirt-blue-m'); // created by an earlier run
}

// docs:start products-list
foreach ($client->products->list() as $product) {
	printf("%s %s: %.2f USD\n", $product->reference, $product->name, $product->price);
}
// docs:end products-list

// docs:start customers-list
$page = $client->customers->list(new \QBitFlow\Params\CustomerListParams(limit: 20));
foreach ($page->items as $customer) {
	echo "{$customer->uuid} {$customer->email}\n";
}
$cursor = $page->nextCursor; // null on the last page; else pass it back as `cursor` for the next one
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
