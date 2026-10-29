<?php

/**
 * A marketplace: invite a seller (the invitation is revoked again so the example can run twice),
 * list the invitations and members and, given a member: read them, act in their space with
 * On-Behalf-Of, read their wallets and held funds, set their fee, trust them (--trust) or remove
 * them (--remove). Needs an organization key.
 *
 *     QBITFLOW_API_KEY=sk_… php examples/marketplace.php [memberUserUuid] [--trust] [--remove]
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use QBitFlow\Exceptions\ConflictException;
use QBitFlow\Params\CreatePaymentSessionParams;
use QBitFlow\Params\AcceptedCurrenciesParams;
use QBitFlow\Placeholders;
use QBitFlow\QBitFlow;

$client = QBitFlow::fromEnv();

// 1. Invite the seller (a member). They exist once they accepted: the member.joined webhook.
try {
	// docs:start members-invite
	$created = $client->invitations->create(new \QBitFlow\Params\CreateInvitationParams(
		email: 'seller@example.com',
		holdFunds: true,              // hold their payments until you trust them (always sent)
		organizationFeePercent: '10', // your commission on their payments: "0" to "50" %
		redirectUrl: 'https://shop.example.com/sellers/welcome',
	));
	// QBitFlow also emails them a link of their own.
	echo "Invitation {$created->uuid}: {$created->link}\n";
	// docs:end members-invite
} catch (ConflictException $e) {
	echo "Not invited ({$e->apiCode})\n"; // already_joined
	$created = null;
}

// docs:start invitations-list
$page = $client->invitations->list(new \QBitFlow\Params\InvitationListParams(status: \QBitFlow\Enums\InvitationStatus::PENDING));
foreach ($page->items as $invitation) {
	echo "{$invitation->uuid} {$invitation->email}, expires {$invitation->expiresAt->format('Y-m-d')}\n";
}
// docs:end invitations-list

if ($created !== null) {
	$invitationUuid = $created->uuid;
	// docs:start invitations-revoke
	$client->invitations->revoke($invitationUuid); // a pending invitation only (204)
	echo "Revoked {$invitationUuid}: its link no longer works\n";
	// docs:end invitations-revoke
}

// docs:start members-list
foreach ($client->members->iterate() as $member) {
	printf("%s %s %s: fee %s %%, %s\n", $member->userUuid, $member->firstName, $member->email,
		$member->organizationFeePercent, $member->trustedAt !== null ? 'trusted' : 'funds held');
}
// docs:end members-list

$memberUuid = array_values(array_filter(array_slice($argv, 1), static fn (string $arg): bool => ! str_starts_with($arg, '--')))[0] ?? null;
if ($memberUuid === null) {
	exit("Pass a member's userUuid (from member.joined) to act for them.\n");
}

// docs:start members-get
$member = $client->members->get($memberUuid); // the member's userUuid
echo "{$member->firstName} " . ($member->lastName ?? '') . " <{$member->email}>, joined {$member->joinedAt->format('Y-m-d')}\n";
foreach ($member->acceptedCurrencies as $currency) {
	echo "  accepts {$currency->code}\n"; // none: their checkouts answer 409 merchant_not_ready
}
// docs:end members-get

// docs:start client-on-behalf-of
// An organization key acts in a member's space: every request sends On-Behalf-Of.
$seller = $client->onBehalfOf($memberUuid); // shares $client's transport and configuration
echo count($seller->products->list()->items), " products on the first page of the member's space\n";
// docs:end client-on-behalf-of

// Sell for them: their space must accept a currency, else their checkouts answer 409 merchant_not_ready.
if ($client->wallets->listAcceptedCurrencies(new AcceptedCurrenciesParams(userUuid: $memberUuid)) !== []) {
	$session = $seller->checkoutSessions->createPayment(new CreatePaymentSessionParams(
		productName: 'T-shirt',
		description: 'Blue, size M',
		priceUsd: '4.99',
		successUrl: 'https://shop.example.com/orders/success?uuid=' . Placeholders::UUID,
	));
	echo "Seller checkout: {$session->link}\n";
}

// docs:start members-wallets
foreach ($client->wallets->listForMember($memberUuid) as $wallet) {
	echo "{$wallet->currency->chain} {$wallet->address}\n";
}
// docs:end members-wallets

// docs:start members-held-funds
foreach ($client->members->listHeldFunds() as $owed) { // every member you owe, the longest held first
	echo "{$owed->userUuid}: {$owed->totalUsd} USD over {$owed->count} lines\n";
}

$held = $client->members->getHeldFunds($memberUuid);
printf("Owed to %s: %s USD over %d lines\n", $memberUuid, $held->totalUsd, count($held->ledgers));
// docs:end members-held-funds

/** The member's side: run with a member's key, or an organization client acting for them. */
function showOwnHeldFunds(QBitFlow $client): void
{
	// docs:start members-own-held-funds
	// With a member's key (or a client from $client->onBehalfOf($memberUuid)):
	$held = $client->members->getOwnHeldFunds();
	printf("Held for me: %s USD over %d lines\n", $held->totalUsd, count($held->ledgers));
	// docs:end members-own-held-funds
}
showOwnHeldFunds($seller);

// docs:start members-update
$member = $client->members->update($memberUuid, new \QBitFlow\Params\UpdateMemberParams(
	organizationFeePercent: '10', // a percent string ("0" to "50"); existing checkouts keep their fee
));
echo "Fee now {$member->organizationFeePercent} %\n";
// docs:end members-update

if (in_array('--trust', $argv, true)) {
	// docs:start members-trust
	// Their new payments go to their own wallets from now on (one-way). What is already held stays
	// held until you release it from the dashboard (heldFunds.released tells you).
	try {
		$member = $client->members->trust($memberUuid);
		echo 'Trusted since ' . ($member->trustedAt?->format(DATE_ATOM) ?? '?') . "\n";
	} catch (\QBitFlow\Exceptions\ConflictException $e) {
		if ($e->apiCode !== 'member_has_no_wallet') {
			throw $e;
		}
		echo "Not trusted: their own wallets accept none of your currencies yet\n";
	}
	// docs:end members-trust
}

if (in_array('--remove', $argv, true)) {
	// docs:start members-remove
	try {
		$client->members->remove($memberUuid); // their keys stop working, their checkouts close
		echo "Removed\n";
	} catch (\QBitFlow\Exceptions\ConflictException $e) {
		if ($e->apiCode !== 'held_funds_pending') {
			throw $e;
		}
		echo "You still hold their funds: release them in the dashboard first\n";
	}
	// docs:end members-remove
}
