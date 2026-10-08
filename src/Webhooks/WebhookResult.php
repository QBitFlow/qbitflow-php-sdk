<?php

declare(strict_types=1);

namespace QBitFlow\Webhooks;

use QBitFlow\Events\Event;
use QBitFlow\Exceptions\WebhookSignatureException;
use Throwable;

/**
 * What {@see WebhookRouter::handle()} made of a delivery: the HTTP status to answer, the event
 * (once verified and parsed) and the error.
 *
 * - `200`: handled, or ignored (no handler for its type): QBitFlow does not retry it;
 * - `400`: not signed by QBitFlow ({@see WebhookSignatureException}), or not a v2 event
 *   ({@see \QBitFlow\Exceptions\ValidationException}): no handler ran;
 * - `500`: a handler threw (`error`): QBitFlow retries the delivery;
 * - `405` / `413` (adapters only): not a POST, or a body over 1 MiB.
 *
 * The adapters answer {@see responseBody()}: `{"received":true}`, or `{"error":"…"}` with
 * `invalid signature`, `invalid event`, `cannot read the body` (400), `internal error` (500),
 * `method not allowed` (405), `body too large` (413).
 */
final readonly class WebhookResult
{
	public function __construct(
		/** The HTTP status to answer. */
		public int $status,
		/** The event, once verified and parsed; null on a 400, 405 or 413. */
		public ?Event $event = null,
		/** Why it is not a 200: the signature, parsing, body reading or handler error; null on a 200, 405 or 413. */
		public ?Throwable $error = null,
		private ?string $reason = null,
	) {
	}

	/**
	 * A 400 for a body the adapter could not read.
	 *
	 * @internal
	 */
	public static function unreadableBody(Throwable $error): self
	{
		return new self(400, null, $error, 'cannot read the body');
	}

	/** Whether the delivery was handled (or ignored): a 200. */
	public function ok(): bool
	{
		return $this->status === 200;
	}

	/**
	 * The JSON body the adapters answer: `{"received":true}` on a 200, else
	 * `{"error":"<short reason>"}` (never the secret nor a stack trace).
	 */
	public function responseBody(): string
	{
		if ($this->status === 200) {
			return '{"received":true}';
		}

		return (string) json_encode(['error' => $this->reason()], JSON_UNESCAPED_SLASHES);
	}

	/** The short reason of a non-200 answer (`invalid signature`, `invalid event`, `internal error`…). */
	public function reason(): string
	{
		if ($this->reason !== null) {
			return $this->reason;
		}

		return match (true) {
			$this->status === 200 => 'received',
			$this->status === 405 => 'method not allowed',
			$this->status === 413 => 'body too large',
			$this->status === 400 && $this->error instanceof WebhookSignatureException => 'invalid signature',
			$this->status === 400 => 'invalid event',
			default => 'internal error',
		};
	}
}
