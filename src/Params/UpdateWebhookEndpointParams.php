<?php

declare(strict_types=1);

namespace QBitFlow\Params;

use QBitFlow\Enums\EventType;
use QBitFlow\Support\Validator;

/**
 * Updates a webhook endpoint (`webhooks->endpoints->update()`): fields left `null` are unchanged.
 */
final readonly class UpdateWebhookEndpointParams
{
	/**
	 * Pass the arguments by name; an optional one left null is not sent. Nothing is checked
	 * here: the service method checks the values before sending (a
	 * {@see \QBitFlow\Exceptions\ValidationException} naming each bad one, nothing sent).
	 *
	 * @param list<string>|null $events Replaces the event types ({@see EventType}; at most 20, not
	 *                                  `webhook.test`): null leaves them unchanged, `[]` means
	 *                                  every type.
	 */
	public function __construct(
		/** The new URL (null or `''`: unchanged). */
		public ?string $url = null,
		public ?array $events = null,
		/** Changes whether an organization endpoint receives its members' events. */
		public ?bool $includeMembers = null,
		/** The new note; `''` clears it, null leaves it unchanged. */
		public ?string $description = null,
		/**
		 * False pauses the endpoint (it receives nothing, deliveries in progress stop); true
		 * enables it again, also after it was disabled for failing.
		 */
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
		if ($this->enabled !== null) {
			$out['enabled'] = $this->enabled;
		}

		return $out;
	}
}
