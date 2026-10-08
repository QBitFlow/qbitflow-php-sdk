<?php

declare(strict_types=1);

namespace QBitFlow;

/**
 * Per-call options, the last argument of every method.
 *
 * ```php
 * $client->checkoutSessions->createPayment($params, new RequestOptions(
 *     onBehalfOf: $member->userUuid,       // this call acts in the member's space
 *     idempotencyKey: 'checkout-order-1044', // the same key returns the same session
 *     requestId: 'job-7781',               // sent as X-Request-Id, echoed in errors
 * ));
 * ```
 */
final readonly class RequestOptions
{
	public function __construct(
		/**
		 * Acts in a member's space for this call (`On-Behalf-Of`: a member's userUuid; organization
		 * key only). It overrides the client's; `''` forces the organization's own space; null keeps
		 * the client's.
		 */
		public ?string $onBehalfOf = null,
		/**
		 * The `Idempotency-Key` of one of the 7 idempotent creates (the SDK otherwise generates a
		 * UUID v4 per call), e.g. to retry a create across processes: 1 to 255 printable ASCII
		 * characters without spaces. Other methods ignore it.
		 */
		public ?string $idempotencyKey = null,
		/** Sent as `X-Request-Id` (1 to 128 of `A-Z a-z 0-9 - _ . :`); the API echoes it and errors carry it. */
		public ?string $requestId = null,
	) {
	}
}
