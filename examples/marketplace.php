<?php

/**
 * A marketplace: invite a seller, sell for them with On-Behalf-Of, read their held funds,
 * trust them. Needs an organization key.
 *
 *     QBITFLOW_API_KEY=sk_… php examples/marketplace.php seller@example.com [memberUserUuid]
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use QBitFlow\Exceptions\ConflictException;
use QBitFlow\Params\CreateInvitationParams;
use QBitFlow\Params\CreatePaymentSessionParams;
use QBitFlow\Params\SupportedCurrenciesParams;
use QBitFlow\Params\UpdateMemberParams;
use QBitFlow\QBitFlow;

$client = new QBitFlow(apiKey: (string) getenv('QBITFLOW_API_KEY'), baseUrl: getenv('QBITFLOW_BASE_URL') ?: null);

// 1. Invite the seller (a member). They exist once they accepted: the member.joined webhook.
try {
	$created = $client->invitations->create(new CreateInvitationParams(
		email: $argv[1] ?? 'seller@example.com',
		trustLayer: true,            // hold their payments until members->trust()
		organizationFeePercent: 5.0, // your commission
		redirectUrl: 'https://market.example.com/welcome',
	));
	echo "Invitation {$created->invitation->uuid}: {$created->link}\n";
} catch (ConflictException $e) {
	echo "Not invited ({$e->apiCode})\n"; // already_joined
}

$memberUuid = $argv[2] ?? null;
if ($memberUuid === null) {
	exit("Pass a member's userUuid (from member.joined) to sell for them.\n");
}

// 2. Sell for them: their space must accept a currency.
$currencies = $client->wallets->listSupportedCurrencies(new SupportedCurrenciesParams(userUuid: $memberUuid));
if ($currencies === []) {
	exit("The seller cannot be paid yet (their checkouts would answer 409 merchant_not_ready).\n");
}
$seller = $client->onBehalfOf($memberUuid);
$session = $seller->checkoutSessions->createPayment(new CreatePaymentSessionParams(
	productName: 'Handmade mug',
	price: 4.5,
	successUrl: 'https://market.example.com/orders/{{UUID}}',
));
echo "Seller checkout: {$session->link}\n";

// 3. Commission and held funds.
$held = $client->members->getHeldFunds($memberUuid);
printf("Owed to the seller: %.2f USD over %d lines\n", $held->totalAmount, count($held->ledgers));

$member = $client->members->trust($memberUuid); // new payments go to their own wallets
echo 'Trusted since ' . ($member->trustedAt?->format(DATE_ATOM) ?? '?') . "\n";

$client->members->update($memberUuid, new UpdateMemberParams(organizationFeePercent: 7.5));
