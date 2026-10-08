<?php

declare(strict_types=1);

namespace QBitFlow\Exceptions;

use Throwable;

/**
 * A webhook signature could not be verified: locally ({@see \QBitFlow\Webhooks\Webhook::verify()},
 * `status` 0) or by the API (`verifyRemote`, 400 `invalid_signature`). `reason` says why: one
 * of the `REASON_*` constants.
 */
class WebhookSignatureException extends ApiException
{
	/** The `QBitFlow-Signature` header is empty. */
	public const REASON_MISSING_HEADER = 'missingHeader';

	/** No `t`, a `t` that is not an integer, a duplicate `t`, or no `v1`. */
	public const REASON_MALFORMED_HEADER = 'malformedHeader';

	/** `t` is too far from now, in either direction. */
	public const REASON_TIMESTAMP_OUTSIDE_TOLERANCE = 'timestampOutsideTolerance';

	/** No `v1` matches the expected signature. */
	public const REASON_NO_MATCHING_SIGNATURE = 'noMatchingSignature';

	/** The API's check (`POST /webhooks/verify`) refused it. */
	public const REASON_INVALID_SIGNATURE = 'invalidSignature';

	/**
	 * @param array<string,mixed> $details
	 * @param list<FieldError>    $fieldErrors
	 */
	public function __construct(
		/** Why the signature was refused: one of the `REASON_*` constants. */
		public readonly string $reason,
		string $message = '',
		int $status = 0,
		string $apiCode = '',
		array $details = [],
		string $requestId = '',
		array $fieldErrors = [],
		string $rawBody = '',
		?Throwable $previous = null,
	) {
		parent::__construct($message, $status, $apiCode, $details, $requestId, $fieldErrors, $rawBody, $previous);
	}

	public function getReason(): string
	{
		return $this->reason;
	}
}
