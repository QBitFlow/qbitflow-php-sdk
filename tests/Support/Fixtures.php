<?php

declare(strict_types=1);

namespace QBitFlow\Tests\Support;

/**
 * JSON fixtures of each response model, following the API reference's types: every wire key set,
 * shaped like the documentation's examples (a 10 USD price paid in BASE:USDC, 0.99 % fee). A key a
 * fixture leaves out is an optional one (decoded as null). Some fixtures also carry keys the API
 * sends but the SDK deliberately leaves out: the tests prove they are ignored.
 */
final class Fixtures
{
	public const CURRENCY = '{"id":8,"code":"BASE:USDC","symbol":"USDC","name":"USD Coin","chain":"BASE","decimals":6,"address":"0x833589fCD6eDb6E08f4c7C32D4f71b54bdA02913","mainCurrencyId":3,"test":false}';

	public const NATIVE = '{"id":1,"code":"ETH","symbol":"ETH","name":"Ethereum","chain":"ETH","decimals":18,"test":false}';

	public const SUMMARY = '{"uuid":"c-1","firstName":"Ada","lastName":"Lovelace","email":"ada@example.com","reference":"crm-1","deleted":true}';

	public const REFUND_SM = '{"uuid":"refund@1","status":"approved","initiatedBy":"merchant","refundPercent":"50","amount":"5.0021","amountMinUnits":"5002100","amountUsd":"5.0021","createdAt":"2026-10-01T12:00:00Z","respondedAt":"2026-10-01T13:00:00Z"}';

	public const ATTEMPT = '{"status":"failed","code":"insufficient_funds","message":"Not enough USDC","txHash":"0xa","at":"2026-10-01T12:00:00Z"}';

	public const TX = '{"blockNumber":"52468103","blockTime":"2026-10-01T11:59:58Z","gasUsed":95000,"fee":{"amount":"0.00000114","amountMinUnits":"1140000000000","amountUsd":"0.005144"},"l1Fee":{"amount":"0.0000001","amountMinUnits":"100000000000","amountUsd":"0.00045"},"nativePriceUsd":"4512.37"}';

	public const SETTLEMENT = '{"feePercent":"0.99","organizationFee":{"address":"0xorg","feePercent":"10"},"referralFee":{"address":"0xref","feePercent":"20"},"split":{"platform":{"amount":"0.0792","amountMinUnits":"79200","amountUsd":"0.0792"},"organization":{"amount":"0.9901","amountMinUnits":"990100","amountUsd":"0.9901"},"referral":{"amount":"0.0198","amountMinUnits":"19800","amountUsd":"0.0198"},"merchant":{"amount":"8.9109","amountMinUnits":"8910900","amountUsd":"8.9109"},"networkFee":{"amount":"0.0042","amountMinUnits":"4200","amountUsd":"0.0042"}},"transaction":' . self::TX . '}';

	public const TRANSFER = '"createdAt":"2026-10-01T12:00:00Z","from":"0xfrom","to":"0xto","amount":"10","amountMinUnits":"10000000","amountUsd":"10","currency":' . self::CURRENCY . ',"txHash":"0xhash","chain":"BASE","explorerUrl":"https://basescan.org/tx/0xhash","test":false,"userUuid":"m-1"';

	public const PAID = '"confirmedAt":"2026-10-01T11:59:58Z","paid":"10.0042","paidMinUnits":"10004200","paidUsd":"10.0042"';

	public const REFUNDING = '"refund":' . self::REFUND_SM . ',"refundable":false,"notRefundableReason":"refundExists"';

	public const FEES = '[{"type":"custom","label":"VAT (20%)","description":"French VAT","amountUsd":"1.30"},{"type":"processingFee","label":"Processing fee","amountUsd":"0.20"}]';

	public const SUB = '{"uuid":"sub@1","reference":"customer-7731","createdAt":"2026-09-01T12:00:00Z","updatedAt":"2026-10-01T12:00:00Z","from":"0xf","to":"0xt","productUuid":"p-1","subscriptionHash":"0x2","currency":' . self::CURRENCY . ',"frequency":{"value":1,"unit":"months"},"status":"stopped","actionRequired":"raiseMaximum","currentPeriodEnd":"2026-10-31T12:00:00Z","priceUsd":"9.99","fees":' . self::FEES . ',"allowance":{"amount":"110","amountMinUnits":"110000000","amountUsd":"110","remainingPeriods":0,"maxPerPeriod":"30","maxPerPeriodMinUnits":"30000000"},"lastBilledAt":"2026-10-01T12:00:00Z","nextBillingAt":"2026-10-31T12:00:00Z","cancelledAt":"2026-10-02T12:00:00Z","cancellationReason":"merchant","cancellableAt":"2026-12-01T12:00:00Z","metadata":{"plan":"pro","crm:account":"A-1042"},"managementPageLink":"https://qbitflow.app/user/s/manage?uuid=sub@1","test":false,"userUuid":"m-1","customerUuid":"c-1","customerReference":"crm-1","customer":' . self::SUMMARY . ',"dunning":{"failedAttempts":1,"remainingAttempts":3,"failingSince":"2026-10-01T12:00:00Z","nextAttemptAt":"2026-10-02T12:00:00Z","awaitingMaximumSince":"2026-10-01T12:00:00Z"}}';

	public const ENDPOINT = '"uuid":"e-1","test":true,"url":"https://x.io/hook","events":["payment.completed"],"includeMembers":true,"payloadVersion":"v2","description":"d","createdAt":"2026-10-01T12:00:00Z","rotatedAt":"2026-10-01T12:00:00Z","disabledAt":"2026-10-02T12:00:00Z","disabledReason":"failing","failingSince":"2026-09-29T12:00:00Z","lastDeliveredAt":"2026-09-28T12:00:00Z"';

	public const SESSION = '"uuid":"sub@1","reference":"order-1044","productUuid":"p-1","productReference":"tshirt","productName":"T-shirt","description":"Blue, size M","priceUsd":"3.99","fees":[{"type":"custom","label":"Shipping","description":"Standard","amountUsd":"0.75"},{"type":"processingFee","label":"Processing fee","amountUsd":"0.07"}],"amountUsd":"4.81","successUrl":"https://x/ok","cancelUrl":"https://x/ko","organizationName":"Shop","userName":"Ada","test":true,"txType":"createSubscription","availableCurrencies":[' . self::CURRENCY . ',' . self::NATIVE . '],"metadata":{"orderId":"order-1044"},"createdAt":"2026-10-01T12:00:00Z","expiresAt":"2026-10-01T13:00:00Z"';

	/**
	 * Fixture name → JSON. The name is the model's class (`Currency/native`: a variant of
	 * `Currency`; `Page`: a page of `CustomerSummary`).
	 *
	 * @return array<string,string>
	 */
	public static function models(): array
	{
		return [
			'Currency' => self::CURRENCY,
			'Currency/native' => self::NATIVE,
			'Payment' => '{"uuid":"pay@1",' . self::TRANSFER . ',"reference":"order-1","priceUsd":"8.5","fees":' . self::FEES . ',"productName":"Pro","productDescription":"Lifetime","metadata":{"orderId":"order-1","crm:customer":"C-5521"},"productUuid":"p-1","customerUuid":"c-1","customerReference":"crm-1","customer":' . self::SUMMARY . ',"settlement":' . self::SETTLEMENT . ',' . self::PAID . ',' . self::REFUNDING . ',"checkoutOpenedAt":"2026-10-01T11:00:00Z"}',
			'PaymentCompleted' => '{"uuid":"pay@1",' . self::TRANSFER . ',"priceUsd":"10","fees":[],"productName":"Pro","metadata":{},"settlement":' . self::SETTLEMENT . ',"managementPageLink":"https://qbitflow.app/user/s/manage?uuid=pay@1"}',
			'SubscriptionSessionData' => '{' . self::SESSION . ',"frequency":{"value":1,"unit":"months"},"trialPeriod":{"value":14,"unit":"days"},"minPeriods":3,"upgradingFromTrial":true}',
			'Bill' => '{"uuid":"sub-hist@1",' . self::TRANSFER . ',"productName":"Pro","productDescription":"Monthly","productUuid":"p-1","subscriptionUuid":"sub@1","customerUuid":"c-1","customerReference":"crm-1","customer":' . self::SUMMARY . ',"settlement":' . self::SETTLEMENT . ',"periodStart":"2026-10-01T12:00:00Z","periodEnd":"2026-10-31T12:00:00Z",' . self::PAID . ',' . self::REFUNDING . '}',
			'Transaction' => '{"type":"bill","uuid":"sub-hist@1","createdAt":"2026-10-01T12:00:00Z","from":"0xf","to":"0xt","productName":"Pro","productDescription":"d","priceUsd":"8.5","fees":' . self::FEES . ',"amount":"10","amountMinUnits":"10000000","amountUsd":"10","currency":' . self::CURRENCY . ',"productUuid":"p-1","txHash":"0xh","customerUuid":"c-1","customer":' . self::SUMMARY . ',"customerReference":"crm-1","subscriptionUuid":"sub@1","periodStart":"2026-10-01T12:00:00Z","periodEnd":"2026-10-31T12:00:00Z","reference":"r","subscriptionReference":"sr","metadata":{"k":"v"},"chain":"SOL","explorerUrl":"https://x","test":true,"userUuid":"m-1","settlement":' . self::SETTLEMENT . ',' . self::PAID . ',' . self::REFUNDING . '}',
			'Failure' => '{"uuid":"f-1","kind":"bill","txUuid":"sub-hist@1","attempt":2,"subscriptionUuid":"sub@1","code":"insufficient_funds","category":"insufficientBalance","message":"m","attempted":"9.99","attemptedMinUnits":"9990000","attemptedUsd":"9.99","currency":' . self::CURRENCY . ',"from":"0xf","txHash":"0xh","customerUuid":"c-1","productUuid":"p-1","createdAt":"2026-10-01T12:00:00Z","customer":' . self::SUMMARY . ',"test":false,"userUuid":"m-1"}',
			'CreatedCheckoutSession' => '{"link":"https://qbitflow.app/checkout?uuid=pay@1","uuid":"pay@1","expiresAt":"2026-10-02T12:00:00Z"}',
			'CheckoutSessionStatus' => '{"uuid":"pay@1","status":"created","txHash":"0xh","message":"m","lastAttempt":' . self::ATTEMPT . ',"expiresAt":"2026-10-02T12:00:00Z"}',
			'Subscription' => self::SUB,
			'TestBill' => '{"billUuid":"sub-hist@019c-1","stage":"waiting","outcome":"paid","attempts":1,"nextAttemptAt":"2026-10-02T12:00:00Z","txHash":"0xh","failureCode":"insufficient_allowance"}',
			'Refund' => '{"uuid":"refund@1","txUuid":"pay@1","reason":"r","status":"approved","createdAt":"2026-10-01T12:00:00Z","merchantMessage":"m","respondedAt":"2026-10-01T13:00:00Z","test":false,"userUuid":"m-1","txHash":"0xh","initiatedBy":"customer","held":true,"paid":"10.0042","paidMinUnits":"10004200","paidUsd":"10.0042","refundPercent":"50","amount":"5.0021","amountMinUnits":"5002100","amountUsd":"5.0021","currency":' . self::CURRENCY . ',"settlement":{"transaction":' . self::TX . '},"chain":"BASE","explorerUrl":"https://e","approval":{"status":"waitingConfirmation","txHash":"0xa","lastAttempt":' . self::ATTEMPT . '},"customerUuid":"c-1","productName":"Pro","customer":' . self::SUMMARY . '}',
			'Customer' => '{"uuid":"c-1","firstName":"Ada","lastName":"Lovelace","email":"ada@example.com","verified":true,"phoneNumber":"+33 6 12 34 56 78","address":"1 rue X","reference":"crm-1","metadata":{"crm":"42"},"createdAt":"2026-10-01T12:00:00Z","test":true,"userUuid":"m-1"}',
			'Product' => '{"uuid":"p-1","name":"Pro","description":"d","priceUsd":"9.99","createdAt":"2026-10-01T12:00:00Z","isActive":true,"reference":"pro","metadata":{"sku":"PRO-1"},"subscription":{"frequency":{"value":1,"unit":"months"},"trialPeriod":{"value":14,"unit":"days"},"minPeriods":3},"paymentLink":"https://qbitflow.app/pay/p-1","test":false,"userUuid":"m-1"}',
			'Member' => '{"userUuid":"m-1","firstName":"Ada","lastName":"L","email":"a@b.co","test":false,"organizationFeePercent":"7.5","trustedAt":"2026-10-03T12:00:00Z","joinedAt":"2026-10-01T12:00:00Z","acceptedCurrencies":[' . self::CURRENCY . '],"spaceUuid":"s-1"}',
			'MemberJoined' => '{"userUuid":"m-1","firstName":"Ada","email":"a@b.co","test":false,"organizationFeePercent":"0","joinedAt":"2026-10-01T12:00:00Z","acceptedCurrencies":[],"spaceUuid":"s-1","invitationUuid":"i-1"}',
			'HeldFunds' => '{"ledgers":[{"txUuid":"pay@1","type":"payment","description":"Payment pay@1","amountUsd":"10","settlement":' . self::SETTLEMENT . ',"currency":' . self::CURRENCY . ',"owed":"8.9109","owedMinUnits":"8910900","owedUsd":"8.9109","createdAt":"2026-10-01T12:00:00Z"},{"txUuid":"refund@1","type":"refund","description":"Refund of payment pay@1","refundedTxUuid":"pay@1","amountUsd":"10.0042","currency":' . self::CURRENCY . ',"owed":"-10.0042","owedMinUnits":"-10004200","owedUsd":"-10.0042","createdAt":"2026-10-02T12:00:00Z"}],"totalUsd":"-1.0933"}',
			'MemberHeldFunds' => '{"userUuid":"m-1","totalUsd":"98.5","count":2,"oldestAt":"2026-09-28T12:00:00Z"}',
			'HeldFundsReleased' => '{"uuid":"transfer@1",' . self::TRANSFER . ',"received":true,"type":"heldFundsRelease","settlement":{"transaction":' . self::TX . '},"ledgers":[{"txUuid":"pay@1","type":"payment","description":"Payment pay@1","amountUsd":"10","currency":' . self::CURRENCY . ',"owed":"9.901","owedMinUnits":"9901000","owedUsd":"9.901","createdAt":"2026-10-01T12:00:00Z"}]}',
			'Wallet' => '{"uuid":"w-1","createdAt":"2026-10-01T12:00:00Z","address":"0xpk","test":false,"userUuid":"m-1","tokenWallets":[{"uuid":"tw-1","createdAt":"2026-10-01T12:00:00Z","currency":' . self::CURRENCY . ',"walletUuid":"w-1","balance":{"currency":' . self::CURRENCY . ',"amount":"12.5","amountMinUnits":"12500000","amountUsd":"12.5"}}],"currency":' . self::NATIVE . '}',
			'Invitation' => '{"uuid":"i-1","test":false,"email":"a@b.co","role":"user","holdFunds":true,"organizationFeePercent":"2.5","redirectUrl":"https://x.io/welcome","expiresAt":"2026-10-08T12:00:00Z","acceptedByUserUuid":"m-1","acceptedAt":"2026-10-02T12:00:00Z","revokedAt":"2026-10-03T12:00:00Z","createdAt":"2026-10-01T12:00:00Z","status":"accepted"}',
			'CreatedInvitation' => '{"uuid":"i-1","test":true,"email":"a@b.co","role":"user","holdFunds":false,"organizationFeePercent":"0","redirectUrl":"https://x.io/welcome","expiresAt":"2026-10-08T12:00:00Z","acceptedByUserUuid":"m-1","acceptedAt":"2026-10-02T12:00:00Z","revokedAt":"2026-10-03T12:00:00Z","createdAt":"2026-10-01T12:00:00Z","status":"pending","link":"https://qbitflow.app/invite?token=x"}',
			'AccountingEvent' => '{"txTimeUtc":"2026-09-13T19:23:26.62Z","type":"refund","reference":"r","relatedReference":"r0","productName":"Pro","customerReference":"crm-1","grossAmountUsd":"-10.0042","platformFeeUsd":"0.099","referralFeeUsd":"0.0198","organizationFeeUsd":"0.9901","netAmountUsd":"-10.0042","chain":"BASE","currency":"USDC","currencyDecimals":6,"currencyPriceUsd":"1.00002","grossAmount":"-10.0042","grossAmountMinUnits":"-10004200","platformFee":"0.099","platformFeeMinUnits":"99000","referralFee":"0.0198","referralFeeMinUnits":"19800","organizationFee":"0.9901","organizationFeeMinUnits":"990100","netAmount":"-10.0042","netAmountMinUnits":"-10004200","networkFeesPaidBy":"merchant","networkFees":"0.0000012","networkFeesMinUnits":"1200000000000","networkFeesCurrency":"ETH","networkFeesCurrencyDecimals":18,"networkFeesCurrencyPriceUsd":"4512.37","networkFeesUsd":"0.00541","platformFeePercent":"0.99","referralFeePercent":"20","organizationFeePercent":"10","fromAddress":"0xf","toAddress":"0xt","txHash":"0xh","blockNumberOrSlot":"52468103","tokenContractOrMint":"0x8335","explorerUrl":"https://e","receiptUrl":"https://r","txUuid":"refund@1","relatedTxUuid":"pay@0","productUuid":"p-1","productReference":"pro","productDescription":"d","customerUuid":"c-1","userUuid":"m-1"}',
			'WebhookEndpointCreated' => '{' . self::ENDPOINT . ',"secret":"whsec_x"}',
			'EventDetail' => '{"id":"evt_1","type":"payment.completed","version":"v2","createdAt":"2026-10-01T12:00:00.123Z","test":false,"userUuid":"m-1","data":{"uuid":"pay@1"},"deliveries":[{"endpointUuid":"e-1","url":"https://x.io/hook","delivered":true,"attempts":[{"uuid":"a-1","eventId":"evt_1","eventType":"payment.completed","endpointUuid":"e-1","attempt":2,"delivered":false,"skipped":true,"statusCode":500,"error":"500 Internal Server Error","durationMs":120,"attemptedAt":"2026-10-01T12:00:01Z"}]}]}',
			'Me' => '{"credential":"apiKey","apiKeyUuid":"k-1","role":"user","onBehalfOfUserUuid":"m-1","space":{"uuid":"s-1","organizationUuid":"o-1","organizationName":"Shop","userUuid":"m-1","test":true,"role":"admin","member":{"userUuid":"m-1","firstName":"Ada","lastName":"L","email":"a@b.co"},"organizationFeePercent":"7.5","trustedAt":"2026-10-01T12:00:00Z","createdAt":"2026-09-01T12:00:00Z"}}',
			'Page' => '{"items":[' . self::SUMMARY . '],"hasMore":true,"nextCursor":"djF8MDE5Y2FkZmQ"}',
		];
	}

	public static function model(string $name): string
	{
		return self::models()[$name];
	}

	/** A page answer `{items, hasMore, nextCursor?}`: `$next` null is the last page. */
	public static function page(string $item, ?string $next = 'djF8MDE5ZWNhODItNTY4MC03YjAw'): string
	{
		return '{"items":[' . $item . '],"hasMore":' . ($next === null ? 'false}' : 'true,"nextCursor":"' . $next . '"}');
	}

	/** A bounded list's answer: one page (`hasMore` false) holding `$items`. */
	public static function list(string ...$items): string
	{
		return '{"items":[' . implode(',', $items) . '],"hasMore":false}';
	}

	/** A webhook.test event (the docs' example shape). */
	public static function webhookTestEvent(): string
	{
		return '{"createdAt":"2026-10-01T12:00:00Z","data":{"endpointUuid":"019eca82-5680-7b00-8000-0000000000e1","message":"This is a test event"},'
			. '"id":"evt_bd1a913d-188e-5ac5-a2d6-5fd25cd67301","test":false,"type":"webhook.test","version":"v2"}';
	}
}
