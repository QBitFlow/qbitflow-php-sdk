<?php

declare(strict_types=1);

namespace QBitFlow;

/**
 * Placeholders for a checkout's `successUrl` and `cancelUrl`: QBitFlow replaces them with the
 * session's values when it redirects the customer. Put them in the URL as they are (the SDK
 * never URL-encodes them; the server substitutes them literally).
 *
 * ```php
 * successUrl: 'https://shop.example.com/thanks?session=' . Placeholders::UUID,
 * ```
 */
final class Placeholders
{
	/** The checkout session's uuid (`pay@…` or `sub@…`), e.g. for `getStatus()` on your success page. */
	public const UUID = '{{UUID}}';

	/** The transaction's type (`payment`, `createSubscription`…). */
	public const TRANSACTION_TYPE = '{{TRANSACTION_TYPE}}';

	private function __construct()
	{
	}
}
