<?php

declare(strict_types=1);

namespace QBitFlow\Params;

use QBitFlow\Enums\EventType;
use QBitFlow\Enums\WebhookPayloadVersion;
use QBitFlow\Support\Validator;

/**
 * Updates a webhook endpoint (`webhooks->endpoints->update()`): fields left `null` are unchanged.
 */
final readonly class UpdateWebhookEndpointParams
{
	/**
	 * @param list<string>|null $events Replaces the event types ({@see EventType}): null leaves them
	 *                                  unchanged, `[]` means every type.
	 */
	public function __construct(
		/** The new URL (null or `''`: unchanged). */
		public ?string $url = null,
		public ?array $events = null,
		/** Changes whether an organization endpoint receives its members' events. */
		public ?bool $includeMembers = null,
		/** The new note; `''` clears it, null leaves it unchanged. */
		public ?string $description = null,
		/** `v2` moves an endpoint migrated from v1 to the event envelope (never back), see {@see WebhookPayloadVersion}. */
		public ?string $payloadVersion = null,
		/** False pauses the endpoint; true enables it again. */
		public ?bool $enabled = null,
	) {
	}

	/** @throws \QBitFlow\Exceptions\ValidationException */
	public function validate(): void
	{
		$v = new Validator();
		$v->url('url', $this->url);
		$v->endpointEvents('events', array_values($this->events ?? []));
		$v->text('description', $this->description, 0, 200);
		$v->oneOf('payloadVersion', $this->payloadVersion, [WebhookPayloadVersion::V2]);
		$v->throwIfAny();
	}

	/** @return array<string,mixed> */
	public function toArray(): array
	{
		$out = [];
		if ($this->url !== null && $this->url !== '') {
			$out['url'] = $this->url;
		}
		if ($this->events !== null) {
			$out['events'] = array_values($this->events);
		}
		if ($this->includeMembers !== null) {
			$out['includeMembers'] = $this->includeMembers;
		}
		if ($this->description !== null) {
			$out['description'] = $this->description;
		}
		if ($this->payloadVersion !== null && $this->payloadVersion !== '') {
			$out['payloadVersion'] = $this->payloadVersion;
		}
		if ($this->enabled !== null) {
			$out['enabled'] = $this->enabled;
		}

		return $out;
	}
}
