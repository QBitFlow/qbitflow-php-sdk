<?php

declare(strict_types=1);

namespace QBitFlow\Params;

use QBitFlow\Support\Validator;

/**
 * Changes a subscription (`subscriptions->update()`): its metadata only. Its price and terms are
 * locked at signup; its status changes through its actions (`subscriptions->cancel()`, its
 * customer's pause and resume).
 */
final readonly class UpdateSubscriptionParams
{
	/**
	 * @param array<string,string>|null $metadata
	 */
	public function __construct(
		/**
		 * Merged into the subscription's: a key set to a value sets it, a key set to `''` deletes
		 * it, keys not named are kept (null: unchanged). At most 50 keys once merged; each key 1 to
		 * 40 of `A-Z a-z 0-9 _ - . :`, each value at most 500 characters without control or
		 * bidirectional characters.
		 */
		public ?array $metadata = null,
	) {
	}

	/**
	 * Checks the metadata sent (the 50-key cap on the merged result is the API's).
	 *
	 * @throws \QBitFlow\Exceptions\ValidationException
	 */
	public function validate(): void
	{
		$v = new Validator();
		$v->metadata('metadata', $this->metadata);
		$v->throwIfAny();
	}

	/** @return array<string,mixed> */
	public function toArray(): array
	{
		return $this->metadata !== null && $this->metadata !== [] ? ['metadata' => (object) $this->metadata] : [];
	}
}
